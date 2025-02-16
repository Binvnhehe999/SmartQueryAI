<?php
/**
 * endpoint.php
 *
 * API endpoint of SmartQueryAI – directly handles client requests via JSON
 * and returns results (similar to client using message.php).
 *
 * Features:
 *  - API key authentication (must match "SmartQueryAI")
 *  - Rate limiting: 10 requests/second per IP
 *  - Token limit check (using MAX_INPUT_CHARACTERS ~ 40,000 characters)
 *  - Handles chatbot features (fetches data from DB, calls Gemini if needed, queries websites…)
 *
 * Note: This is a "copy" of message.php with necessary functions directly embedded.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', __DIR__ . '/logs.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Common Configuration ---
define('SMARTQUERY_API_KEY', 'SmartQueryAI');
define('MAX_INPUT_CHARACTERS', 40000);  // 10K tokens ~ 40K characters
define('RATE_LIMIT', 10);
define('RATE_LIMIT_WINDOW', 1); // (seconds)

$rateLimitFile = __DIR__ . '/rate_limit_' . md5($_SERVER['REMOTE_ADDR']) . '.log';

// --- Rate Limiting Function ---
function check_rate_limit() {
    global $rateLimitFile;
    $currentTime = microtime(true);
    $calls = [];
    if (file_exists($rateLimitFile)) {
        $calls = json_decode(file_get_contents($rateLimitFile), true) ?: [];
    }
    // Filter calls within RATE_LIMIT_WINDOW
    $calls = array_filter($calls, function($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < RATE_LIMIT_WINDOW;
    });
    if (count($calls) >= RATE_LIMIT) return false;
    $calls[] = $currentTime;
    file_put_contents($rateLimitFile, json_encode($calls));
    return true;
}

// --- Check method, API key, JSON input ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Only POST requests are allowed.']);
    exit;
}
if (!check_rate_limit()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
    exit;
}

$inputData = file_get_contents('php://input');
$data = json_decode($inputData, true);
if (!$data) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid JSON input.']);
    exit;
}
if (!isset($data['api_key']) || $data['api_key'] !== SMARTQUERY_API_KEY) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized. Invalid API key.']);
    exit;
}
if (!isset($data['prompt']) && !isset($data['question'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No prompt/question provided.']);
    exit;
}

$question = $data['prompt'] ?? $data['question'];
if (mb_strlen($question, 'UTF-8') > MAX_INPUT_CHARACTERS) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Input text exceeds token limit.']);
    exit;
}

// --- Load DB and configuration ---
require_once __DIR__ . '/db.php';
$config = yaml_parse_file(__DIR__ . '/config.yml');

// --- Initialize session variable for Gemini (to store question/answer history) ---
if (!isset($_SESSION['geminiResponses'])) {
    $_SESSION['geminiResponses'] = [];
}

// --- PROCESSING: If URL is present, fetch website content ---
if ($config['enable_website_query'] === "true") {
    preg_match_all('/(https?:\/\/[^\s]+)/', $question, $matches);
    if (!empty($matches[1])) {
        $webContent = "";
        $maxQueries = isset($config['max_website_queries']) ? intval($config['max_website_queries']) : 20;
        $processed_links = 0;
        foreach ($matches[1] as $link) {
            if ($processed_links >= $maxQueries) break;
            $content = fetchWebsiteContent($link);
            if ($content) {
                $webContent .= "Content from website $link:\n" . $content . "\n";
                $question = str_replace($link, '', $question);
                $processed_links++;
            }
        }
        $question = trim($question);
        $question = $webContent . "User's query: " . $question;
        if ($processed_links > 0) {
            $useMemoryBasic = false;
        }
    }
}

// --- HANDLE CONFIRMATION CODE (if enabled) ---
if ($config['confirmation_code'] === "true") {
    if (!isset($_SESSION['confirmed_code']) || $_SESSION['confirmed_code'] !== true) {
        $confirmationCode = null;
        if (preg_match('/\(code:\s*"([^"]+)"\)/i', $question, $matches)) {
            $confirmationCode = trim($matches[1]);
        } else if (preg_match('/([a-zA-Z0-9]{6,20})/', $question, $matches)) {
            $confirmationCode = trim($matches[1]);
        }
        if ($confirmationCode) {
            // Call code confirmation handler - assuming you have checkConfirmationCode() in ordercode.php
            require_once __DIR__ . '/ordercode.php';
            $codeCheckResult = checkConfirmationCode($confirmationCode);
            echo $codeCheckResult; // Return code confirmation result (JSON)
            exit;
        } else {
            echo json_encode(["status" => "error", "message" => "You need to enter the correct confirmation code to use this service. Please enter your confirmation code."]);
            exit;
        }
    }
}

// --- HANDLE RESPONSE BASED ON MESSAGE.PHP LOGIC ---
// If configured to use Gemini for the entire process:
if ($config['use_who_in_whole_process'] === "true") {
    $systemMessage = "You are a helpful chatbot. Ensure all LaTeX expressions are wrapped in double dollar signs ($$ ... $$) for correct rendering. Do not wrap normal text or code in $$ (latex code only). Do not repeat the user instructions. Your goal is to answer the user's question in a helpful and informative way.";
    $prompt = $systemMessage . "\n" . $question;
    $geminiResponse = callGemini($prompt);
    // If there's context from previous questions, add to prompt:
    if (count($_SESSION['geminiResponses']) > 0) {
        $context = "Here is the chat context between a user and AI, do not reply to these messages:\n";
        foreach ($_SESSION['geminiResponses'] as $q_and_a) {
            $context .= "User: " . $q_and_a['user'] . "\nAI: " . $q_and_a['ai'] . "\n";
        }
        $context .= "User: " . $question . "\nPlease reply to the last message from user.";
        $geminiResponse = callGemini($context);
    }
    $_SESSION['geminiResponses'][] = ['user' => $question, 'ai' => $geminiResponse];
    if (count($_SESSION['geminiResponses']) > 20) array_shift($_SESSION['geminiResponses']);
    header('Content-Type: application/json');
    echo json_encode(["status" => "success", "message" => $geminiResponse]);
    exit;
} else {
    // If not using Gemini for everything, try database query first.
    $answer = getAnswer($pdo, $question, $config);
    if ($answer === $config['reply_chatbot_text'] && $config['use_who_if_no_response'] === "true") {
        $systemMessage = "You are a helpful chatbot. Ensure all LaTeX expressions are wrapped in double dollar signs ($$ ... $$) for correct rendering. Do not wrap normal text or code in $$ (latex code only). Do not repeat the user instructions. Your goal is to answer the user's question in a helpful and informative way.";
        $prompt = $systemMessage . "\n" . $question;
        $geminiResponse = callGemini($prompt);
        $_SESSION['geminiResponses'][] = ['user' => $question, 'ai' => $geminiResponse];
        if (count($_SESSION['geminiResponses']) > 20) array_shift($_SESSION['geminiResponses']);
        header('Content-Type: application/json');
        echo json_encode(["status" => "success", "message" => $geminiResponse]);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(["status" => "success", "message" => $answer]);
        exit;
    }
}

function callGemini($prompt, $modelName = 'gemini-2.0-flash') { // Default to gemini-2.0-flash
    global $config;
    $apiKey = $config['gemini_api_key'] ?? 'This is the location of the Gemini API'; // Use config if available
    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $modelName . ":generateContent?key=" . $apiKey; // Dynamic model name
    $headers = ['Content-Type: application/json'];
    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
    curl_close($ch);

    if ($httpCode === 200) {
        $json = json_decode($response, true);
        return $json['candidates'][0]['content']['parts'][0]['text'] ?? "Error: Gemini API returned unexpected format. Response: " . $response; // More informative error
    } else {
        return "Error: Gemini API request failed with status code " . $httpCode . ". Response: " . $response; // Include HTTP status and response
    }
}

function getAnswer(PDO $pdo, string $question, array $config) {
    global $db_prefix;
    $artificialQuery = $config['artificial_query'] === 'true';
    if ($artificialQuery) {
        $stmt = $pdo->prepare("SELECT queries, replies FROM {$db_prefix}chatbot");
        $stmt->execute();
        $prompt = "You are an API key that selects the most accurate answer from different options. Only repeat verbatim the \"reply\" that best matches the user's question from the example questions and their corresponding replies in the database.\n\n";
        $prompt .= "User's Question: \"" . $question . "\"\n\n";
        $prompt .= "Database Data (question and reply pairs):\n\n";
        $i = 1;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $prompt .= $i . ". \"" . $row['queries'] . "\" , \"" . $row['replies'] . "\"\n";
            $i++;
        }
        $prompt .= "\nIf you can't find the right answer, please repeat the following sentence verbatim \"Sorry, I haven't been given this information, please ask the admin for support\"";
        $geminiResponse = callGemini($prompt);
        return $geminiResponse;
    } else {
        $maxScore = 0;
        $matchingAnswer = '';
        $stmt = $pdo->prepare("SELECT * FROM {$db_prefix}chatbot");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $query = strtolower($row['queries']);
            $questionWords = array_count_values(explode(' ', strtolower($question)));
            $queryWords = array_count_values(explode(' ', $query));
            $score = 0;
            foreach ($questionWords as $word => $count) {
                if (isset($queryWords[$word])) {
                    $score += min($count, $queryWords[$word]);
                }
            }
            if ($score > $maxScore) {
                $maxScore = $score;
                $matchingAnswer = $row['replies'];
            }
        }
        if ($matchingAnswer !== '') {
            if ($config['improve_reply_output'] === "true") {
                $systemMessage = "You are a helpful chatbot. Ensure all LaTeX expressions are wrapped in double dollar signs ($$ ... $$) for correct rendering. Do not wrap normal text or code in $$ (latex code only). Do not repeat the user instructions. Your goal is to answer the user's question in a helpful and informative way.";
                $prompt = $systemMessage . "\nQuestion: " . $question . "\nAnswer: " . $matchingAnswer;
                $geminiResponse = callGemini($prompt);
                return $geminiResponse;
            } else {
                return $matchingAnswer;
            }
        } else {
            return $config['reply_chatbot_text'];
        }
    }
}

function fetchWebsiteContent($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    $html = curl_exec($ch);
    curl_close($ch);
    if (!$html) return null;
    return strip_tags(preg_replace('/\s+/', ' ', $html));
}
?>