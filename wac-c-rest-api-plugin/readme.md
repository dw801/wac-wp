WAC REST API

This is a custom wordpress plugin to add WAC-specific endpoints to the worpress REST api.

Shared hosting will probably have authorization headers disabled by default. To enable, 
add the following to .htaccess:

# WP REST API 
RewriteEngine on
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
# END WP REST API
