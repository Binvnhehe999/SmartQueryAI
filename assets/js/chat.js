document.addEventListener("DOMContentLoaded", function () {
    fetch('assets/config.yml')
        .then(response => response.text())
        .then(yamlText => {
            const config = jsyaml.load(yamlText);

            const form = document.querySelector('.abc-input-container');
            const userInput = document.getElementById('user-input');
            const imageInput = document.getElementById('image-input');
            const chatbotContainer = document.getElementById('chatbot');
            const greetingMessage = document.getElementById('greeting-message');
            const suggestionsPopup = document.getElementById('suggestions-popup');
            const logoContainer = document.querySelector('.abc-logo-container');
            const switchInput = document.querySelector('.switch input');

            const body = document.body;
            const enableSuggestions = body.dataset.enableSuggestions === 'true';
            const enableFileUpload = body.dataset.enableFileUpload === 'true';
            const enableAdvancedFeature = body.dataset.enableAdvancedFeature === 'true';


            if (suggestionsPopup && !enableSuggestions) {
                suggestionsPopup.remove();
            }

            if (imageInput && !enableFileUpload) {
                let fileUploadInput = document.querySelector(".abc-upload-button");
                fileUploadInput.remove();
                imageInput.remove();
            }

            if (switchInput && !enableAdvancedFeature) {
                let switchContainer = document.querySelector(".switch").closest('.abc-input-container');
                switchContainer.remove();
            }


            let isFeatureEnabled = switchInput?.checked;

            if (switchInput) {
                switchInput.addEventListener('change', () => {
                    isFeatureEnabled = switchInput.checked;
                    if (!isFeatureEnabled) {
                        sessionStorage.removeItem('userQuestions');
                    }
                });
            }


            form.addEventListener('submit', function (event) {
                event.preventDefault();
                const message = userInput.value;
                sendMessage(message);
            });
            if (imageInput) {
                imageInput.addEventListener('change', function (event) {
                    const file = event.target.files[0];
                    if (file) {
                        processImage(file);
                    }
                });
            }
            function sendMessage(message) {
                if (message.trim() !== '') {
                    hideInitialElements();
                    showChat(message, 'user');
                    const typingChat = showChatWithTypingEffect('', 'chatbot', true);


                    let prompt = message; // Initialize with the current question
                      let useMemoryBasic = false;

                    if (isFeatureEnabled) {
                         let questions = sessionStorage.getItem('userQuestions') || '';
                          let questionArray = questions.split('|').filter(q => q.trim() !== '');

                          if (questionArray.length >= 20) {
                            alert("Reached maximum limit for AI model enhancing response feature");
                            return;
                        }
                          questionArray.push(message);
                            sessionStorage.setItem('userQuestions', questionArray.join('|'));
                            useMemoryBasic = true;


                        if(config.use_who_in_whole_process === 'true' || config.improve_reply_output === 'true'  ){
                           useMemoryBasic = false;
                            if(questionArray.length > 1){
                            prompt =  "Here are the previous messages from the user, do not reply to these messages. Use this as the context:\n";
                                for (let i = 0; i < questionArray.length - 1; i++) {
                                prompt += "User: " + questionArray[i] + "\n";
                                }
                                prompt += "User: " +  message + "\n";
                                prompt += "Please reply to the last message from user."
                             }
                        }else{
                            if(questionArray.length > 1 && config.use_who_if_no_response === 'true'){
                                 prompt =  questionArray.join(' ');
                            }

                        }
                     }

                    fetch('assets/message.php', {
                        method: 'POST',
                        body: JSON.stringify({ question: prompt , useMemoryBasic: useMemoryBasic}), // Send the structured prompt
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                        .then(response => response.json())
                        .then(data => {
                            removeTypingIndicator(typingChat);
                            if (data.status === "success") {
                                //updateChatWithTypingEffect(data.message, typingChat, 'chatbot');
                                updateChatWithWordFadeEffect(data.message, typingChat, 'chatbot'); // Use the new function
                            } else {
                                //updateChatWithTypingEffect(data.message, typingChat, 'chatbot');
                                updateChatWithWordFadeEffect(data.message, typingChat, 'chatbot'); // Use the new function
                            }
                        })
                        .catch(err => {
                            console.error("Error:", err);
                            removeTypingIndicator(typingChat);
                            //updateChatWithTypingEffect("Sorry, I'm having some trouble processing your request.", typingChat, 'chatbot');
                             updateChatWithWordFadeEffect("Sorry, I'm having some trouble processing your request.", typingChat, 'chatbot'); // Use the new function
                        });
                    userInput.value = '';
                }
            }

                    // Theme Switch Logic
            const themeSwitchContainer = document.createElement('div');
            themeSwitchContainer.classList.add('theme-switch-container');
            const themeSwitchButton = document.createElement('button');
            themeSwitchButton.classList.add('theme-switch-button');
            themeSwitchButton.innerHTML = '<i class="fas fa-moon"></i>'; // Default moon icon
            themeSwitchContainer.appendChild(themeSwitchButton);
            document.body.appendChild(themeSwitchContainer);

            const currentTheme = localStorage.getItem('theme') || 'light';
            const styleElement = document.querySelector('link[href="assets/css/style.css"]');
            if (currentTheme === 'dark') {
                 styleElement.href = 'assets/css/style2.css';
                themeSwitchButton.classList.add('dark-mode');
                 themeSwitchButton.innerHTML = '<i class="fas fa-sun"></i>';
            }
            themeSwitchButton.addEventListener('click', () => {
                if (styleElement.getAttribute('href') === 'assets/css/style.css') {
                    styleElement.href = 'assets/css/style2.css';
                    themeSwitchButton.classList.add('dark-mode');
                     themeSwitchButton.innerHTML = '<i class="fas fa-sun"></i>';
                    localStorage.setItem('theme', 'dark');

                } else {
                   styleElement.href = 'assets/css/style.css';
                    themeSwitchButton.classList.remove('dark-mode');
                   themeSwitchButton.innerHTML = '<i class="fas fa-moon"></i>';
                    localStorage.setItem('theme', 'light');
                }
            });


            function processImage(file) {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('apikey', config.ocr_api_key);
                fetch('https://api.ocr.space/parse/image', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        const text = data.ParsedResults[0].ParsedText;
                        sendMessage(text);
                    })
                    .catch(err => console.log(err));
            }

            function showChat(message, sender) {
                let chatBubble = document.createElement('div');
                chatBubble.classList.add('abc-bubble', sender);
                if (sender === 'user') {
                    chatBubble.style.justifyContent = 'flex-end';
                    chatBubble.innerHTML = `<div class="text">${message}</div>`;
                } else if (sender === 'chatbot') {
                    chatBubble.innerHTML = `
                      <div class="avatar">
                          <i class="fas fa-user"></i>
                      </div>
                      <div class="text"></div>
                  `;
                }
                chatbotContainer.appendChild(chatBubble);
                chatbotContainer.scrollTop = chatbotContainer.scrollHeight;
            }


            function showChatWithTypingEffect(message, sender, isTyping = false) {
                let chatBubble = document.createElement('div');
                chatBubble.classList.add('abc-bubble', sender);
                let textContent = `<div class="text"></div>`;
                if (isTyping) {
                    textContent = `<div class="text"><div class="typing-indicator"></div></div>`;
                }
                if (sender == 'chatbot') {
                    let avatarContent = `
                          <div class="avatar"><i class="fas fa-user"></i></div>
                   `;
                    chatBubble.innerHTML = avatarContent + textContent;
                } else {
                    chatBubble.innerHTML = textContent;
                }
                chatbotContainer.appendChild(chatBubble);
                chatbotContainer.scrollTop = chatbotContainer.scrollHeight;
                return chatBubble;
            }


            function updateChatWithTypingEffect(message, chatBubble, sender) {
                if (chatBubble) {
                    let textContainer = chatBubble.querySelector('.text');
                    let processedMessage = splitMessageIntoChunks(message);
                    let currentChunkIndex = 0;
                    textContainer.innerHTML = '';
                    function displayChunk() {
                        if (currentChunkIndex < processedMessage.length) {
                            let chunk = processedMessage[currentChunkIndex];
                            currentChunkIndex++;

                            let span = document.createElement('span');
                            span.className = 'typing-chunk';
                            span.innerHTML = chunk;
                            textContainer.appendChild(span);
                            setTimeout(() => {
                                span.style.opacity = 1;
                            }, 25);
                            setTimeout(displayChunk, 83);
                        }
                        chatbotContainer.scrollTop = chatbotContainer.scrollHeight;
                    }
                    displayChunk();
                }
            }
            function removeTypingIndicator(chatBubble) {
                if (chatBubble) {
                    let elements = chatBubble.querySelectorAll('.typing-indicator');
                    elements.forEach(element => element.remove());
                }
            }
            function splitMessageIntoChunks(message) {
                let chunks = [];
                let words = message.split(/(\s+)/);

                let chunk = '';
                words.forEach(word => {
                    if (word.trim().length === 0) {
                        if (chunk) {
                            chunks.push(chunk);
                            chunk = '';
                        }
                        chunks.push(word);
                    } else {
                        if (chunk.length + word.length > 50) {
                            chunks.push(chunk);
                            chunk = word;
                        } else {
                            chunk += word;
                        }
                    }
                });
                if (chunk) {
                    chunks.push(chunk);
                }
                return chunks;
            }

            function hideInitialElements() {
                greetingMessage.classList.add('hide-initial');
                if (suggestionsPopup) {
                    suggestionsPopup.classList.add('hide-initial');
                }

                logoContainer.classList.add('hide-initial');
            }
            window.fillInput = function (text) {
                userInput.value = text;
                userInput.focus();
            }
        })
        .catch(err => console.error('Error loading config.yml:', err));
});

// Enhanced BBCode parser supporting URL, image, list, color, and size tags.
function parseBBCode(text) {
  // URL links:
  // [url]http://example.com[/url]
  // [url=http://example.com]Link Text[/url]
  text = text.replace(/\[url\](.*?)\[\/url\]/gi, '<a href="$1" target="_blank">$1</a>');
  text = text.replace(/\[url=(.*?)\](.*?)\[\/url\]/gi, '<a href="$1" target="_blank">$2</a>');

  // Image tag: [img]http://example.com/image.jpg[/img]
  text = text.replace(/\[img\](.*?)\[\/img\]/gi, '<img src="$1" alt="Image" />');

  // Unordered List:
  // [list][*]Item 1[*]Item 2[/list]
  text = text.replace(/\[list\](.*?)\[\/list\]/gis, function(match, content) {
      // Split by [*] token (ignore empty splits)
      let items = content.split(/\[\*\]/).filter(item => item.trim() !== '');
      let listItems = items.map(item => `<li>${item.trim()}</li>`).join('');
      return `<ul>${listItems}</ul>`;
  });
  
  // Ordered List (using [list=1]):
  // [list=1][*]First[*]Second[/list]
  text = text.replace(/\[list=1\](.*?)\[\/list\]/gis, function(match, content) {
      let items = content.split(/\[\*\]/).filter(item => item.trim() !== '');
      let listItems = items.map(item => `<li>${item.trim()}</li>`).join('');
      return `<ol>${listItems}</ol>`;
  });

  // Color: [color=red]text[/color] or [color=#ff0000]text[/color]
  text = text.replace(/\[color=(.*?)\](.*?)\[\/color\]/gi, '<span style="color:$1;">$2</span>');

  // Size: [size=12]text[/size] (numeric, appending "px") or [size=large]text[/size]
  text = text.replace(/\[size=(\d+)\](.*?)\[\/size\]/gi, function(match, size, content) {
      return `<span style="font-size:${size}px;">${content}</span>`;
  });
  text = text.replace(/\[size=([a-zA-Z]+)\](.*?)\[\/size\]/gi, '<span style="font-size:$1;">$2</span>');

  // Basic formatting:
  text = text.replace(/\[b\](.*?)\[\/b\]/gi, '<strong>$1</strong>');
  text = text.replace(/\[i\](.*?)\[\/i\]/gi, '<em>$1</em>');
  text = text.replace(/\[u\](.*?)\[\/u\]/gi, '<u>$1</u>');
  text = text.replace(/\[code\](.*?)\[\/code\]/gi, '<code>$1</code>');
  text = text.replace(/\[quote\](.*?)\[\/quote\]/gi, '<blockquote>$1</blockquote>');

  return text;
}

// Format the raw bot output by first converting BBCode to HTML,
// then processing any Markdown if marked.js is available.
function formatBotMessage(text) {
  text = parseBBCode(text);
  if (typeof marked !== 'undefined') {
    text = marked.parse(text);
  }
  return text;
}

function updateChatWithWordFadeEffect(message, chatBubble, sender) {
    if (chatBubble) {
        // Get the container where text is rendered
        let textContainer = chatBubble.querySelector('.text');
        // Clear any previous content
        textContainer.innerHTML = '';

        // Split the complete message into words
        const words = message.split(' ');
        let index = 0;

        // Define a function to reveal each word with a fade-in effect
        function revealNextWord() {
            if (index < words.length) {
                // Create a span for the current word
                const span = document.createElement('span');
                span.textContent = words[index] + ' ';
                // Start with fully transparent text
                span.style.opacity = 0;
                // Use a CSS transition for smooth fade-in
                span.style.transition = 'opacity 0.3s ease';
                // Append the span to the container
                textContainer.appendChild(span);

                // Force a reflow to apply the transition then set opacity to 1
                void span.offsetWidth;
                span.style.opacity = 1;

                index++;
                // Scroll the chat container (if defined) to ensure the new text is visible
                if (typeof chatbotContainer !== 'undefined') {
                    chatbotContainer.scrollTop = chatbotContainer.scrollHeight;
                }
                // Call the next word reveal after a short delay
                setTimeout(revealNextWord, 150);
            } else {
                // Once all words have been revealed,
                // process the full message formatting.
                let formattedMessage = formatBotMessage(message);
                textContainer.innerHTML = formattedMessage;
                // If MathJax is loaded, re-typeset the new content to render any LaTeX code.
                if (window.MathJax) {
                    MathJax.typesetPromise([textContainer]).catch(function(err) {
                        console.error(err);
                    });
                }
            }
        }

        // Start the word-by-word reveal animation
        revealNextWord();
    }
}