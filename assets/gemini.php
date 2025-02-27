<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$geminiApiKey = "AIzaSyBNLYLNo7gXXfy42woW4dCmX175AXwFJlw"; // Insert your actual API key here

// Load internal guidelines from custom.json
$customFile = __DIR__ . '/custom.json';
$internalGuidelines = "";
if (file_exists($customFile)) {
    $customGuidelines = json_decode(file_get_contents($customFile), true);
}
$internalMessage = "";
if ($customGuidelines) {
    // Build a message that is internal only; instruct the AI not to repeat it.
    $internalMessage .= "Internal AI Guidelines (do not include this text in your final answer):\n";
    $internalMessage .= "Characteristics: " . $customGuidelines["What characteristics should AI have"] . "\n";
    $internalMessage .= "Constraints: " . $customGuidelines["Constraints that AI must follow (rule)"] . "\n";
    $internalMessage .= "Text Processing: " . $customGuidelines["Text processing documents"] . "\n\n";
}

// Get the user query from the request
$data = json_decode(file_get_contents("php://input"), true);
$userQuery = $data['prompt'] ?? '';
$modelName = $data['modelName'] ?? 'gemini-2.0-flash';

if (!$userQuery) {
    echo json_encode(["status" => "error", "message" => "No query provided"]);
    exit();
}

// Combine the internal guidelines with the user's query.
// The instruction tells the AI to base its answer on these guidelines but not to output them.
$combinedText = $internalMessage .
    "User Query: " . $userQuery . "\n\n" .
    "Based on the above internal guidelines, please provide an answer without repeating or referencing these internal instructions.";

// Construct the payload for Gemini API using the combined text
$url = "https://generativelanguage.googleapis.com/v1beta/models/" . $modelName . ":generateContent?key=" . $geminiApiKey;

$headers = [
    'Content-Type: application/json',
];

$payload = [
    "contents" => [
         [
            "parts" => [
                [
                    "text" => $combinedText
                ]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode === 200) {
    $responseData = json_decode($response, true);
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
       echo json_encode($responseData['candidates'][0]['content']['parts'][0]['text'], JSON_UNESCAPED_UNICODE);
    } else {
         echo json_encode(["status" => "error", "message" => "Gemini API returned an unexpected structure. Response: " . $response]);
    }
} else {
     echo json_encode(["status" => "error", "message" => "Error calling Gemini API. Status code: " . $httpcode . ", Response: " . $response]);
}
?>
