<?php

/**
 * config.php
 * MY Buddy – Hyperlocal Malaysia AI Buddy
 *
 * Loads environment variables from .env and exposes them via:
 *   - getenv('GEMINI_API_KEY')
 *   - $_ENV['GEMINI_API_KEY']
 *   - the constant GEMINI_API_KEY
 *
 * Usage in any other PHP file:
 *   require_once __DIR__ . '/config.php';
 *   $key = GEMINI_API_KEY;
 */

function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception(
            '.env file not found. Please create ' . $path . ' and add GEMINI_API_KEY.'
        );
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip blank lines and comments
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        // Skip lines that have no = sign
        if (strpos($line, '=') === false) {
            continue;
        }

        // Split on the FIRST = only so values can contain = (e.g. base64 keys)
        $parts = explode('=', $line, 2);
        $name  = trim($parts[0]);
        $value = trim($parts[1]);

        // Remove surrounding quotes if present  ("value" or 'value')
        if (preg_match('/^(["\']).*\1$/', $value)) {
            $value = substr($value, 1, -1);
        }

        // Make the variable available through all three standard PHP methods
        putenv("$name=$value");
        $_ENV[$name]    = $value;
        $_SERVER[$name] = $value;
    }
}

// ── Load .env ────────────────────────────────────────────────────────────────
loadEnv(__DIR__ . '/.env');

// ── Expose the API key as a constant ─────────────────────────────────────────
$_geminiKey = getenv('GEMINI_API_KEY');

if (empty($_geminiKey) || $_geminiKey === 'PASTE_YOUR_GEMINI_API_KEY_HERE') {
    throw new Exception(
        'GEMINI_API_KEY is not set. Open .env and replace the placeholder with your real key.'
    );
}

define('GEMINI_API_KEY', $_geminiKey);
unset($_geminiKey); // don't leave the key floating in a variable
