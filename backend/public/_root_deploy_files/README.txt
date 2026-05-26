Copy these two files to your DOMAIN ROOT (public_html), not inside backend/public:
- .htaccess -> /public_html/.htaccess
- index.php -> /public_html/index.php

Reason:
Root front-controller handles /index.php/api/* requests and must preserve Authorization header.