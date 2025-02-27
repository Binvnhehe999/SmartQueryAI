<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Only accept POST requests—no silly GETs!
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Only POST requests are allowed."]);
    exit;
}

// Read and decode the input JSON
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!$data) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Invalid JSON input."]);
    exit;
}

// Check for valid API key
if (!isset($data['api_key']) || $data['api_key'] !== 'SmartQueryAI') {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Unauthorized. Invalid API key."]);
    exit;
}

// Ensure a prompt or question is provided
if (!isset($data['prompt']) && !isset($data['question'])) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "No prompt/question provided."]);
    exit;
}

// Get the question (from 'prompt' or 'question')
$question = isset($data['prompt']) ? $data['prompt'] : $data['question'];

// Prepare the payload for message.php, forwarding useMemoryBasic if available
$payload = ["question" => $question];
if (isset($data['useMemoryBasic'])) {
    $payload['useMemoryBasic'] = $data['useMemoryBasic'];
}
$newPayload = json_encode($payload);

// Determine the URL for message.php relative to this file
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$messageUrl = $protocol . "://".$_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI'])."/message.php";

// Call message.php using cURL
$ch = curl_init($messageUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $newPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "message.php returned HTTP code " . $httpCode]);
    exit;
}

// Clean up the response: remove any content before the first '{'
$response = trim($response);
$startPos = strpos($response, '{');
if ($startPos !== false) {
    $response = substr($response, $startPos);
}

// Attempt to decode the JSON response
$decodedResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedResponse)) {
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Invalid response from message.php: " . $response]);
    exit;
}

// Forward the response directly if it has the expected structure
if (isset($decodedResponse['status']) && isset($decodedResponse['message'])) {
    header('Content-Type: application/json');
    echo json_encode($decodedResponse);
} else {
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Invalid response structure from message.php"]);
}
exit;
?>
