FROM nginx:1-alpine

COPY nginx.conf /etc/nginx/templates/default.conf.template
