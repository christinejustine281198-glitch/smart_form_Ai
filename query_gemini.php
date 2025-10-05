<?php
// query_gemini.php

header('Content-Type: application/json');

// Config - put your Gemini API key here
$apiKey = "AIzaSyDL13EAPosZwLsysabzwssoag5i6Q3O2RM";
if ($apiKey === "your_gemini_api_key_here") {
    echo json_encode(['error' => 'Set your Gemini API key in query_gemini.php']); exit;
}

// Get POST JSON
$body = json_decode(file_get_contents('php://input'), true);
$query = trim($body['query'] ?? '');
$form_id = !empty($body['form_id']) ? intval($body['form_id']) : null;

if (!$query) { echo json_encode(['error' => 'Empty query']); exit; }

try {
    $db = new PDO('sqlite:' . __DIR__ . '/forms.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch recent submissions (limit to last 30 to keep prompt size reasonable)
    if ($form_id) {
        $stmt = $db->prepare("SELECT responses, submitted_at FROM submissions WHERE form_id = ? ORDER BY submitted_at DESC LIMIT 30");
        $stmt->execute([$form_id]);
    } else {
        $stmt = $db->prepare("SELECT responses, submitted_at FROM submissions ORDER BY submitted_at DESC LIMIT 30");
        $stmt->execute();
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build compact context: an array of parsed submissions (decoded JSON)
    $examples = [];
    foreach ($rows as $r) {
        $decoded = json_decode($r['responses'], true);
        if (is_array($decoded)) $examples[] = $decoded;
    }

    // Prepare prompt: include a short sample (up to first 20 entries)
    $context = json_encode(array_slice($examples, 0, 20), JSON_UNESCAPED_UNICODE);
    if (!$context) $context = '[]';

    $systemPrompt = "You are a helpful data analyst. Given the following recent submissions (JSON array):\n$context\n\nAnswer the question precisely. If the data cannot answer fully, say what is missing.";

    $fullPrompt = $systemPrompt . "\n\nQuestion: " . $query;

    // Call Gemini generateContent endpoint
    $payload = [
        "contents" => [
            ["parts" => [["text" => $fullPrompt]]]
        ]
    ];

    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo json_encode(['error' => 'Curl error: ' . $err]); exit;
    }

    $respData = json_decode($resp, true);
    $answer = '';

    // Robust extraction of model text
    if (isset($respData['candidates'][0]['content']['parts'][0]['text'])) {
        $answer = $respData['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($respData['output'][0]['content'][0]['text'])) {
        $answer = $respData['output'][0]['content'][0]['text'];
    } else {
        // fallback: full response
        $answer = json_encode($respData);
    }

    echo json_encode(['answer' => $answer]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
