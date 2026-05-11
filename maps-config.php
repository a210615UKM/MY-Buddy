<?php
// ============================================================
// maps-config.php — Google Maps API Key Provider (PHP 5.5+)
// ============================================================

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

$googleMapsApiKey = getenv('GOOGLE_MAPS_API_KEY');

if (!$googleMapsApiKey || $googleMapsApiKey === 'PASTE_YOUR_GOOGLE_MAPS_API_KEY_HERE') {
  http_response_code(500);
  echo json_encode(array(
    'success' => false,
    'error' => 'Google Maps API key is not configured. Please check your .env file.'
  ));
  exit;
}

echo json_encode(array(
  'success' => true,
  'apiKey' => $googleMapsApiKey
));
