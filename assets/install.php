<?php
// Start the session
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the current step from the URL or set it to 1 if not set
$currentStep = isset($_GET['step']) ? (float)$_GET['step'] : 1;

// Helper function to set permissions
function setPermissions($dir, $perm = 0755) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = "$dir/$file";
        if (is_dir($path)) {
            setPermissions($path, $perm);
        } else {
            chmod($path, $perm);
        }
    }
}

// Original Step 1: Set permissions
if ($currentStep == 1) {
    try {
        setPermissions(__DIR__);
        header('Location: install.php?step=2'); // Redirect to step 2
        exit();
    } catch (Exception $e) {
        $permissionError = "Error setting file permissions. Please set to 755 manually.";
    }
}

// Step 2: PHP Requirements Check
if ($currentStep == 2) {
    $phpVersionCheck = version_compare(PHP_VERSION, '7.0', '>=');
    $pdoExtensionCheck = extension_loaded('pdo');
    $jsonExtensionCheck = extension_loaded('json');
    $yamlExtensionCheck = extension_loaded('yaml');

    $requirementsMet = $phpVersionCheck && $pdoExtensionCheck && $jsonExtensionCheck && $yamlExtensionCheck;

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ignore_requirements'])) {
        header('Location: install.php?step=3');
        exit();
    }
}

// --- NEW STEP 3: Database Connection Option ---
// In this step we allow the user either to use an existing db.php or enter new connection information.
// The submitted configuration (plus an optional "keep database intact" flag) is stored in the session.
if ($currentStep == 3) {
    // If the form was submitted with "use_existing", load the existing db.php configuration.
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['use_existing'])) {
        // "keep_data" checkbox will be sent if the user wants to keep the current data intact.
        if (file_exists(__DIR__ . '/db.php')) {
            include __DIR__ . '/db.php';
            $_SESSION['db_config'] = [
                'host' => $host,
                'port' => $port ?? 3306, // Default port
                'db'   => $db,
                'user' => $user,
                'pass' => $pass,
                'keep_data' => isset($_POST['keep_data']) ? true : false
            ];
            header('Location: install.php?step=3.1');
            exit();
        } else {
            $error = "No existing database configuration found.";
        }
    }
    // Otherwise, if the form is submitted with new connection data.
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['host']) && !isset($_POST['use_existing'])) {
        $host = filter_var($_POST['host'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $port = filter_var($_POST['port'], FILTER_VALIDATE_INT) ?: 3306; // Default MySQL port
        $db   = filter_var($_POST['db'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $user = filter_var($_POST['user'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $pass = $_POST['pass']; // No sanitization to avoid special character issues

        try {
            // Establish PDO connection to test
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            // Write new database configuration to db.php
            $dbFileContent = "<?php

// Load database prefix from prefix.json
\$prefixFile = __DIR__ . '/prefix.json';
if (file_exists(\$prefixFile)) {
    \$prefixData = json_decode(file_get_contents(\$prefixFile), true);
    \$db_prefix = isset(\$prefixData['db_prefix']) ? \$prefixData['db_prefix'] : '';
} else {
    \$db_prefix = ''; // Default to no prefix if file not found
}

\$host = '$host';
\$port = $port;
\$db   = '$db';
\$user = '$user';
\$pass = '$pass';
\$charset = 'utf8mb4';

\$dsn = \"mysql:host=\$host;port=\$port;dbname=\$db;charset=\$charset\";
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     \$pdo = new PDO(\$dsn, \$user, \$pass, \$options);
} catch (\\PDOException \$e) {
     throw new \\PDOException(\$e->getMessage(), (int)\$e->getCode());
}
?>";
            if (file_put_contents(__DIR__ . '/db.php', $dbFileContent) === false) {
                throw new Exception("Failed to write database configuration to db.php. Please check file permissions.");
            }
            $_SESSION['db_config'] = [
                'host' => $host,
                'port' => $port,
                'db'   => $db,
                'user' => $user,
                'pass' => $pass,
                'keep_data' => isset($_POST['keep_data']) ? true : false
            ];
            header('Location: install.php?step=3.1');
            exit();
        } catch (PDOException $e) {
            $error = "Database connection failed: " . $e->getMessage();
        }
    }
}


// --- NEW STEP 3.1: Database Prefix Configuration ---
if ($currentStep == 3.1) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['table_prefix'])) {
        $prefix = trim($_POST['table_prefix']);
        $allowReuse = isset($_POST['allow_reuse']) ? true : false;

        if (!isset($_SESSION['db_config'])) {
            $error = "Database configuration not found. Please go back to step 3.";
        } else {
            $dbConfig = $_SESSION['db_config'];
            try {
                $pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['db']}", $dbConfig['user'], $dbConfig['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);

                // Check if tables with the chosen prefix already exist
                $stmt = $pdo->query("SHOW TABLES LIKE '" . $prefix . "%'");
                $existingTables = $stmt->fetchAll();
                if (!empty($existingTables) && !$allowReuse) {
                    $error = "The prefix '$prefix' already exists. To reuse existing data, please check the 'Allow reuse' option. Otherwise, choose a different prefix.";
                } else {
                    // Save prefix to prefix.json
                    file_put_contents(__DIR__ . '/prefix.json', json_encode(["db_prefix" => $prefix], JSON_PRETTY_PRINT));

                    // Import database with prefix
                    importDatabase($pdo, $prefix);
                    
                    header('Location: install.php?step=4');
                    exit();
                }
            } catch (PDOException $e) {
                $error = "Database connection failed: " . $e->getMessage();
            }
        }
    }
}

// Step 4: Config Setup (Template-Based)
if ($currentStep == 4 && $_SERVER['REQUEST_METHOD'] == 'POST') {

    // --- Get user input (with proper sanitization/escaping) ---
    $geminiApiKey = $_POST['gemini_api_key'] ?? '';  // No need for extra escaping here
    $confirmationCode = $_POST['confirmation_code'] ?? 'false';
    $ocrApiKey = $_POST['ocr_api_key'] ?? 'K00000000000000';
    $useWhoIfNoResponse = isset($_POST['use_who_if_no_response']) ? 'true' : 'false';
    $useWhoInWholeProcess = isset($_POST['use_who_in_whole_process']) ? 'true' : 'false';
    $improveReplyOutput = isset($_POST['improve_reply_output']) ? 'true' : 'false';
    $enableWebsiteQuery = isset($_POST['enable_website_query']) ? 'true' : 'false';
    $chatbotName = $_POST['chatbot_name'] ?? 'Chatbot';
    $defaultChatbotText = $_POST['default_text'] ?? 'Hello';
    $askSomethingPlaceholder = $_POST['ask_something_placeholder'] ?? 'Ask something';
    $chatbotInterface = $_POST['chatbot_interface'] ?? 'Chatbot Interface';
    $replyChatbotText = $_POST['reply_chatbot_text'] ?? "Sorry, I don't understand your question.";
    $welcomeTo = $_POST['welcome_to'] ?? 'How can I help you today?';
    $suggestedQuestions = $_POST['suggested_questions'] ?? 'Suggested questions:';
    $enableSuggestions = isset($_POST['enable_suggestions']) ? 'true' : 'false';
    $enableFileUpload = isset($_POST['enable_file_upload']) ? 'true' : 'false';
    $enableAdvancedFeature = isset($_POST['enable_advanced_feature']) ? 'true' : 'false';

     // --- Escape values for YAML ---
    $escapedChatbotName = str_replace('"', '\"', $chatbotName);
    $escapedDefaultChatbotText = str_replace('"', '\"', $defaultChatbotText);
    $escapedAskSomethingPlaceholder = str_replace('"', '\"', $askSomethingPlaceholder);
    $escapedChatbotInterface = str_replace('"', '\"', $chatbotInterface);
    $escapedReplyChatbotText = str_replace('"', '\"', $replyChatbotText);
    $escapedWelcomeTo = str_replace('"', '\"', $welcomeTo);
    $escapedSuggestedQuestions = str_replace('"', '\"', $suggestedQuestions);

    // --- Read the template file ---
    $templateFile = 'config.yml.template'; // Use a template file
    if (!file_exists($templateFile)) {
        // Handle the case where the template is missing
        die("Error: config.yml.template file not found!");
    }
    $configContent = file_get_contents($templateFile);

    // --- Replace placeholders with user input ---
    $replacements = [
        'ocr_api_key: "K00000000000000"' => 'ocr_api_key: "' . $ocrApiKey . '"',
        'use_who_if_no_response: "true"' => 'use_who_if_no_response: "' . $useWhoIfNoResponse . '"',
        'use_who_in_whole_process: "false"' => 'use_who_in_whole_process: "' . $useWhoInWholeProcess . '"',
        'improve_reply_output: "true"' => 'improve_reply_output: "' . $improveReplyOutput . '"',
        'enable_website_query: "true"' => 'enable_website_query: "' . $enableWebsiteQuery . '"',
        'chatbot_name: "SmartQuery Ai"' => 'chatbot_name: "' . $escapedChatbotName . '"',
        'default_chatbot_text: "YOUR_DEFAULT_CHATBOT_TEXT_HERE"' => 'default_chatbot_text: "' . $escapedDefaultChatbotText . '"',
        'ask_something_placeholder: "Ask something"' => 'ask_something_placeholder: "' . $escapedAskSomethingPlaceholder . '"',
        'chatbot_interface: "Chatbot Interface"' => 'chatbot_interface: "' . $escapedChatbotInterface . '"',
        'reply_chatbot_text: "Sorry, I don\'t understand your question."' => 'reply_chatbot_text: "' . $escapedReplyChatbotText . '"',
        'welcome_to: "How can I help you today?"' => 'welcome_to: "' . $escapedWelcomeTo . '"',
        'suggested_questions: "Suggested questions:"' => 'suggested_questions: "' . $escapedSuggestedQuestions . '"',
        'confirmation_code: "false"' => 'confirmation_code: "' . $confirmationCode . '"',
        'enable_suggestions: true' => 'enable_suggestions: ' . $enableSuggestions,
        'enable_file_upload: true' => 'enable_file_upload: ' . $enableFileUpload,
        'enable_advanced_feature: true' => 'enable_advanced_feature: ' . $enableAdvancedFeature,
    ];

    $configContent = str_replace(array_keys($replacements), array_values($replacements), $configContent);


    // --- Write the modified content to config.yml ---
    if (file_put_contents('config.yml', $configContent) === false) {
        die("Error: Failed to write to config.yml. Check file permissions.");
    }

    // --- Store Gemini API Key to gemini.php (same as before) ---
      $geminiFileContent = "<?php
      error_reporting(E_ALL);
      ini_set('display_errors', 1);

      \$geminiApiKey = \"" . $geminiApiKey . "\";

      // Get the prompt and model name from the request
      \$data = json_decode(file_get_contents(\"php://input\"), true);
      \$prompt = \$data['prompt'] ?? '';
      \$modelName = \$data['modelName'] ?? 'gemini-2.0-flash'; // Default model if not provided

      if (!\$prompt) {
          echo json_encode([\"status\" => \"error\", \"message\" => \"No prompt provided\"]);
          exit();
      }

      // Construct the URL with the dynamic model name
      \$url = \"https://generativelanguage.googleapis.com/v1beta/models/\" . \$modelName . \":generateContent?key=\" . \$geminiApiKey;

      \$headers = [
          'Content-Type: application/json',
      ];

      \$payload = [
          \"contents\" => [
              [
                  \"parts\" => [
                      [
                          \"text\" => \$prompt
                      ]
                  ]
              ]
          ]
      ];

      \$ch = curl_init(\$url);
      curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt(\$ch, CURLOPT_HTTPHEADER, \$headers);
      curl_setopt(\$ch, CURLOPT_POST, true);
      curl_setopt(\$ch, CURLOPT_POSTFIELDS, json_encode(\$payload));

      \$response = curl_exec(\$ch);
      \$httpcode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
      curl_close(\$ch);

      if (\$httpcode === 200) {
          \$responseData = json_decode(\$response, true);
          if (isset(\$responseData['candidates'][0]['content']['parts'][0]['text'])) {
            echo json_encode(\$responseData['candidates'][0]['content']['parts'][0]['text'], JSON_UNESCAPED_UNICODE);
          } else {
              echo json_encode([\"status\" => \"error\", \"message\" => \"Gemini API returned an unexpected structure. Response: \" . \$response]);
          }
      } else {
          echo json_encode([\"status\" => \"error\", \"message\" => \"Error calling Gemini API. Status code: \" . \$httpcode . \", Response: \" . \$response]);
      }
      ?>";
      file_put_contents('gemini.php', $geminiFileContent);


    header('Location: install.php?step=4.1');
    exit();
}


// --- NEW STEP 4.1: Endpoint Installation ---
if ($currentStep == 4.1 && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $smartQueryApiKey = $_POST['smartquery_api_key'] ?? '';
    $geminiEndpointApiKey = $_POST['gemini_endpoint_api_key'] ?? ''; // NEW: Gemini API Key for Endpoint
    $useEndpoint = !isset($_POST['no_endpoint']); // Checkbox is "no_endpoint", so negate to get "useEndpoint"

    if ($useEndpoint) {
        // --- Read endpoint.php template and replace API keys ---
        $endpointTemplateFile = 'endpoint.php.template'; // Create a template for endpoint.php
        if (!file_exists($endpointTemplateFile)) {
            die("Error: endpoint.php.template file not found!");
        }
        $endpointContent = file_get_contents($endpointTemplateFile);
        $endpointContent = str_replace("define('SMARTQUERY_API_KEY', 'SmartQueryAI');", "define('SMARTQUERY_API_KEY', '" . $smartQueryApiKey . "');", $endpointContent);
        // NEW: Replace Gemini API Key placeholder
        $endpointContent = str_replace("\$apiKey = \$config['gemini_api_key'] ?? 'This is the location of the Gemini API';", "\$apiKey = '" . $geminiEndpointApiKey . "';", $endpointContent);


        // --- Write modified endpoint.php ---
        if (file_put_contents('endpoint.php', $endpointContent) === false) {
            die("Error: Failed to write to endpoint.php. Check file permissions.");
        }
    } else {
        // --- Delete endpoint.php if user chooses not to use it ---
        if (file_exists('endpoint.php')) {
            unlink('endpoint.php');
        }
    }

    header('Location: install.php?step=5'); // Proceed to password step
    exit();
}



// Step 5: Change Password
if ($currentStep == 5 && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'] ?? '123456';
    $loginFileContent = "<?php
session_start();
\$correct_password = '$password';
if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    \$password = \$_POST['password'] ?? '';
    if (\$password === \$correct_password) {
        \$_SESSION['authenticated'] = true;
        header('Location: assets/admin.php');
        exit;
    } else {
        \$error = 'Incorrect password';
    }
}
?>
<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Admin Login</title>
    <link rel=\"stylesheet\" href=\"assets/css/login.css\">
</head>
<body>
    <div class=\"login-container\">
        <img src=\"assets/logo.png\" alt=\"Logo\" class=\"logo\">
        <h2>Admin Login</h2>
        <?php if (isset(\$error)): ?>
            <p class=\"error\"><?php echo htmlspecialchars(\$error); ?></p>
        <?php endif; ?>
        <form method=\"POST\">
            <input type=\"password\" name=\"password\" placeholder=\"Enter password\" required>
            <button type=\"submit\">Login</button>
        </form>
    </div>
</body>
</html>";

    file_put_contents(__DIR__ . '/login.php', $loginFileContent);
    header('Location: install.php?step=6');
    exit();
}

// Step 6: Finalize Installation
if ($currentStep == 6) {
    unlink(__FILE__);
    if (file_exists(__DIR__ . '/chatbot.sql')) unlink('chatbot.sql');
    header('Location: /index.php'); // Redirect to a success page
    exit();
}

// --- Modified importDatabase function accepting a prefix ---
function importDatabase(PDO $pdo, $prefix) {
    $sqlFile = 'chatbot.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);

        // Define the tables that need to be prefixed
        $tablesToPrefix = [
            'chatbot',
            'confirmation_codes',
            'confirmation_code_attempts',
            'confirmation_code_ips'
        ];

        // Use regex to replace all occurrences of table names with the prefixed version
        foreach ($tablesToPrefix as $table) {
            $pattern = '/(`?)' . preg_quote($table, '/') . '(`?)/i';
            $replacement = '`' . $prefix . $table . '`';
            $sql = preg_replace($pattern, $replacement, $sql);
        }

        // Execute the modified SQL
        $pdo->exec($sql);
    } else {
        throw new Exception("Database file 'chatbot.sql' not found.");
    }
}

// Load prefix from prefix.json
$prefixFile = __DIR__ . '/prefix.json';
if (file_exists($prefixFile)) {
    $prefixData = json_decode(file_get_contents($prefixFile), true);
    $db_prefix = isset($prefixData['db_prefix']) ? $prefixData['db_prefix'] : '';
} else {
    $db_prefix = ''; // Default to no prefix if file not found
}

// Example usage in queries
$tableName = $db_prefix . 'chatbot'; // Uses the prefixed table name
$query = "SELECT * FROM `" . $tableName . "`";
// Execute query...


if ($currentStep == 3.1) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['table_prefix'])) {
        // Define $prefix safely
        $prefix = trim($_POST['table_prefix']);
        $allowReuse = isset($_POST['allow_reuse']) ? true : false;
        // Proceed with your database check and import logic
        // ...
    } else {
        // Optionally initialize $prefix to a default value to avoid warnings
        $prefix = '';
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Install Chatbot</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding: 40px;
            margin: 0;
            color: #343a40;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        h2, h3, h4 {
            text-align: center;
            color: #0056b3;
        }
        .form-group {
            margin-bottom: 15px;
        }
        input[type="text"],
        input[type="number"],
        input[type="password"] {
            width: calc(100% - 22px);
            padding: 12px 10px;
            margin: 8px 0;
            border: 1px solid #ced4da;
            border-radius: 5px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #80bdff;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #0056b3;
        }
        .message {
            text-align: center;
            font-size: 1.2em;
            color: green;
            margin: 10px 0;
        }
        .error {
            text-align: center;
            font-size: 1.1em;
            color: red;
            margin: 10px 0;
        }
        .checkbox-container {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        .checkbox-container label {
            margin-left: 8px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Install Chatbot</h2>
    <?php if ($currentStep == 1): ?>
        <?php if (isset($permissionError)): ?>
            <div class="error"><?= $permissionError; ?></div>
        <?php else: ?>
            <div>Setting file permissions...</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($currentStep == 2): ?>
        <h3>PHP Requirements Check</h3>
        <ul>
            <li>PHP Version: <span><?= $phpVersionCheck ? '✔️ OK' : '❌ Required: PHP 7.0 or higher'; ?></span></li>
            <li>PDO Extension: <span><?= $pdoExtensionCheck ? '✔️ OK' : '❌ Required'; ?></span></li>
            <li>JSON Extension: <span><?= $jsonExtensionCheck ? '✔️ OK' : '❌ Required'; ?></span></li>
            <li>YAML Extension: <span><?= $yamlExtensionCheck ? '✔️ OK' : '❌ Required'; ?></span></li>
        </ul>
        <?php if (!$requirementsMet): ?>
            <div class="error">Not all requirements are met!</div>
            <form method="POST">
                <div class="checkbox-container">
                    <input type="checkbox" name="ignore_requirements" required>
                    <label for="ignore_requirements">I understand the importance of these requirements but want to ignore them.</label>
                </div>
                <button type="submit">Continue</button>
            </form>
        <?php else: ?>
            <form method="POST" action="install.php?step=3">
                <button type="submit">Continue to Database Setup</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($currentStep == 3): ?>
        <h3>Database Connection</h3>
        <?php if (isset($error)): ?>
            <div class="error"><?= $error; ?></div>
        <?php endif; ?>
        <?php if (file_exists(__DIR__ . '/db.php')): ?>
            <h4>Existing database configuration detected.</h4>
            <form method="POST">
                <div class="checkbox-container">
                    <input type="checkbox" name="keep_data" id="keep_data">
                    <label for="keep_data">Keep existing database data intact</label>
                </div>
                <button type="submit" name="use_existing" value="1">Use Existing Configuration</button>
            </form>
            <hr>
            <h4>Or enter new database connection info:</h4>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="host">Database Host</label>
                <input type="text" name="host" placeholder="Database Host" required>
            </div>
            <div class="form-group">
                <label for="port">Database Port</label>
                <input type="number" name="port" placeholder="3306" value="3306" required>
            </div>
            <div class="form-group">
                <label for="db">Database Name</label>
                <input type="text" name="db" placeholder="Database Name" required>
            </div>
            <div class="form-group">
                <label for="user">Database Username</label>
                <input type="text" name="user" placeholder="Database Username" required>
            </div>
            <div class="form-group">
                <label for="pass">Database Password</label>
                <input type="password" name="pass" placeholder="Database Password" required>
            </div>
            <div class="checkbox-container">
                <input type="checkbox" name="keep_data" id="keep_data_new">
                <label for="keep_data_new">Keep database data intact if already present</label>
            </div>
            <button type="submit">Connect to Database</button>
        </form>
    <?php endif; ?>

    <?php if ($currentStep == 3.1): ?>
        <h3>Database Table Prefix Configuration</h3>
        <?php if (isset($error)): ?>
            <div class="error"><?= $error; ?></div>
        <?php endif; ?>
        <form method="POST" action="install.php?step=3.1">
            <div class="form-group">
                <label for="table_prefix">Enter table prefix (leave empty for none)</label>
                <input type="text" name="table_prefix" placeholder="e.g. myapp_" required>
            </div>
            <div class="checkbox-container">
                <input type="checkbox" name="allow_reuse" id="allow_reuse">
                <label for="allow_reuse">Allow reuse of existing prefix data</label>
            </div>
            <button type="submit">Continue and Import Database</button>
        </form>
    <?php endif; ?>

    <?php if ($currentStep == 4): ?>
        <h3>Language Settings, Gemini AI and Features Setup</h3>
        <form method="POST">
            <div class="form-group">
                <label for="gemini_api_key">Gemini API Key:</label>
                <input type="text" name="gemini_api_key" placeholder="Enter Gemini API key" required>
            </div>
            <div class="form-group">
                <label for="confirmation_code">Confirmation code:</label>
                <input type="text" name="confirmation_code" placeholder="confirmation_code:" required>
            </div>
            <div class="form-group">
                <label for="ocr_api_key">OCR Api key:</label>
                <input type="text" name="ocr_api_key" placeholder="K00000000000000" required>
            </div>
            <div class="checkbox-container">
                <input type="checkbox" name="use_who_if_no_response" id="use_who_if_no_response">
                <label for="use_who_if_no_response">Use Gemini if no match is found</label>
            </div>
            <div class="checkbox-container">
                <input type="checkbox" name="use_who_in_whole_process" id="use_who_in_whole_process">
                <label for="use_who_in_whole_process">Use Gemini for every question</label>
            </div>
            <div class="checkbox-container">
                <input type="checkbox" name="improve_reply_output" id="improve_reply_output">
                <label for="improve_reply_output">Use Gemini to improve chatbot reply</label>
            </div>
            <div class="checkbox-container">
                <input type="checkbox" name="enable_website_query" id="enable_website_query">
                <label for="enable_website_query">Enable website query</label>
            </div>
            <div class="form-group">
                <label for="chatbot_name">Chatbot Name</label>
                <input type="text" name="chatbot_name" placeholder="Chatbot Name" required>
            </div>
            <div class="form-group">
                <label for="default_text">Default Chatbot Text</label>
                <input type="text" name="default_text" placeholder="YOUR_DEFAULT_CHATBOT_TEXT_HERE" required>
            </div>
            <div class="form-group">
                <label for="ask_something_placeholder">Ask Something Placeholder</label>
                <input type="text" name="ask_something_placeholder" placeholder="Ask something" required>
            </div>
            <div class="form-group">
                <label for="chatbot_interface">Chatbot Interface Label</label>
                <input type="text" name="chatbot_interface" placeholder="Chatbot Interface" required>
            </div>
            <div class="form-group">
                <label for="reply_chatbot_text">Reply Chatbot Text</label>
                <input type="text" name="reply_chatbot_text" placeholder="Sorry, I don't understand your question." required>
            </div>
            <div class="form-group">
                <label for="welcome_to">Welcome Text</label>
                <input type="text" name="welcome_to" placeholder="How can I help you today?" required>
            </div>
            <div class="form-group">
                <label for="suggested_questions">Suggested Questions</label>
                <input type="text" name="suggested_questions" placeholder="Suggested questions:" required>
            </div>
            <div class="checkbox-container">
                <input type="checkbox" name="enable_suggestions" id="enable_suggestions">
                <label for="enable_suggestions">Enable Suggestions Popup</label>
            </div>
            <div class="checkbox-container">
                <input type="checkbox" name="enable_file_upload" id="enable_file_upload">
                <label for="enable_file_upload">Enable File Upload Option</label>
            </div>
            <div class="checkbox-container">
                <input type="checkbox" name="enable_advanced_feature" id="enable_advanced_feature">
                <label for="enable_advanced_feature">Enable Memory Enhancement Feature</label>
            </div>
            <button type="submit">Save Configuration</button>
        </form>
    <?php endif; ?>

        <?php if ($currentStep == 4.1): ?>
        <h3>Endpoint (API) Installation</h3>
        <form method="POST">
            <div class="form-group">
                <label for="smartquery_api_key">SmartQueryAI API Key (Legal API Key):</label>
                <input type="text" name="smartquery_api_key" placeholder="Enter SmartQueryAI API Key" required>
            </div>
            <div class="form-group">
                <label for="gemini_endpoint_api_key">Gemini API Key (For Endpoint.php):</label>
                <input type="text" name="gemini_endpoint_api_key" placeholder="Enter Gemini API Key for Endpoint" required>
            </div>
            <div class="checkbox-container">
                <input type="checkbox" name="no_endpoint" id="no_endpoint">
                <label for="no_endpoint">I do not use endpoint (API Server) - Skip Endpoint Installation and Delete endpoint.php</label>
            </div>
            <button type="submit">Continue with Endpoint Setup</button>
        </form>
    <?php endif; ?>

    <?php if ($currentStep == 5): ?>
        <h3>Set Admin Password</h3>
        <form method="POST">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" name="password" placeholder="Enter Password" required>
            </div>
            <button type="submit">Save Password</button>
        </form>
    <?php endif; ?>

    <?php if ($currentStep == 6): ?>
        <div class="message">Installation complete! Files deleted.</div>
    <?php endif; ?>
</div>
</body>
</html>
