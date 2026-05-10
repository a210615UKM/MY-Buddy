<?php
// ============================================================
// chat.php — MY Buddy Backend
// ============================================================
// FLOW:
//   1. Receive user input (need, budget, location, mode)
//   2. If mode = 'nearby'  → Google Places Nearby Search (GPS)
//      If mode = 'text'    → Google Places Text Search (area name)
//   3. Pass Places results to Gemini for smart ranking
//   4. If Google Places fails → fall back to local data.php
//   5. Return ranked JSON to script.js
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST requests are allowed']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data.php';

// ── Read input ────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
}

$need      = trim($input['need']      ?? '');
$budget    = floatval($input['budget'] ?? 0);
$location  = trim($input['location']  ?? '');
$latitude  = trim($input['latitude']  ?? '');
$longitude = trim($input['longitude'] ?? '');
$mode      = trim($input['mode']      ?? 'text'); // 'text' or 'nearby'

// ── Validate ──────────────────────────────────────────────────
if ($need === '' || $location === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please fill in what you need and your area.']);
    exit;
}

// ── Step 1: Try Google Places ─────────────────────────────────
$googleMapsApiKey = getenv('GOOGLE_MAPS_API_KEY');
$placesResults    = [];
$placesError      = '';

if ($googleMapsApiKey && $googleMapsApiKey !== 'PASTE_YOUR_GOOGLE_MAPS_API_KEY_HERE') {

    if ($mode === 'nearby' && $latitude !== '' && $longitude !== '') {
        // User clicked "Use My Current Location" → Text Search with location bias
        // We still use Text Search (not Nearby) so the user's actual need
        // drives the results. The GPS coordinates bias results to be nearby.
        $placesResult = searchPlacesByTextWithBias($need, $location, $latitude, $longitude, $googleMapsApiKey);
    } else {
        // User typed an area → Text Search (area name in query)
        $placesResult = searchPlacesByText($need, $location, $googleMapsApiKey);
    }

    if ($placesResult['success']) {
        $placesResults = $placesResult['places'];
    } else {
        $placesError = $placesResult['error'] ?? 'Google Places search failed.';
    }
}

// ── Step 2: Rank with Gemini (if we have results) ─────────────
$geminiApiKey = getenv('GEMINI_API_KEY');

if (!empty($placesResults) && $geminiApiKey && $geminiApiKey !== 'PASTE_YOUR_GEMINI_API_KEY_HERE') {

    $ranked = rankWithGemini($placesResults, $need, $budget, $location, $geminiApiKey);

    if ($ranked['success']) {
        echo json_encode([
            'success'         => true,
            'source'          => 'google_places_gemini',
            'message'         => 'Real places found and ranked by MY Buddy AI.',
            'recommendations' => $ranked['recommendations']
        ]);
        exit;
    }

    // Gemini failed — return raw Places results formatted as cards
    echo json_encode([
        'success'         => true,
        'source'          => 'google_places',
        'message'         => 'Real places found near ' . $location . '.',
        'recommendations' => formatPlacesAsCards($placesResults, $need, $budget)
    ]);
    exit;
}

// ── Step 3: Places found but no Gemini key ────────────────────
if (!empty($placesResults)) {
    echo json_encode([
        'success'         => true,
        'source'          => 'google_places',
        'message'         => 'Real places found near ' . $location . '.',
        'recommendations' => formatPlacesAsCards($placesResults, $need, $budget)
    ]);
    exit;
}

// ── Step 4: No Places results — fall back to local data ───────
if (!$geminiApiKey || $geminiApiKey === 'PASTE_YOUR_GEMINI_API_KEY_HERE') {
    echo json_encode([
        'success'         => true,
        'source'          => 'fallback',
        'message'         => 'Showing local recommendations. (Google Places: ' . ($placesError ?: 'not configured') . ')',
        'recommendations' => rankFallback($recommendations, $need, $budget, $location)
    ]);
    exit;
}

// ── Step 5: No Places results but Gemini available ────────────
// Ask Gemini to generate recommendations from local data
$geminiResult = rankLocalWithGemini($recommendations, $need, $budget, $location, $geminiApiKey);

if ($geminiResult['success']) {
    echo json_encode([
        'success'         => true,
        'source'          => 'gemini_local',
        'message'         => 'Recommendations from MY Buddy AI.' . ($placesError ? ' (Google Places: ' . $placesError . ')' : ''),
        'recommendations' => $geminiResult['recommendations']
    ]);
    exit;
}

// Final fallback
echo json_encode([
    'success'         => true,
    'source'          => 'fallback',
    'message'         => 'Showing local recommendations.',
    'recommendations' => rankFallback($recommendations, $need, $budget, $location)
]);


// ============================================================
// GOOGLE PLACES — TEXT SEARCH (area typed by user)
// The user's exact need becomes the search query, combined
// with the area. This ensures different needs produce
// completely different results even for the same area.
// ============================================================
function searchPlacesByText($need, $location, $apiKey) {
    // Build a specific natural-language query
    // e.g. "food hunting in Bangi" or "weekend activity in Bangi"
    $query = $need . ' in ' . $location;

    $url = 'https://places.googleapis.com/v1/places:searchText';

    $requestData = [
        'textQuery'      => $query,
        'maxResultCount' => 10,
        'languageCode'   => 'en',
        'regionCode'     => 'MY'
    ];

    return executeTextSearch($url, $requestData, $apiKey);
}


// ============================================================
// GOOGLE PLACES — TEXT SEARCH WITH LOCATION BIAS (GPS)
// Same as text search but biases results toward the user's
// GPS coordinates. The user's need still drives the query
// so different needs = different results.
// ============================================================
function searchPlacesByTextWithBias($need, $location, $latitude, $longitude, $apiKey) {
    // Use the need as the query — location bias handles proximity
    $query = $need . ' in ' . $location;

    $url = 'https://places.googleapis.com/v1/places:searchText';

    $requestData = [
        'textQuery'      => $query,
        'maxResultCount' => 10,
        'languageCode'   => 'en',
        'regionCode'     => 'MY',
        'locationBias'   => [
            'circle' => [
                'center' => [
                    'latitude'  => floatval($latitude),
                    'longitude' => floatval($longitude)
                ],
                'radius' => 5000.0
            ]
        ]
    ];

    return executeTextSearch($url, $requestData, $apiKey);
}


// ============================================================
// SHARED: Execute a Google Places Text Search request
// ============================================================
function executeTextSearch($url, $requestData, $apiKey) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Goog-Api-Key: ' . $apiKey,
        'X-Goog-FieldMask: places.displayName,places.formattedAddress,places.rating,places.userRatingCount,places.types,places.priceLevel,places.editorialSummary'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => 'Connection error: ' . $err];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode !== 200 || !isset($data['places'])) {
        $errMsg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
        return ['success' => false, 'error' => 'Places search error: ' . $errMsg];
    }

    return ['success' => true, 'places' => $data['places']];
}


// ============================================================
// RANK PLACES WITH GEMINI
// Sends Google Places results to Gemini for smart ranking
// and personalised descriptions based on user's need + budget
// ============================================================
function rankWithGemini($places, $need, $budget, $location, $apiKey) {

    // Deduplicate by name before sending to Gemini
    $seen  = [];
    $clean = [];
    foreach ($places as $p) {
        $name = strtolower(trim($p['displayName']['text'] ?? ''));
        if ($name === '' || isset($seen[$name])) continue;
        $seen[$name] = true;
        $clean[] = $p;
    }

    // Simplify places data for the prompt
    $simplePlaces = [];
    foreach ($clean as $p) {
        $simplePlaces[] = [
            'name'        => $p['displayName']['text']    ?? 'Unknown',
            'address'     => $p['formattedAddress']       ?? 'No address',
            'rating'      => $p['rating']                 ?? null,
            'reviews'     => $p['userRatingCount']        ?? 0,
            'price_level' => $p['priceLevel']             ?? null,
            'summary'     => $p['editorialSummary']['text'] ?? null,
            'types'       => array_slice($p['types'] ?? [], 0, 3)
        ];
    }

    $systemPrompt =
        "You are MY Buddy, a helpful Malaysia AI buddy. " .
        "You receive a list of real places from Google Maps and the user's SPECIFIC request. " .
        "Your job is to: " .
        "1) FILTER OUT places that do NOT match the user's specific need (e.g. if user wants 'weekend activity', remove restaurants unless they offer activities). " .
        "2) RANK the remaining places from most suitable to least suitable based on relevance to the user's need and budget. " .
        "3) Write a short, friendly description and a clear reason why each place suits the user's EXACT request. " .
        "IMPORTANT: Different user needs MUST produce different rankings. 'food hunting' should prioritise food places. 'weekend activity' should prioritise parks, attractions, entertainment. " .
        "Return ONLY valid JSON with no markdown, no code fences, no extra text.";

    $userPrompt = json_encode([
        'user_request' => [
            'need'      => $need,
            'budget_rm' => $budget,
            'area'      => $location
        ],
        'places_from_google' => $simplePlaces,
        'instructions' => [
            'CRITICAL: The user specifically wants "' . $need . '". Only include places that are relevant to this exact need.',
            'Rank from most suitable to least suitable for the user\'s specific request.',
            'Remove duplicates — each place name must appear only once.',
            'Keep the top 5 results only.',
            'EXCLUDE places that do not match the user\'s need (e.g. do not show restaurants for "weekend activity" unless they are activity-related).',
            'Estimate price based on price_level: PRICE_LEVEL_INEXPENSIVE=RM5-15, PRICE_LEVEL_MODERATE=RM15-40, PRICE_LEVEL_EXPENSIVE=RM40-100, PRICE_LEVEL_VERY_EXPENSIVE=RM100+, null=varies.',
            'Write the suitability_reason explaining why this place matches "' . $need . '" specifically.',
            'If fewer than 5 places match, return only the matching ones.'
        ],
        'required_output_format' => [
            'recommendations' => [
                [
                    'name'               => 'string',
                    'type'               => 'Restaurant / Cafe / Place / Activity / Shop',
                    'description'        => 'short friendly description (1-2 sentences)',
                    'estimated_price'    => 'e.g. RM10 - RM25 per person',
                    'category'           => 'e.g. Local, Cafe, Street Food, Shopping',
                    'suitability_reason' => 'why this suits the user (1-2 sentences)',
                    'rating'             => 'number or null',
                    'feedback'           => 'short note about reviews or atmosphere'
                ]
            ]
        ]
    ]);

    $data = [
        'model'       => 'gemini-2.5-flash',
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt]
        ],
        'temperature' => 0.3,
        'max_tokens'  => 2000
    ];

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/openai/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return ['success' => false, 'error' => 'Gemini connection failed.'];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'Gemini returned HTTP ' . $httpCode];
    }

    $responseData = json_decode($response, true);
    $content      = trim($responseData['choices'][0]['message']['content'] ?? '');

    // Strip markdown code fences if present
    $content = preg_replace('/^```json\s*/i', '', $content);
    $content = preg_replace('/^```\s*/i',     '', $content);
    $content = preg_replace('/\s*```$/i',     '', $content);

    $aiResult = json_decode($content, true);

    if (!$aiResult || !isset($aiResult['recommendations'])) {
        return ['success' => false, 'error' => 'Gemini returned invalid JSON.'];
    }

    return ['success' => true, 'recommendations' => $aiResult['recommendations']];
}


// ============================================================
// RANK LOCAL DATA WITH GEMINI
// Used when Google Places is unavailable
// ============================================================
function rankLocalWithGemini($items, $need, $budget, $location, $apiKey) {

    $systemPrompt =
        "You are MY Buddy, a helpful Malaysia AI buddy. " .
        "Rank the provided local recommendations from most suitable to least suitable " .
        "based on the user's SPECIFIC need, budget, and area. " .
        "IMPORTANT: Only include items that are relevant to the user's exact request. " .
        "Different requests must produce different results. " .
        "Return ONLY valid JSON with no markdown, no code fences, no extra text.";

    $userPrompt = json_encode([
        'user_request' => [
            'need'      => $need,
            'budget_rm' => $budget,
            'area'      => $location
        ],
        'available_recommendations' => $items,
        'instructions' => [
            'The user specifically wants: "' . $need . '". Only include items relevant to this.',
            'Rank from most suitable to least suitable.',
            'Keep the top 5 only.',
            'Each item must appear only once.',
            'Write a suitability_reason explaining why this matches "' . $need . '" specifically.',
            'EXCLUDE items that do not match the user need.'
        ],
        'required_output_format' => [
            'recommendations' => [
                [
                    'name'               => 'string',
                    'type'               => 'string',
                    'description'        => 'string',
                    'estimated_price'    => 'string',
                    'category'           => 'string',
                    'suitability_reason' => 'string',
                    'rating'             => 'number',
                    'feedback'           => 'string'
                ]
            ]
        ]
    ]);

    $data = [
        'model'       => 'gemini-2.5-flash',
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt]
        ],
        'temperature' => 0.3,
        'max_tokens'  => 1500
    ];

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/openai/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return ['success' => false];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['success' => false];
    }

    $responseData = json_decode($response, true);
    $content      = trim($responseData['choices'][0]['message']['content'] ?? '');

    $content = preg_replace('/^```json\s*/i', '', $content);
    $content = preg_replace('/^```\s*/i',     '', $content);
    $content = preg_replace('/\s*```$/i',     '', $content);

    $aiResult = json_decode($content, true);

    if (!$aiResult || !isset($aiResult['recommendations'])) {
        return ['success' => false];
    }

    return ['success' => true, 'recommendations' => $aiResult['recommendations']];
}


// ============================================================
// FORMAT PLACES AS CARDS (no Gemini)
// Simple formatting when Gemini is unavailable
// ============================================================
function formatPlacesAsCards($places, $need, $budget) {
    $seen    = [];
    $results = [];

    foreach ($places as $place) {
        $name = trim($place['displayName']['text'] ?? '');
        if ($name === '') continue;

        $nameKey = strtolower($name);
        if (isset($seen[$nameKey])) continue;
        $seen[$nameKey] = true;

        $address     = $place['formattedAddress']         ?? 'No address available';
        $rating      = $place['rating']                   ?? null;
        $reviewCount = $place['userRatingCount']          ?? 0;
        $priceLevel  = $place['priceLevel']               ?? null;
        $summary     = $place['editorialSummary']['text'] ?? null;

        // Estimate price from price level
        $priceMap = [
            'PRICE_LEVEL_FREE'          => 'Free',
            'PRICE_LEVEL_INEXPENSIVE'   => 'RM5 - RM15',
            'PRICE_LEVEL_MODERATE'      => 'RM15 - RM40',
            'PRICE_LEVEL_EXPENSIVE'     => 'RM40 - RM100',
            'PRICE_LEVEL_VERY_EXPENSIVE'=> 'RM100+',
        ];
        $estimatedPrice = $priceMap[$priceLevel] ?? 'Price varies';

        $results[] = [
            'name'               => $name,
            'type'               => 'Place',
            'description'        => $summary ?? $address,
            'estimated_price'    => $estimatedPrice,
            'category'           => 'Google Places',
            'suitability_reason' => 'Found near ' . ($address ?: 'your area') . ' on Google Maps.',
            'rating'             => $rating,
            'feedback'           => $reviewCount > 0 ? $reviewCount . ' Google reviews' : 'No review count available.'
        ];

        if (count($results) >= 5) break;
    }

    return $results;
}


// ============================================================
// FALLBACK RANKING (local data.php only)
// ============================================================
function rankFallback($items, $need, $budget, $location) {
    $need     = strtolower($need);
    $location = strtolower($location);

    foreach ($items as &$item) {
        $score = 0;

        $itemText = strtolower(
            $item['name']        . ' ' .
            $item['type']        . ' ' .
            $item['description'] . ' ' .
            implode(' ', $item['mood_tags'] ?? [])
        );

        // Need keyword match
        if (strpos($itemText, $need) !== false) {
            $score += 4;
        }
        foreach (explode(' ', $need) as $word) {
            if ($word !== '' && strpos($itemText, $word) !== false) {
                $score += 1;
            }
        }

        // Budget match
        if ($budget >= $item['price_min'] && $budget <= $item['price_max']) {
            $score += 4;
        } elseif ($budget >= $item['price_min']) {
            $score += 2;
        }

        // Location match
        $itemArea = strtolower($item['area'] ?? '');
        if (
            strpos($itemArea, $location) !== false ||
            strpos($location, $itemArea) !== false ||
            $itemArea === 'malaysia'
        ) {
            $score += 3;
        }

        // Rating as tiebreaker
        $score += floatval($item['rating'] ?? 0);

        $item['score']           = $score;
        $item['estimated_price'] = 'RM' . $item['price_min'] . ' - RM' . $item['price_max'];
        $item['suitability_reason'] = 'Matched based on your need, budget, and area.';
    }

    usort($items, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    return array_slice($items, 0, 5);
}


// End of chat.php
