<?php
// Test file — DELETE after confirming everything works!
// Access: http://lrgs.ftsm.ukm.my/users/a210615/ChatGPT/test-server.php

header('Content-Type: application/json');

$checks = array();

// 1. PHP is running
$checks['php_works'] = true;
$checks['php_version'] = phpversion();

// 2. curl extension
$checks['curl_enabled'] = extension_loaded('curl');

// 3. .env file exists and readable
$checks['env_file_exists'] = file_exists(__DIR__ . '/.env');

// 4. cacert.pem exists
$checks['cacert_pem_exists'] = file_exists(__DIR__ . '/cacert.pem');

// 5. Test outbound HTTPS with cacert.pem fix
if ($checks['curl_enabled']) {
    $ch = curl_init('https://maps.googleapis.com');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true);

    $caFile = __DIR__ . '/cacert.pem';
    if (file_exists($caFile)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caFile);
    }

    curl_exec($ch);
    $checks['outbound_https'] = (curl_errno($ch) === 0);
    $checks['outbound_error'] = curl_error($ch) ? curl_error($ch) : null;
    curl_close($ch);
} else {
    $checks['outbound_https'] = false;
    $checks['outbound_error'] = 'curl not available';
}

// 6. Test Gemini API connectivity
if ($checks['curl_enabled'] && $checks['outbound_https']) {
    $ch = curl_init('https://generativelanguage.googleapis.com');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true);

    $caFile = __DIR__ . '/cacert.pem';
    if (file_exists($caFile)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caFile);
    }

    curl_exec($ch);
    $checks['gemini_reachable'] = (curl_errno($ch) === 0);
    $checks['gemini_error'] = curl_error($ch) ? curl_error($ch) : null;
    curl_close($ch);
}

echo json_encode($checks, JSON_PRETTY_PRINT);
