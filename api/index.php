<?php

/*
 * Vercel runs PHP only from api/, so every request is forwarded to Laravel's
 * front controller. Static files are served from public/ before this runs.
 */
require __DIR__.'/../public/index.php';
