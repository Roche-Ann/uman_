<?php
/**
 * Minimal .env loader for Main LGU SSO secrets — same approach as
 * ipms-integration/env.php, kept separate since this one lives at the
 * project root instead of inside a subfolder.
 *
 * Reads KEY=VALUE lines from .env (untracked) into getenv()/putenv(),
 * then upad_sso_env() reads them back with a default fallback. Real
 * process/webserver environment variables (if set instead of a .env
 * file) take precedence and are never overwritten.
 */

if (!function_exists('upad_sso_load_env_file')) {
    function upad_sso_load_env_file(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $envFile = __DIR__ . '/.env';
        if (!is_file($envFile) || !is_readable($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key   = trim($parts[0]);
            $value = trim($parts[1]);
            $value = trim($value, "\"'");

            if ($key !== '' && getenv($key) === false) {
                putenv("$key=$value");
            }
        }
    }
}

if (!function_exists('upad_sso_env')) {
    function upad_sso_env(string $key, ?string $default = null): ?string
    {
        upad_sso_load_env_file();

        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}
