<?php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$config = Yaml::parseFile('assets/config.yml');
$default_text = $config['default_chatbot_text'];
$enable_suggestions = isset($config['enable_suggestions']) ? filter_var($config['enable_suggestions'], FILTER_VALIDATE_BOOLEAN) : false;
 $enable_file_upload = isset($config['enable_file_upload']) ? filter_var($config['enable_file_upload'], FILTER_VALIDATE_BOOLEAN) : false;
 $enable_advanced_feature = isset($config['enable_advanced_feature']) ? filter_var($config['enable_advanced_feature'], FILTER_VALIDATE_BOOLEAN) : false;
 ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['chatbot_interface']; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/js-yaml/4.1.0/js-yaml.min.js"></script>
</head>
<body
     data-enable-suggestions="<?php echo $enable_suggestions ? 'true' : 'false'; ?>"
     data-enable-file-upload="<?php echo $enable_file_upload ? 'true' : 'false'; ?>"
     data-enable-advanced-feature="<?php echo $enable_advanced_feature ? 'true' : 'false'; ?>"
 >
    <div class="abc">
        <div class="abc-container">
            <div class="abc-header">
                <h3><?php echo $config['chatbot_name']; ?></h3>
            </div>
            <div class="abc-body">
                <div class="abc-logo-message-container">
                    <div class="abc-logo-container">
                        <img src="assets/logo.png" alt="Logo" class="abc-logo">
                    </div>
                    <div id="greeting-message" class="greeting-message"><b><?php echo $config['welcome_to']; ?></b></div>
                </div>
                <div id="chatbot"></div>
               <?php if ($enable_suggestions): ?>
                <div id="suggestions-popup" class="suggestions-popup">
                    <p><b><?php echo $config['suggested_questions']; ?></b></p>
                    <ul>
                        <li onclick="fillInput('What are your working hours?')">What are your working hours?</li>
                        <li onclick="fillInput('How can I contact support?')">How can I contact support?</li>
                        <li onclick="fillInput('Tell me about your services.')">Tell me about your services.</li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <form class="abc-input-container" onsubmit="sendMessage(event)">
             <?php if($enable_file_upload): ?>
              <label for="image-input" class="abc-upload-button"><i class="fa-solid fa-paperclip"></i></label>
              <input type="file" class="abc-file-input" id="image-input" accept="image/*" style="display:none;">
             <?php endif; ?>
              <input type="text" class="abc-input" id="user-input" placeholder="<?php echo $config['ask_something_placeholder']; ?>" maxlength="120">
              <button type="submit" class="abc-send-button"><i class="fa-solid fa-paper-plane"></i></button>
            </form>
            
             <?php if ($enable_advanced_feature): ?>
            <div class="abc-input-container">
                <label class="switch">
                    <input type="checkbox" id="advanced-feature-switch"> 
                    <div class="slider">
                        <div class="circle">
                            <svg class="cross" xml:space="preserve" viewBox="0 0 365.696 365.696" y="0" x="0" height="6" width="6">
                                <g>
                                    <path data-original="#000000" fill="currentColor" d="M243.188 182.86 356.32 69.726c12.5-12.5 12.5-32.766 0-45.247L341.238 9.398c-12.504-12.503-32.77-12.503-45.25 0L182.86 122.528 69.727 9.374c-12.5-12.5-32.766-12.5-45.247 0L9.375 24.457c-12.5 12.504-12.5 32.77 0 45.25l113.152 113.152L9.398 295.99c-12.503 12.503-12.503 32.769 0 45.25L24.48 356.32c12.5 12.5 32.766 12.5 45.247 0l113.132-113.132L295.99 356.32c12.503 12.5 32.769 12.5 45.25 0l15.081-15.082c12.5-12.504 12.5-32.77 0-45.25zm0 0"></path>
                                </g>
                            </svg>
                            <svg class="checkmark" xml:space="preserve" viewBox="0 0 24 24" y="0" x="0" height="10" width="10">
                                <g>
                                    <path class="" data-original="#000000" fill="currentColor" d="M9.707 19.121a.997.997 0 0 1-1.414 0l-5.646-5.647a1.5 1.5 0 0 1 0-2.121l.707-.707a1.5 1.5 0 0 1 2.121 0L9 14.171l9.525-9.525a1.5 1.5 0 0 1 2.121 0l.707.707a1.5 1.5 0 0 1 0 2.121z"></path>
                                </g>
                            </svg>
                        </div>
                    </div>
                </label>
                <span class="toggle-text">Memory enhancement feature (Remember up to 20 questions)</span>
            </div>
              <?php endif; ?>

            <div class="typing-indicator" id="typing-indicator" style="display: none;"></div>
        </div>
    </div>
    
    <script src="assets/js/chat.js"></script>
</body>
</html>