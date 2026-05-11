<?php
// ============================================================
// chat.php — MY Buddy Backend (PHP 5.5+ compatible)
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
    echo json_encode(array('success' => false, 'error' => 'Only POST requests are allowed'));
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data.php';

// ── Helper: PHP 5.5 compatible null coalescing ────────────────
function val($arr, $key, $default = '') {
    return isset($arr[$key]) ? $arr[$key] : $default;
}

// ── Helper: nested array access ───────────────────────────────
function nested($arr, $key1, $key2, $default = null) {
    return isset($arr[$key1][$key2]) ? $arr[$key1][$key2] : $default;
}

// ── Read input ────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'Invalid JSON input'));
    exit;
}

$need      = trim(val($input, 'need'));
$budget    = floatval(val($input, 'budget', 0));
$location  = trim(val($input, 'location'));
$latitude  = trim(val($input, 'latitude'));
$longitude = trim(val($input, 'longitude'));
$mode      = trim(val($input, 'mode', 'text'));

// Activity planning fields
$planDate  = trim(val($input, 'plan_date'));
$planTime  = trim(val($input, 'plan_time'));
$planGroup = trim(val($input, 'plan_group'));

// ── Validate ──────────────────────────────────────────────────
if ($need === '' || $location === '') {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'Please fill in what you need and your area.'));
    exit;
}

// ── Step 1: Try Google Places ─────────────────────────────────
$googleMapsApiKey = getenv('GOOGLE_MAPS_API_KEY');
$placesResults    = array();
$placesError      = '';

if ($googleMapsApiKey && $googleMapsApiKey !== 'PASTE_YOUR_GOOGLE_MAPS_API_KEY_HERE') {

    if ($mode === 'nearby' && $latitude !== '' && $longitude !== '') {
        $placesResult = searchPlacesByTextWithBias($need, $location, $latitude, $longitude, $googleMapsApiKey);
    } else {
        $placesResult = searchPlacesByText($need, $location, $googleMapsApiKey);
    }

    if ($placesResult['success']) {
        $placesResults = $placesResult['places'];
    } else {
        $placesError = isset($placesResult['error']) ? $placesResult['error'] : 'Google Places search failed.';
    }
}

// ── Step 2: Rank with Gemini (if we have results) ─────────────
$geminiApiKey = getenv('GEMINI_API_KEY');

if (!empty($placesResults) && $geminiApiKey && $geminiApiKey !== 'PASTE_YOUR_GEMINI_API_KEY_HERE') {

    $ranked = rankWithGemini($placesResults, $need, $budget, $location, $geminiApiKey);

    if ($ranked['success']) {
        echo json_encode(array(
            'success'         => true,
            'source'          => 'google_places_gemini',
            'message'         => 'Real places found and ranked by MY Buddy AI.',
            'recommendations' => $ranked['recommendations']
        ));
        exit;
    }

    echo json_encode(array(
        'success'         => true,
        'source'          => 'google_places',
        'message'         => 'Real places found near ' . $location . '.',
        'recommendations' => formatPlacesAsCards($placesResults, $need, $budget)
    ));
    exit;
}

// ── Step 3: Places found but no Gemini key ────────────────────
if (!empty($placesResults)) {
    echo json_encode(array(
        'success'         => true,
        'source'          => 'google_places',
        'message'         => 'Real places found near ' . $location . '.',
        'recommendations' => formatPlacesAsCards($placesResults, $need, $budget)
    ));
    exit;
}

// ── Step 4: No Places results — fall back to local data ───────
if (!$geminiApiKey || $geminiApiKey === 'PASTE_YOUR_GEMINI_API_KEY_HERE') {
    echo json_encode(array(
        'success'         => true,
        'source'          => 'fallback',
        'message'         => 'Showing local recommendations. (Google Places: ' . ($placesError ? $placesError : 'not configured') . ')',
        'recommendations' => rankFallback($recommendations, $need, $budget, $location)
    ));
    exit;
}

// ── Step 5: No Places results but Gemini available ────────────
$geminiResult = rankLocalWithGemini($recommendations, $need, $budget, $location, $geminiApiKey);

if ($geminiResult['success']) {
    echo json_encode(array(
        'success'         => true,
        'source'          => 'gemini_local',
        'message'         => 'Recommendations from MY Buddy AI.' . ($placesError ? ' (Google Places: ' . $placesError . ')' : ''),
        'recommendations' => $geminiResult['recommendations']
    ));
    exit;
}

// Final fallback
echo json_encode(array(
    'success'         => true,
    'source'          => 'fallback',
    'message'         => 'Showing local recommendations.',
    'recommendations' => rankFallback($recommendations, $need, $budget, $location)
));


// ============================================================
// GOOGLE PLACES — TEXT SEARCH (area typed by user)
// ============================================================
function searchPlacesByText($need, $location, $apiKey) {
    $query = $need . ' in ' . $location;
    $url = 'https://places.googleapis.com/v1/places:searchText';

    $requestData = array(
        'textQuery'      => $query,
        'maxResultCount' => 10,
        'languageCode'   => 'en',
        'regionCode'     => 'MY'
    );

    return executeTextSearch($url, $requestData, $apiKey);
}


// ============================================================
// GOOGLE PLACES — TEXT SEARCH WITH LOCATION BIAS (GPS)
// ============================================================
function searchPlacesByTextWithBias($need, $location, $latitude, $longitude, $apiKey) {
    $query = $need . ' in ' . $location;
    $url = 'https://places.googleapis.com/v1/places:searchText';

    $requestData = array(
        'textQuery'      => $query,
        'maxResultCount' => 10,
        'languageCode'   => 'en',
        'regionCode'     => 'MY',
        'locationBias'   => array(
            'circle' => array(
                'center' => array(
                    'latitude'  => floatval($latitude),
                    'longitude' => floatval($longitude)
                ),
                'radius' => 5000.0
            )
        )
    );

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
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'X-Goog-Api-Key: ' . $apiKey,
        'X-Goog-FieldMask: places.displayName,places.formattedAddress,places.rating,places.userRatingCount,places.types,places.priceLevel,places.editorialSummary,places.photos'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // Fix SSL on servers without updated CA bundle
    $caFile = __DIR__ . '/cacert.pem';
    if (file_exists($caFile)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caFile);
    }

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        return array('success' => false, 'error' => 'Connection error: ' . $err);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode !== 200 || !isset($data['places'])) {
        $errMsg = isset($data['error']['message']) ? $data['error']['message'] : ('HTTP ' . $httpCode);
        return array('success' => false, 'error' => 'Places search error: ' . $errMsg);
    }

    return array('success' => true, 'places' => $data['places']);
}


// ============================================================
// RANK PLACES WITH GEMINI
// ============================================================
function rankWithGemini($places, $need, $budget, $location, $apiKey) {
    global $planDate, $planTime, $planGroup;

    // Deduplicate by name
    $seen  = array();
    $clean = array();
    foreach ($places as $p) {
        $name = strtolower(trim(nested($p, 'displayName', 'text', '')));
        if ($name === '' || isset($seen[$name])) continue;
        $seen[$name] = true;
        $clean[] = $p;
    }

    // Simplify places data for the prompt
    $simplePlaces = array();
    foreach ($clean as $p) {
        $types = isset($p['types']) ? array_slice($p['types'], 0, 3) : array();
        $simplePlaces[] = array(
            'name'        => nested($p, 'displayName', 'text', 'Unknown'),
            'address'     => val($p, 'formattedAddress', 'No address'),
            'rating'      => val($p, 'rating', null),
            'reviews'     => val($p, 'userRatingCount', 0),
            'price_level' => val($p, 'priceLevel', null),
            'summary'     => nested($p, 'editorialSummary', 'text', null),
            'types'       => $types
        );
    }

    $systemPrompt =
        "You are MY Buddy, a helpful Malaysia AI buddy. " .
        "You receive a list of real places from Google Maps and the user's SPECIFIC request. " .
        "Your job is to: " .
        "1) FILTER OUT places that do NOT match the user's specific need. " .
        "2) RANK the remaining places from most suitable to least suitable based on relevance to the user's need and budget. " .
        "3) Write a short, friendly description and a clear reason why each place suits the user's EXACT request. " .
        "IMPORTANT: Different user needs MUST produce different rankings. " .
        "Return ONLY valid JSON with no markdown, no code fences, no extra text.";

    $userPrompt = json_encode(array(
        'user_request' => array(
            'need'      => $need,
            'budget_rm' => $budget,
            'area'      => $location,
            'planned_date' => $planDate ? $planDate : 'not specified',
            'preferred_time' => $planTime ? $planTime : 'anytime',
            'group_type' => $planGroup ? $planGroup : 'not specified'
        ),
        'places_from_google' => $simplePlaces,
        'instructions' => array(
            'CRITICAL: The user specifically wants "' . $need . '". Only include places that are relevant to this exact need.',
            'Rank from most suitable to least suitable for the user\'s specific request.',
            'Remove duplicates — each place name must appear only once.',
            'Keep the top 5 results only.',
            'EXCLUDE places that do not match the user\'s need.',
            'Consider the time preference: ' . ($planTime ? $planTime : 'anytime') . '. Suggest places appropriate for that time.',
            'Consider the group type: ' . ($planGroup ? $planGroup : 'anyone') . '. Prioritize places suitable for this group.',
            'Estimate price based on price_level: PRICE_LEVEL_INEXPENSIVE=RM5-15, PRICE_LEVEL_MODERATE=RM15-40, PRICE_LEVEL_EXPENSIVE=RM40-100, PRICE_LEVEL_VERY_EXPENSIVE=RM100+, null=varies.',
            'Write the suitability_reason explaining why this place matches "' . $need . '" specifically.',
            'If fewer than 5 places match, return only the matching ones.'
        ),
        'required_output_format' => array(
            'recommendations' => array(
                array(
                    'name'               => 'string',
                    'type'               => 'Restaurant / Cafe / Place / Activity / Shop',
                    'description'        => 'short friendly description (1-2 sentences)',
                    'estimated_price'    => 'e.g. RM10 - RM25 per person',
                    'category'           => 'e.g. Local, Cafe, Street Food, Shopping',
                    'suitability_reason' => 'why this suits the user (1-2 sentences)',
                    'rating'             => 'number or null',
                    'feedback'           => 'short note about reviews or atmosphere'
                )
            )
        )
    ));

    $data = array(
        'model'       => 'gemini-2.5-flash',
        'messages'    => array(
            array('role' => 'system', 'content' => $systemPrompt),
            array('role' => 'user',   'content' => $userPrompt)
        ),
        'temperature' => 0.3,
        'max_tokens'  => 2000
    );

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/openai/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    // Fix SSL
    $caFile = __DIR__ . '/cacert.pem';
    if (file_exists($caFile)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caFile);
    }

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return array('success' => false, 'error' => 'Gemini connection failed.');
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return array('success' => false, 'error' => 'Gemini returned HTTP ' . $httpCode);
    }

    $responseData = json_decode($response, true);
    $content      = isset($responseData['choices'][0]['message']['content'])
                  ? trim($responseData['choices'][0]['message']['content'])
                  : '';

    // Strip markdown code fences if present
    $content = preg_replace('/^```json\s*/i', '', $content);
    $content = preg_replace('/^```\s*/i',     '', $content);
    $content = preg_replace('/\s*```$/i',     '', $content);

    $aiResult = json_decode($content, true);

    if (!$aiResult || !isset($aiResult['recommendations'])) {
        return array('success' => false, 'error' => 'Gemini returned invalid JSON.');
    }

    // Attach photo URLs from original places data
    $googleMapsApiKey = getenv('GOOGLE_MAPS_API_KEY');
    $recs = $aiResult['recommendations'];
    foreach ($recs as &$rec) {
        $recName = strtolower(trim(isset($rec['name']) ? $rec['name'] : ''));
        foreach ($clean as $p) {
            $pName = strtolower(trim(nested($p, 'displayName', 'text', '')));
            if ($pName !== '' && $recName !== '' && strpos($pName, $recName) !== false || strpos($recName, $pName) !== false) {
                if (isset($p['photos']) && is_array($p['photos']) && count($p['photos']) > 0) {
                    $photoRef = isset($p['photos'][0]['name']) ? $p['photos'][0]['name'] : '';
                    if ($photoRef && $googleMapsApiKey) {
                        $rec['photo_url'] = 'https://places.googleapis.com/v1/' . $photoRef . '/media?maxWidthPx=400&key=' . $googleMapsApiKey;
                    }
                }
                break;
            }
        }
    }

    return array('success' => true, 'recommendations' => $recs);
}


// ============================================================
// RANK LOCAL DATA WITH GEMINI
// ============================================================
function rankLocalWithGemini($items, $need, $budget, $location, $apiKey) {
    global $planDate, $planTime, $planGroup;

    $systemPrompt =
        "You are MY Buddy, a helpful Malaysia AI buddy. " .
        "Rank the provided local recommendations from most suitable to least suitable " .
        "based on the user's SPECIFIC need, budget, and area. " .
        "IMPORTANT: Only include items that are relevant to the user's exact request. " .
        "Different requests must produce different results. " .
        "Return ONLY valid JSON with no markdown, no code fences, no extra text.";

    $userPrompt = json_encode(array(
        'user_request' => array(
            'need'      => $need,
            'budget_rm' => $budget,
            'area'      => $location,
            'planned_date' => $planDate ? $planDate : 'not specified',
            'preferred_time' => $planTime ? $planTime : 'anytime',
            'group_type' => $planGroup ? $planGroup : 'not specified'
        ),
        'available_recommendations' => $items,
        'instructions' => array(
            'The user specifically wants: "' . $need . '". Only include items relevant to this.',
            'Rank from most suitable to least suitable.',
            'Keep the top 5 only.',
            'Each item must appear only once.',
            'Write a suitability_reason explaining why this matches "' . $need . '" specifically.',
            'EXCLUDE items that do not match the user need.'
        ),
        'required_output_format' => array(
            'recommendations' => array(
                array(
                    'name'               => 'string',
                    'type'               => 'string',
                    'description'        => 'string',
                    'estimated_price'    => 'string',
                    'category'           => 'string',
                    'suitability_reason' => 'string',
                    'rating'             => 'number',
                    'feedback'           => 'string'
                )
            )
        )
    ));

    $data = array(
        'model'       => 'gemini-2.5-flash',
        'messages'    => array(
            array('role' => 'system', 'content' => $systemPrompt),
            array('role' => 'user',   'content' => $userPrompt)
        ),
        'temperature' => 0.3,
        'max_tokens'  => 1500
    );

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/openai/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    // Fix SSL
    $caFile = __DIR__ . '/cacert.pem';
    if (file_exists($caFile)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caFile);
    }

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return array('success' => false);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return array('success' => false);
    }

    $responseData = json_decode($response, true);
    $content      = isset($responseData['choices'][0]['message']['content'])
                  ? trim($responseData['choices'][0]['message']['content'])
                  : '';

    $content = preg_replace('/^```json\s*/i', '', $content);
    $content = preg_replace('/^```\s*/i',     '', $content);
    $content = preg_replace('/\s*```$/i',     '', $content);

    $aiResult = json_decode($content, true);

    if (!$aiResult || !isset($aiResult['recommendations'])) {
        return array('success' => false);
    }

    return array('success' => true, 'recommendations' => $aiResult['recommendations']);
}


// ============================================================
// FORMAT PLACES AS CARDS (no Gemini)
// ============================================================
function formatPlacesAsCards($places, $need, $budget) {
    $seen    = array();
    $results = array();

    foreach ($places as $place) {
        $name = trim(nested($place, 'displayName', 'text', ''));
        if ($name === '') continue;

        $nameKey = strtolower($name);
        if (isset($seen[$nameKey])) continue;
        $seen[$nameKey] = true;

        $address     = val($place, 'formattedAddress', 'No address available');
        $rating      = val($place, 'rating', null);
        $reviewCount = val($place, 'userRatingCount', 0);
        $priceLevel  = val($place, 'priceLevel', null);
        $summary     = nested($place, 'editorialSummary', 'text', null);

        // Get photo URL
        $photoUrl = '';
        $googleMapsApiKey = getenv('GOOGLE_MAPS_API_KEY');
        if (isset($place['photos']) && is_array($place['photos']) && count($place['photos']) > 0) {
            $photoRef = isset($place['photos'][0]['name']) ? $place['photos'][0]['name'] : '';
            if ($photoRef && $googleMapsApiKey) {
                $photoUrl = 'https://places.googleapis.com/v1/' . $photoRef . '/media?maxWidthPx=400&key=' . $googleMapsApiKey;
            }
        }

        // Estimate price from price level
        $priceMap = array(
            'PRICE_LEVEL_FREE'           => 'Free',
            'PRICE_LEVEL_INEXPENSIVE'    => 'RM5 - RM15',
            'PRICE_LEVEL_MODERATE'       => 'RM15 - RM40',
            'PRICE_LEVEL_EXPENSIVE'      => 'RM40 - RM100',
            'PRICE_LEVEL_VERY_EXPENSIVE' => 'RM100+',
        );
        $estimatedPrice = isset($priceMap[$priceLevel]) ? $priceMap[$priceLevel] : 'Price varies';

        $results[] = array(
            'name'               => $name,
            'type'               => 'Place',
            'description'        => $summary ? $summary : $address,
            'estimated_price'    => $estimatedPrice,
            'category'           => 'Google Places',
            'suitability_reason' => 'Found near ' . ($address ? $address : 'your area') . ' on Google Maps.',
            'rating'             => $rating,
            'feedback'           => $reviewCount > 0 ? $reviewCount . ' Google reviews' : 'No review count available.',
            'photo_url'          => $photoUrl
        );

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

        $moodTags = isset($item['mood_tags']) ? $item['mood_tags'] : array();
        $itemText = strtolower(
            $item['name']        . ' ' .
            $item['type']        . ' ' .
            $item['description'] . ' ' .
            implode(' ', $moodTags)
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
        $itemArea = strtolower(isset($item['area']) ? $item['area'] : '');
        if (
            strpos($itemArea, $location) !== false ||
            strpos($location, $itemArea) !== false ||
            $itemArea === 'malaysia'
        ) {
            $score += 3;
        }

        // Rating as tiebreaker
        $rating = isset($item['rating']) ? floatval($item['rating']) : 0;
        $score += $rating;

        $item['score']              = $score;
        $item['estimated_price']    = 'RM' . $item['price_min'] . ' - RM' . $item['price_max'];
        $item['suitability_reason'] = 'Matched based on your need, budget, and area.';
    }

    usort($items, function ($a, $b) {
        if ($b['score'] == $a['score']) return 0;
        return ($b['score'] > $a['score']) ? 1 : -1;
    });

    return array_slice($items, 0, 5);
}

// End of chat.php
