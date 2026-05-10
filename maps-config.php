<?php
// ============================================================
// maps-config.php — Google Maps API Key Provider
// Location: C:\xampp\htdocs\ChatGPT\maps-config.php
// ============================================================
// WHAT THIS FILE DOES:
//   1. Loads the Google Maps API key from .env using config.php
//   2. Checks whether the key exists
//   3. Sends the key to script.js as JSON
// ============================================================

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

$googleMapsApiKey = getenv('GOOGLE_MAPS_API_KEY');

if (!$googleMapsApiKey || $googleMapsApiKey === 'PASTE_YOUR_GOOGLE_MAPS_API_KEY_HERE') {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Google Maps API key is not configured. Please check your .env file.'
  ]);
  exit;
}

echo json_encode([
  'success' => true,
  'apiKey' => $googleMapsApiKey
]);