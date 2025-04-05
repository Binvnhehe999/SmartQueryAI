<?php
require __DIR__ . '/../vendor/autoload.php'; // Load Composer autoloader

use Symfony\Component\Yaml\Yaml;

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

global $db_prefix; // Declare global here

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

try {
    $config = Yaml::parseFile(__DIR__ . '/config.yml'); // Parse YAML file
    $response = [
        'status' => 'success',
        'data' => $config
    ];
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);

// **Thêm hàm ghi log**
function logError($message) {
    $logFile = __DIR__ . '/logs.log'; // Đường dẫn đến file log, **chú ý quyền ghi file cho web server**
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] ERROR: $message\n";
    error_log($logMessage, 3, $logFile); // Ghi vào file, số 3 là kiểu log file
}

// Helper function to dynamically resolve API URL
function getApiUrl($endpoint) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    return rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
}

function checkConfirmationCode($code) {
   // Implementation remains the same as in original `message.php`
    $apiUrl = getApiUrl("ordercode.php?code=" . urlencode($code));

    // Get the real client IP
    $clientIP = $_SERVER['REMOTE_ADDR'];

    // Initialize cURL
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $apiUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);

    // Add the client IP address as a custom header
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "X-Client-IP: $clientIP"
    ]);

    $response = curl_exec($curl);
    $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpStatus !== 200 || $response === false) {
        $errorMessage = "Lỗi khi gọi ordercode.php. Status code: " . $httpStatus . ", Response: " . $response;
        logError($errorMessage); // **Ghi log lỗi**
        return json_encode(["status" => "error", "message" => "An error occurred while validating the code."]);
    }

    // Process response (both JSON and plain text)
    $responseData = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($responseData['status'])) {
        switch ($responseData['status']) {
             case 'active':
                $_SESSION['confirmed_code'] = true;
                return json_encode(["status" => "success", "message" => "Code confirmed! You may now interact with the chatbot."]);
            case 'multi-direction warning':
                return json_encode(["status" => "warning", "message" => "Warning: This code has been flagged for multi-direction use. Please contact your provider."]);
            case 'disabled':
                return json_encode(["status" => "error", "message" => "Incorrect confirmation code, please contact your service provider."]);
            case 'not found':
                 return json_encode(["status" => "error", "message" => "You need to enter the correct confirmation code to use this service."]);
            default:
                 return json_encode(["status" => "error", "message" => "Unknown code status received."]);
        }
    } else {
        // Assume plain text response if JSON decoding fails
        $errorMessage = "Phản hồi không mong đợi từ ordercode.php: " . htmlspecialchars($response);
        logError($errorMessage); // **Ghi log lỗi**
         return json_encode(["status" => "error", "message" => "Unexpected response: " . htmlspecialchars($response)]);
    }
}

function callGemini($prompt) {
    global $config;

    $url = getApiUrl("gemini.php");

    $data = array("prompt" => $prompt);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20); // Tăng timeout cho Gemini API

    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch); // Lấy thông tin lỗi cURL
    curl_close($ch);

    if ($httpcode === 200) {
         return $result;
     } else {
         $errorMessage = "Lỗi Gemini API. Status code: " . $httpcode . ", Response: " . $result . ", cURL error: " . $curl_error;
         logError($errorMessage); // **Ghi log lỗi**
         return json_encode(["status" => "error", "message" => "Gemini API Error : ". $result]);
     }
}


// Enhanced function to fetch website content
function fetchWebsiteContent($url)
{
        $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
     curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set a timeout of 10 seconds.
      curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');

    $html = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curl_error = curl_error($ch); // Lấy thông tin lỗi cURL
        curl_close($ch);


    if ($httpCode !== 200 || !$html) {
            $errorMessage = "Lỗi khi fetch website $url. Status code: " . $httpCode . ", cURL error: " . $curl_error;
            logError($errorMessage); // **Ghi log lỗi**
            return null; // Or throw an exception for error handling
         }

    // Extract only the text content
     $dom = new DOMDocument();
        @$dom->loadHTML($html); //Use @ to suppress warnings

         $text = '';
         $body = $dom->getElementsByTagName('body')->item(0);
             if ($body) {
                 $text = getTextFromNode($body);
             }

    return $text;
}
//Helper Function to convert the whole HTML elements into string
function getTextFromNode(DOMNode $node)
{
        $text = '';
        if ($node->nodeType === XML_TEXT_NODE) {
            $text .= $node->nodeValue;
        } else {
            foreach ($node->childNodes as $child) {
                $text .= getTextFromNode($child);
            }
         }
    return $text;
}

// Function to extract links from text
function extractLinks($text) {
    $links = [];
    preg_match_all('/(https?:\/\/[^\s]+)/', $text, $matches);
    if(!empty($matches[1])){
        $links = $matches[1];
    }
      return $links;
}


// Main message processing logic
$data = json_decode(file_get_contents("php://input"));

if (isset($data->question)) {
    $question = $data->question;
    $useMemoryBasic = $data->useMemoryBasic; // Get the flag
    $artificialQuery = $config['artificial_query'] === 'true';


    $websiteContent = '';
       // Check if the input is a valid link and website querying is enabled
    if ($config['enable_website_query'] === "true") {
       $links = extractLinks($question);
            $maxQueries = isset($config['max_website_queries']) ? intval($config['max_website_queries']) : 20;
         $processed_links = 0;


            foreach($links as $link){

                    if ($processed_links >= $maxQueries) {
                      break;
                     }

                $content = fetchWebsiteContent($link);
                    if($content){
                      $websiteContent .=  "Content from website $link:\n".$content . "\n";
                        $question = str_replace($link, '', $question); //remove the url, as that is what you asked for.

                         $processed_links++;
                   }
            }
         $question = trim($question);
         $question = $websiteContent  .  "User's query : " . $question;
            if($processed_links > 0 ){
                  $useMemoryBasic = false;
            }
    }


    if ($config['confirmation_code'] === "true") {
        if (!isset($_SESSION['confirmed_code']) || $_SESSION['confirmed_code'] !== true) {
            // **Bắt đầu sửa đổi logic xác nhận mã ở đây**

            $confirmationCode = null;

            // Tìm kiếm mã xác nhận trong cấu trúc (code: "MÃ_XÁC_NHẬN")
            if (preg_match('/\(code:\s*"([^"]+)"\)/i', $question, $matches)) {
                $confirmationCode = trim($matches[1]);
            } else {
                // Nếu không tìm thấy cấu trúc, thử tìm kiếm một chuỗi chữ và số có độ dài hợp lý (ví dụ: 6-20 ký tự)
                if (preg_match('/([a-zA-Z0-9]{6,20})/', $question, $matches)) {
                    $confirmationCode = trim($matches[1]);
                }
            }

            if ($confirmationCode) {
                $codeCheckResult = checkConfirmationCode($confirmationCode); // **Gửi mã đã lọc đến checkConfirmationCode**
                echo $codeCheckResult;
                exit; // Dừng xử lý tiếp nếu là mã xác nhận
            } else {
                // Nếu không tìm thấy mã hợp lệ, trả lời yêu cầu nhập mã
                echo json_encode(["status" => "error", "message" => "Bạn cần nhập mã xác nhận chính xác để sử dụng dịch vụ này. Vui lòng nhập mã xác nhận của bạn."]);
                exit;
            }
            // **Kết thúc sửa đổi logic xác nhận mã**
        } else {
           if ($config['use_who_in_whole_process'] === "true") {
                 $systemMessage = "You are a helpful chatbot.Ensure all LaTeX expressions are wrapped in double dollar signs ($$ ... $$) for correct rendering. Do not wrap normal text or code in $$ (latex code only). Do not repeat the user instructions. Your goal is to answer the user's question in a helpful and informative way.";
                    $prompt =  $systemMessage . "\n" . $question;
                   $geminiResponse = callGemini($prompt);
                    $geminiResponse = json_decode($geminiResponse, true);
                     if(isset($geminiResponse['status']) && $geminiResponse['status'] == 'error'){
                           echo json_encode($geminiResponse);
                       } else {
                           if ($useMemoryBasic === false && isset($_SESSION['geminiResponses']) && count($_SESSION['geminiResponses']) > 0) {
                                 $prompt =  "Here is the chat context between a user and AI, do not reply to these messages. Use this as the context:\n";
                                foreach ($_SESSION['geminiResponses'] as $q_and_a) {
                                     $prompt .=  "User: " . $q_and_a['user'] . "\n";
                                     $prompt .=  "AI: " . $q_and_a['ai'] . "\n";
                                }
                                $prompt .= "User: " . $question . "\n";
                                $prompt += "Please reply to the last message from user. Do not repeat the user instructions or previous answers, answer based on the context and your own knowledge.";
                                $geminiResponse = callGemini($prompt);
                                $geminiResponse = json_decode($geminiResponse, true);
                                if(isset($geminiResponse['status']) && $geminiResponse['status'] == 'error'){
                                    echo json_encode($geminiResponse);
                                } else {
                                      $_SESSION['geminiResponses'][] = ['user' => $question, 'ai' => $geminiResponse];
                                   if (count($_SESSION['geminiResponses']) > 20) {
                                       array_shift($_SESSION['geminiResponses']); // Keep only the latest 20 entries
                                   }
                                   echo json_encode(["status" => "success", "message" => $geminiResponse]);
                                }
                            } else{
                                $_SESSION['geminiResponses'][] = ['user' => $question, 'ai' => $geminiResponse];
                                if (count($_SESSION['geminiResponses']) > 20) {
                                     array_shift($_SESSION['geminiResponses']); // Keep only the latest 20 entries
                                }
                                 echo json_encode(["status" => "success", "message" => $geminiResponse]);
                           }
                       }
               }  else{
                    $answer = getAnswer($pdo,$question,$config);
                    if ($answer === $config['reply_chatbot_text'] && $config['use_who_if_no_response'] === "true") {
                       $systemMessage = "You are a helpful chatbot.Ensure all LaTeX expressions are wrapped in double dollar signs ($$ ... $$) for correct rendering. Do not wrap normal text or code in $$ (latex code only). Do not repeat the user instructions. Your goal is to answer the user's question in a helpful and informative way.";
                            $prompt =  $systemMessage . "\n" . $question;
                             $geminiResponse = callGemini($prompt);
                             $geminiResponse = json_decode($geminiResponse, true);
                             if(isset($geminiResponse['status']) && $geminiResponse['status'] == 'error'){
                                  echo json_encode($geminiResponse);
                              } else {
                                if ($useMemoryBasic === false && isset($_SESSION['geminiResponses']) && count($_SESSION['geminiResponses']) > 0) {
                                        $prompt =  "Here is the chat context between a user and AI, do not reply to these messages. Use this as the context:\n";
                                        foreach ($_SESSION['geminiResponses'] as $q_and_a) {
                                             $prompt .=  "User: " . $q_and_a['user'] . "\n";
                                             $prompt .=  "AI: " . $q_and_a['ai'] . "\n";
                                        }
                                        $prompt .= "User: " . $question . "\n";
                                        $prompt .= "Please reply to the last message from user. Do not repeat the user instructions or previous answers, answer based on the context and your own knowledge.";
                                        $geminiResponse = callGemini($prompt);
                                        $geminiResponse = json_decode($geminiResponse, true);
                                        if(isset($geminiResponse['status']) && $geminiResponse['status'] == 'error'){
                                            echo json_encode($geminiResponse);
                                        } else {
                                              $_SESSION['geminiResponses'][] = ['user' => $question, 'ai' => $geminiResponse];
                                           if (count($_SESSION['geminiResponses']) > 20) {
                                               array_shift($_SESSION['geminiResponses']); // Keep only the latest 20 entries
                                           }
                                            echo json_encode(["status" => "success", "message" => $geminiResponse]);
                                        }
                                   } else {
                                         $_SESSION['geminiResponses'][] = ['user' => $question, 'ai' => $geminiResponse];
                                         if (count($_SESSION['geminiResponses']) > 20) {
                                                array_shift($_SESSION['geminiResponses']); // Keep only the latest 20 entries
                                            }
                                        echo json_encode(["status" => "success", "message" => $geminiResponse]);
                                   }
                             }
                       } else {
                            echo json_encode(["status" => "success", "message" => $answer]);
                       }
               }
        }
    } else {
       if ($config['use_who_in_whole_process'] === "true") {
           $systemMessage = "You are a helpful chatbot.Ensure all LaTeX expressions are wrapped in double dollar signs ($$ ... $$) for correct rendering. Do not wrap normal text or code in $$ (latex code only). Do not repeat the user instructions. Your goal is to answer the user's question in a helpful and informative way.";
              $prompt =  $systemMessage . "\n" . $question;
             $geminiResponse = callGemini($prompt);
               $geminiResponse = json_decode($geminiResponse, true);
                if(isset($geminiResponse['status']) && $geminiResponse['status'] == 'error'){
                   echo json_encode($geminiResponse);
                 } else {
                     if ($useMemoryBasic === false && isset($_SESSION['geminiResponses']) && count($_SESSION['geminiResponses']) > 0) {
                             $prompt =  "Here is the chat context between a user and AI, do not reply to these messages. Use this as the context:\n";
                                foreach ($_SESSION['geminiResponses'] as $q_and_a) {
                                     $prompt .=  "User: " . $q_and_a['user'] . "\n";
                                     $prompt .=  "AI: " . $q_and_a['ai'] . "\n";
                                }
                                $prompt .= "User: " . $question . "\n";
                                $prompt .= "Please reply to the last message from user. Do not repeat the user instructions or previous answers, answer based on the context and your own knowledge.";
                                 $geminiResponse = callGemini($prompt);
                                 $geminiResponse = json_decode($geminiResponse, true);
                                 if(isset($geminiResponse['status']) && $geminiResponse['status'] == 'error'){
                                    echo json_encode($geminiResponse);
                                } else {
                                    $_SESSION['geminiResponses'][] = ['user' => $question, 'ai' => $geminiResponse];
                                    if (count($_SESSION['geminiResponses']) > 20) {
                                       array_shift($_SESSION['geminiResponses']); // Keep only the latest 20 entries
                                     }
                                   echo json_encode(["status" => "success", "message" => $geminiResponse]);
                                }
                     } else {
                          $_SESSION['geminiResponses'][] = ['user' => $question, 'ai' => $geminiResponse];
                           if (count($_SESSION['geminiResponses']) > 20) {
                                    array_shift($_SESSION['geminiResponses']); // Keep only the latest 20 entries
                                }
                        echo json_encode(["status" => "success", "message" => $geminiResponse]);
                     }
                }
        } else{
                $answer = getAnswer($pdo, $question, $config);
                if ($answer === $config['reply_chatbot_text'] && $config['use_who_if_no_response'] === "true") {
                   $systemMessage = "You are a helpful chatbot.Ensure all LaTeX expressions are wrapped in double dollar signs ($$ ... $$) for correct rendering. Do not wrap normal text or code in $$ (latex code only). Do not repeat the user instructions. Your goal is to answer the user's question in a helpful and informative way.";
                    $prompt =  $systemMessage . "\n" . $question;
                   $geminiResponse = callGemini($prompt);
                     $geminiResponse = json_decode($geminiResponse, true);
                     if(isset($geminiResponse['status']) && $geminiResponse['status'] == 'error'){
                          echo json_encode($geminiResponse);
                       } else {
                         if ($useMemoryBasic === false && isset($_SESSION['geminiResponses']) && count($_SESSION['geminiResponses']) > 0) {
                              $prompt =  "Here is the chat context between a user and AI, do not reply to these messages. Use this as the context:\n";
                                foreach ($_SESSION['geminiResponses'] as $q_and_a) {
                                     $prompt .=  "User: " . $q_and_a['user'] . "\n";
                                     $prompt .=  "AI: " . $q_and_a['ai'] . "\n";
                                }
                                $prompt .= "User: " . $question . "\n";
                                $prompt .= "Please reply to the last message from user. Do not repeat the user instructions or previous answers, answer based on the context and your own knowledge.";
                                 $geminiResponse = callGemini($prompt);
                                $geminiResponse = json_decode($geminiResponse, true);
                                 if(isset($geminiResponse['status']) && $geminiResponse['status'] == 'error'){
                                      echo json_encode($geminiResponse);
                                } else {
                                      $_SESSION['geminiResponses'][] = ['user' => $question, 'ai' => $geminiResponse];
                                    if (count($_SESSION['geminiResponses']) > 20) {
                                        array_shift($_SESSION['geminiResponses']); // Keep only the latest 20 entries
                                      }
                                    echo json_encode(["status" => "success", "message" => $geminiResponse]);
                                }
                           } else {
                                 $_SESSION['geminiResponses'][] = ['user' => $question, 'ai' => $geminiResponse];
                                  if (count($_SESSION['geminiResponses']) > 20) {
                                     array_shift($_SESSION['geminiResponses']); // Keep only the latest 20 entries
                                  }
                                   echo json_encode(["status" => "success", "message" => $geminiResponse]);
                           }
                     }
                } else {
                   echo json_encode(["status" => "success", "message" => $answer]);
                 }
         }
    }
} else {
     echo json_encode(["status" => "error", "message" => $config['reply_chatbot_text']]);
}


function getAnswer(PDO $pdo, string $question, array $config): string
{
    global $db_prefix;
    $artificialQuery = $config['artificial_query'] === 'true';

    if ($artificialQuery) {
        $stmt = $pdo->prepare("SELECT queries, replies FROM {$db_prefix}chatbot");
        $stmt->execute();

         $prompt = "You are an API key that selects the most accurate answer from different options. Only repeat verbatim the \"reply\" that best matches the user's question from the example questions and their corresponding replies in the database.\n\n";
         $prompt .= "User's Question: \"" . $question . "\"\n\n";
         $prompt .= "Database Data (question and reply pairs):\n\n";


        $i = 1;
        $data = [];
         while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
               $prompt .=  $i . ".\"" . $row['queries'] . "\" , \"" . $row['replies'] . "\"\n";
                 $i++;
           }

          $prompt .= "\n If you can't find the right answer, please repeat the following sentence verbatim \"Sorry, I haven't been given this information, please ask the admin for support\"";
            $geminiResponse = callGemini($prompt);
            $geminiResponse = json_decode($geminiResponse, true);
           if (isset($geminiResponse['status']) && $geminiResponse['status'] == 'error') {
               return  $config['reply_chatbot_text'];
           } else {
               $finalReply = $geminiResponse;
                if($config['improve_reply_output'] === "true"){
                      $systemMessage = "You are a helpful chatbot.Ensure all LaTeX expressions are wrapped in double dollar signs ($$ ... $$) for correct rendering. Do not wrap normal text or code in $$ (latex code only). Do not repeat the user instructions. Your goal is to answer the user's question in a helpful and informative way.";
                      $prompt =   $systemMessage . "\n" . "Question: " . $question . " \n" ."Answer: " . $finalReply;
                      $improvedResponse = callGemini($prompt);
                       $improvedResponse = json_decode($improvedResponse, true);

                    if (isset($improvedResponse['status']) && $improvedResponse['status'] == 'error') {
                      return  $finalReply; // Return original AI response if no improvement
                     } else {
                        return $improvedResponse;
                     }
                 }else{
                      return  $finalReply;
                }
           }
     }else{

         $maxScore = 0;
         $matchingAnswer = '';

        $stmt = $pdo->prepare("SELECT * FROM {$db_prefix}chatbot");
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $query = strtolower($row['queries']);
            $questionWords = array_count_values(explode(' ', strtolower($question)));
            $queryWords = array_count_values(explode(' ', $query));

            $score = calculateScore($questionWords, $queryWords);

            if ($score > $maxScore) {
                $maxScore = $score;
                $matchingAnswer = $row['replies'];
            }
        }

         if ($matchingAnswer !== '') {
              if ($config['improve_reply_output'] === "true") {
                $systemMessage = "You are a helpful chatbot.Ensure all LaTeX expressions are wrapped in double dollar signs ($$ ... $$) for correct rendering. Do not wrap normal text or code in $$ (latex code only). Do not repeat the user instructions. Your goal is to answer the user's question in a helpful and informative way.";
                $prompt =   $systemMessage . "\n" . "Question: " . $question . " \n" ."Answer: " . $matchingAnswer;
                  $geminiResponse = callGemini($prompt);
                    $geminiResponse = json_decode($geminiResponse, true);
                    if(isset($geminiResponse['status']) && $geminiResponse['status'] == 'error'){
                          return $matchingAnswer; // Return original answer if AI fails
                       } else {
                          return $geminiResponse;
                       }
            } else {
               return $matchingAnswer;
            }
        } else {
            return $config['reply_chatbot_text'];
        }
     }
}

function calculateScore($questionWords, $queryWords)
{
       $score = 0;
        foreach ($questionWords as $word => $count) {
            if (isset($queryWords[$word])) {
                $score += min($count, $queryWords[$word]);
            }
        }
     return $score;
}
?>