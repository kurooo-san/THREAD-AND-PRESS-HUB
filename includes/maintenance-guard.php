<?php

/**
 * Guard for one-shot maintenance / seeding scripts.
 *
 * These scripts live in the web root, so without a guard ANY visitor can run
 * them — a crawler, a classmate, a panelist clicking around, or a bot on the
 * deployed site. insert_sample_products.php had no guard and duplicated the
 * whole catalogue every single time it was requested.
 *
 * Two levels:
 *   requireCli()      — the script may only run from the command line.
 *   requireAdminOrCli() — command line, or a signed-in admin over HTTP.
 *
 * Include this BEFORE the script does any work.
 */

if (!function_exists('isCliRequest')) {

    /** True when running under the CLI SAPI rather than a web request. */
    function isCliRequest(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }

    /** Refuse anything that is not the command line. */
    function requireCli(string $scriptName = ''): void
    {
        if (isCliRequest()) {
            return;
        }
        maintenanceDeny(
            $scriptName,
            'This is a command-line maintenance script.',
            'Run it from a terminal:  C:\\xampp\\php\\php.exe ' . ($scriptName ?: basename($_SERVER['SCRIPT_NAME'] ?? 'script.php'))
        );
    }

    /** Allow the command line, or an authenticated admin session. */
    function requireAdminOrCli(string $scriptName = ''): void
    {
        if (isCliRequest()) {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (($_SESSION['user_type'] ?? '') === 'admin') {
            return;
        }
        maintenanceDeny(
            $scriptName,
            'This maintenance tool is for administrators only.',
            'Sign in as an admin first, or run it from the command line.'
        );
    }

    /**
     * Send a 403 and stop. Deliberately says nothing about what the script
     * would have done.
     */
    function maintenanceDeny(string $scriptName, string $why, string $how): void
    {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        $name = htmlspecialchars($scriptName ?: basename($_SERVER['SCRIPT_NAME'] ?? ''), ENT_QUOTES);
        echo '<!doctype html><meta charset="utf-8"><title>Not available</title>'
           . '<div style="font:15px/1.6 system-ui,sans-serif;max-width:34rem;margin:12vh auto;padding:0 1.25rem;color:#222">'
           . '<h1 style="font-size:1.25rem;margin:0 0 .5rem">403 — Not available over the web</h1>'
           . '<p style="margin:0 0 .75rem">' . htmlspecialchars($why, ENT_QUOTES) . '</p>'
           . '<p style="margin:0;color:#666;font-size:.92rem">' . htmlspecialchars($how, ENT_QUOTES) . '</p>'
           . ($name !== '' ? '<p style="margin:1.25rem 0 0;color:#999;font-size:.82rem">' . $name . '</p>' : '')
           . '</div>';
        exit;
    }
}
