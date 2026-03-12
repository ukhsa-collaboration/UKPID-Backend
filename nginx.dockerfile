FROM nginx:1.29.3-alpine

COPY nginx.conf /etc/nginx/templates/default.conf.template
