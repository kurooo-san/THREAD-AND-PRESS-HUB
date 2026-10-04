<?php
/**
 * Router for PHP's built-in server (localhost:3000).
 *
 * Started by server.js in the project root (npm start), or directly:
 *   C:\xampp\php\php.exe -S localhost:3000 -t . scripts/router.php
 *
 * The built-in server ignores .htaccess, so this repeats the access rules
 * Apache enforces (storage/.htaccess, uploads/.htaccess and the Dockerfile).
 * Everything else is served exactly as Apache would.
 */

$path = strtolower(rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/'));
$path = str_replace('\\', '/', $path);

$deniedDirs = '#^/(storage|vendor|tests|scripts|docker|database|docs)(/|$)#';
$deniedFiles = '#(^|/)\.|\.(sql|env|md|lock|bak|log|ini)$|^/uploads/.*\.json$|^/composer[^/]*$|^/(server\.js|package\.json)$#';

if (preg_match($deniedDirs, $path) || preg_match($deniedFiles, $path)) {
    http_response_code(403);
    echo '403 Forbidden';
    return true;
}

// Let the built-in server handle the request (PHP pages, CSS, JS, images).
return false;
