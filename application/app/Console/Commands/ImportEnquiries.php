<?php

namespace App\Console\Commands;

use App\Migration\CsvReader;
use App\Migration\DbWriter;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class ImportEnquiries extends Command
{
    private DbWriter $dbWriter;

    protected $signature = 'enquiry:import
                        {inputDir : The directory to process}
                        {files=20 : The number of files to process (default: 20)}
                        {--reprocess-errors : Reprocess files that are in the error directory}';

    protected $description = 'Import split CSV files into MongoDB';

    public function __construct()
    {
        $this->dbWriter = new DbWriter();
        parent::__construct();
    }

    public function handle()
    {
        $inputDir = $this->argument('inputDir');
        $archiveDir = $inputDir.'/archive';
        $errorDir = $inputDir.'/error';
        $reprocessErrors = $this->option('reprocess-errors');
        $filesToProcess = $this->argument('files'); // Get the number of files to process

        // Check for required directories and create if necessary
        if (! is_dir($inputDir)) {
            $this->error("The provided directory {$inputDir} does not exist!");

            return;
        }

        if (! is_dir($archiveDir)) {
            mkdir($archiveDir);
        }

        if (! is_dir($errorDir)) {
            mkdir($errorDir);
        }

        // reprocess files in error dir if --reprocess-errors flag is passed
        $processingDir = $reprocessErrors ? $errorDir : $inputDir;
        $splitFiles = array_diff(scandir($processingDir), ['..', '.', 'archive', 'error']);

        if (empty($splitFiles)) {
            $this->error("No CSV files found in $processingDir");

            return;
        }

        usort($splitFiles, function ($a, $b) {
            return (int) str_replace('split_', '', $a) - (int) str_replace('split_', '', $b);
        });

        $processedCount = 0;
        foreach ($splitFiles as $file) {
            if ($processedCount >= $filesToProcess) {
                break; // Stop processing if the specified number of files have been processed
            }

            $filePath = "{$processingDir}/{$file}";
            try {
                $data = CsvReader::execute($filePath);
                $this->info("Importing $file");
                $this->dbWriter->write($data);
                // Move to archive if write is successful
                rename($filePath, "{$archiveDir}/{$file}");
                $processedCount++; // Increment the processed file count
            } catch (\Exception $exception) {
                if (! $reprocessErrors) {
                    // move to error if there's an exception and we're not reprocessing errors
                    rename($filePath, "{$errorDir}/{$file}");
                }
                // log the error whether we're reprocessing or not
                $this->error("Error importing $file: ".$exception->getMessage());
            }
        }

        $this->info("Processed $processedCount files in {$processingDir}");
    }

    protected function getOptions()
    {
        return [
            ['reprocess-errors', null, InputOption::VALUE_NONE, 'Reprocess files that are in the error directory.'],
        ];
    }
}
