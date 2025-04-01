<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$geminiApiKey = "This is the location of the Gemini API"; // Hardcoded API Key. ADD YOUR API KEY HERE

// Get the prompt and model name from the request
$data = json_decode(file_get_contents("php://input"), true);
$prompt = $data['prompt'] ?? '';
$modelName = $data['modelName'] ?? 'gemini-2.0-flash'; // Default model if not provided

if(!$prompt) {
    echo json_encode(["status" => "error", "message" => "No prompt provided"]);
    exit();
}

// Construct the URL with the dynamic model name
$url = "https://generativelanguage.googleapis.com/v1beta/models/" . $modelName . ":generateContent?key=" . $geminiApiKey;

$headers = [
    'Content-Type: application/json',
];

$payload = [
    "contents" => [
         [
            "parts" => [
                [
                    "text" => $prompt
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
    if(isset($responseData['candidates'][0]['content']['parts'][0]['text'])){
       echo json_encode($responseData['candidates'][0]['content']['parts'][0]['text'], JSON_UNESCAPED_UNICODE);
    } else {
         echo json_encode(["status" => "error", "message" => "Gemini API returned an unexpected structure. Response: " . $response]); // Include the response for debugging
    }
} else {
     echo json_encode(["status" => "error", "message" => "Error calling Gemini API. Status code: " . $httpcode . ", Response: " . $response]); // Include the response for debugging
}
?>