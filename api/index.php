<?php

/*
 * Vercel runs PHP only from api/, so every request is forwarded to Laravel's
 * front controller. Static files are served from public/ before this runs,
 * except index.php and .htaccess, which vercel.json routes here so their
 * source is never served as a static file.
 */
require __DIR__.'/../public/index.php';
