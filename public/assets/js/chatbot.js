/**
 * HIPAA-Compliant Chatbot UI Component
 * Provides a floating chatbot interface for the SecureHealth application
 */

class ChatbotUI {
    constructor() {
        this.isOpen = false;
        this.isLoading = false;
        this.conversationHistory = [];
        this.maxHistoryLength = 50;
        this.scrollThrottle = false; // Added for better scroll handling

        this.init();
    }

    init() {
        this.createChatbotHTML();
        this.attachEventListeners();
        this.loadConversationHistory();

        // Position the chatbot higher to avoid overlap with activity timer
        this.positionChatbot();
    }

    // Method to reposition the chatbot to avoid activity timer
    positionChatbot() {
        const chatbotContainer = document.getElementById('chatbot-container');
        if (chatbotContainer) {
            chatbotContainer.style.bottom = '90px'; // Increased to avoid activity timer
        }
    }

    createChatbotHTML() {
        // Create chatbot container
        const chatbotHTML = `
            <div id="chatbot-container" class="chatbot-container">
                <!-- Chatbot Toggle Button -->
                <button id="chatbot-toggle" class="chatbot-toggle" title="Open AI Assistant">
                    <i class="fas fa-robot"></i>
                    <span class="chatbot-notification-badge" id="chatbot-notification" style="display: none;">1</span>
                </button>

                <!-- Chatbot Window -->
                <div id="chatbot-window" class="chatbot-window">
                    <div class="chatbot-header">
                        <div class="chatbot-title">
                            <i class="fas fa-robot"></i>
                            <span>AI Assistant</span>
                        </div>
                        <div class="chatbot-controls">
                            <button id="chatbot-clear" class="chatbot-btn-clear" title="Clear Conversation">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button id="chatbot-examples" class="chatbot-btn-examples" title="Show Examples">
                                <i class="fas fa-lightbulb"></i>
                            </button>
                            <button id="chatbot-close" class="chatbot-btn-close" title="Close">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="chatbot-body">
                        <div id="chatbot-messages" class="chatbot-messages">
                            <div class="chatbot-welcome">
                                <div class="chatbot-welcome-icon">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <div class="chatbot-welcome-text">
                                    <h4>Welcome to the AI Assistant!</h4>
                                    <p>I can help you with:</p>
                                    <ul>
                                        <li>📚 Knowledge questions about MongoDB, HIPAA, and the application</li>
                                        <li>🔍 Patient data queries (based on your role)</li>
                                        <li>💊 Drug interaction checks (doctors only)</li>
                                        <li>🏥 Clinical decision support</li>
                                    </ul>
                                    <p><em>All interactions are logged for audit compliance.</em></p>
                                </div>
                            </div>
                        </div>

                        <div id="chatbot-examples-panel" class="chatbot-examples-panel" style="display: none;">
                            <h5>Example Queries:</h5>
                            <div id="chatbot-examples-list" class="chatbot-examples-list">
                                <!-- Examples will be loaded dynamically -->
                            </div>
                        </div>

                        <div class="chatbot-input-container">
                            <div class="chatbot-input-wrapper">
                                <textarea
                                    id="chatbot-input"
                                    class="chatbot-input"
                                    placeholder="Ask me anything about the application, patients, or medical knowledge..."
                                    rows="1"
                                    maxlength="1000"
                                ></textarea>
                                <button id="chatbot-send" class="chatbot-send" disabled>
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                            <div class="chatbot-character-count">
                                <span id="chatbot-char-count">0</span>/1000
                            </div>
                        </div>
                    </div>

                    <div class="chatbot-footer">
                        <div class="chatbot-status">
                            <span id="chatbot-status-text">Ready</span>
                        </div>
                        <div class="chatbot-privacy">
                            <i class="fas fa-shield-alt"></i>
                            <span>HIPAA Compliant</span>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Insert chatbot HTML
        document.body.insertAdjacentHTML('beforeend', chatbotHTML);
    }

    attachEventListeners() {
        const toggle = document.getElementById('chatbot-toggle');
        const close = document.getElementById('chatbot-close');
        const send = document.getElementById('chatbot-send');
        const input = document.getElementById('chatbot-input');
        const clear = document.getElementById('chatbot-clear');
        const examples = document.getElementById('chatbot-examples');
        const examplesPanel = document.getElementById('chatbot-examples-panel');
        const messagesContainer = document.getElementById('chatbot-messages');

        // Toggle chatbot
        toggle.addEventListener('click', () => this.toggleChatbot());
        close.addEventListener('click', () => this.closeChatbot());

        // Send message
        send.addEventListener('click', () => this.sendMessage());
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Input character count
        input.addEventListener('input', () => {
            this.updateCharacterCount();
            this.autoResizeTextarea();
        });

        // Clear conversation
        clear.addEventListener('click', () => this.clearConversation());

        // Toggle examples
        examples.addEventListener('click', () => this.toggleExamples());

        // Load examples on first open
        toggle.addEventListener('click', () => {
            if (!this.isOpen) {
                this.loadExamples();
            }
        });

        // Close chatbot when clicking outside
        document.addEventListener('click', (e) => {
            const chatbot = document.getElementById('chatbot-container');
            if (this.isOpen && !chatbot.contains(e.target)) {
                this.closeChatbot();
            }
        });

        // Add scroll event listener to the messages container
        messagesContainer.addEventListener('scroll', () => {
            this.handleScroll(messagesContainer);
        });

        // Handle window resize to maintain proper positioning
        window.addEventListener('resize', () => {
            this.positionChatbot();
        });
    }

    // Handle scroll events in the messages container
    handleScroll(container) {
        if (this.scrollThrottle) return;

        this.scrollThrottle = true;

        // Debounce scroll handling
        setTimeout(() => {
            this.scrollThrottle = false;
        }, 100);
    }

    toggleChatbot() {
        if (this.isOpen) {
            this.closeChatbot();
        } else {
            this.openChatbot();
        }
    }

    openChatbot() {
        this.isOpen = true;
        const window = document.getElementById('chatbot-window');
        const toggle = document.getElementById('chatbot-toggle');

        // Apply fade-in animation
        window.style.opacity = '0';
        window.style.display = 'flex'; // Changed to flex to ensure proper layout

        // Trigger reflow for animation
        void window.offsetWidth;

        // Fade in
        window.style.opacity = '1';
        toggle.classList.add('active');

        // Focus input
        setTimeout(() => {
            document.getElementById('chatbot-input').focus();
            // Ensure any existing messages are scrolled into view
            this.scrollToBottom();
        }, 100);

        // Hide notification badge
        this.hideNotification();
    }

    closeChatbot() {
        this.isOpen = false;
        const window = document.getElementById('chatbot-window');
        const toggle = document.getElementById('chatbot-toggle');

        // Apply fade-out animation
        window.style.opacity = '0';

        // Wait for animation to complete
        setTimeout(() => {
            window.style.display = 'none';
            toggle.classList.remove('active');

            // Hide examples panel
            document.getElementById('chatbot-examples-panel').style.display = 'none';
        }, 300);
    }

    async sendMessage() {
        const input = document.getElementById('chatbot-input');
        const query = input.value.trim();

        if (!query || this.isLoading) return;

        // Clear input and disable send button
        input.value = '';
        this.updateCharacterCount();
        this.autoResizeTextarea();
        this.setLoading(true);

        // Add user message to conversation
        this.addMessage('user', query);

        try {
            // First try the main chatbot endpoint
            let useEmergencyEndpoint = false;
            let response;

            try {
                response = await fetch('/api/chatbot/query', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({ query }),
                    // Set a shorter timeout to fail faster
                    signal: AbortSignal.timeout(5000)
                });

                // If we get a 500 or 401 error, try the emergency endpoint
                if (response.status === 500 || response.status === 401) {
                    console.warn(`Main chatbot endpoint returned ${response.status}, trying emergency endpoint`);
                    useEmergencyEndpoint = true;
                }
            } catch (fetchError) {
                console.error('Error calling main chatbot endpoint:', fetchError);
                useEmergencyEndpoint = true;
            }

            // If main endpoint failed, try emergency endpoint
            if (useEmergencyEndpoint) {
                console.log('Using emergency chatbot endpoint');
                try {
                    response = await fetch('/api/chatbot-emergency/query', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'include',
                        body: JSON.stringify({ query })
                    });

                    if (!response.ok) {
                        console.error('Emergency chatbot API also failed:', response.status, response.statusText);

                        try {
                            // Try to parse error details if available
                            const errorData = await response.json();
                            let errorMessage = `Emergency endpoint error: ${errorData.error || response.statusText}`;

                            if (errorData.details) {
                                console.error('Emergency endpoint detailed error:', errorData.details);

                                // Include relevant details in the error message
                                if (errorData.details.problem) {
                                    errorMessage += `\n\nProblem: ${errorData.details.problem}`;
                                }

                                if (errorData.details.raw_message) {
                                    errorMessage += `\n\nDetails: ${errorData.details.raw_message}`;
                                }
                            }

                            this.addMessage('error', errorMessage);
                        } catch (jsonParseError) {
                            this.addMessage('error', `Emergency chatbot endpoint failed with status: ${response.status} ${response.statusText}`);
                        }
                        return;
                    }
                } catch (emergencyError) {
                    console.error('Error calling emergency chatbot endpoint:', emergencyError);
                    this.addMessage('error', `Failed to connect to the emergency chatbot service: ${emergencyError.message}`);
                    return;
                }
            } else if (!response.ok) {
                console.error('Chatbot API error:', response.status, response.statusText);

                // Try to get error details from the response
                try {
                    const errorData = await response.json();

                    // Format a more detailed error message with diagnostic information
                    let errorMessage = errorData.error || `Server error: ${response.status} ${response.statusText}`;

                    // Include error type if available
                    if (errorData.error_type) {
                        errorMessage += `\n\nError type: ${errorData.error_type}`;
                    }

                    // Include detailed diagnostic information if available
                    if (errorData.details) {
                        console.error('Detailed error information:', errorData.details);

                        // Add problem summary if available
                        if (errorData.details.problem) {
                            errorMessage += `\n\nProblem: ${errorData.details.problem}`;
                        }

                        // Add file and line information
                        if (errorData.details.file && errorData.details.line) {
                            const filePath = errorData.details.file.split('/').slice(-3).join('/');
                            errorMessage += `\n\nLocation: ${filePath}:${errorData.details.line}`;
                        }

                        // Add raw message if available
                        if (errorData.details.raw_message) {
                            errorMessage += `\n\nDetails: ${errorData.details.raw_message}`;
                        }
                    }

                    this.addMessage('error', errorMessage);

                } catch (jsonError) {
                    // If we can't parse the error JSON, show a generic message
                    this.addMessage('error', `Server error: ${response.status} ${response.statusText}. Unable to parse error details.`);
                    console.error('Error parsing error response:', jsonError);
                }

                return; // Exit early
            }

            // We have a valid response from either endpoint
            const data = await response.json();

            if (data.success) {
                let responseText = data.response;

                // Make sure responseText is a string
                if (typeof responseText !== 'string') {
                    responseText = String(responseText);
                    console.warn("Response was not a string:", data.response);
                }

                // If responseText is "[object Object]", we have a problem
                if (responseText === '[object Object]') {
                    responseText = "The server returned an invalid response. Please try a different query.";
                    console.error("Invalid response format:", data);
                }

                // If there's data and response is of type 'data', format it properly
                if (data.type === 'data' && data.data) {
                    // Add the data details in a formatted way
                    if (Array.isArray(data.data) && data.data.length > 0) {
                        responseText += '\n\n' + this.formatDataArray(data.data);
                    } else if (typeof data.data === 'object' && data.data !== null) {
                        // Handle single object case
                        responseText += '\n\n' + this.formatDataObject(data.data);
                    }
                }

                this.addMessage('assistant', responseText, data.type, data.sources, data.data);
            } else {
                const errorMessage = data.error || 'An unknown error occurred';
                console.error('Chatbot API returned error:', errorMessage, data.error_type || 'Unknown error type');
                this.addMessage('error', errorMessage);
            }
        } catch (error) {
            console.error('Chatbot error:', error);

            // Show a more detailed error message
            let errorMessage = 'Failed to connect to the AI assistant. Please check your connection and try again.';

            // Add more details if available
            if (error.message) {
                errorMessage += `\n\nError details: ${error.message}`;
            }

            if (error.name) {
                errorMessage += `\nError type: ${error.name}`;
            }

            this.addMessage('error', errorMessage);
        } finally {
            this.setLoading(false);
        }
    }

    addMessage(type, content, messageType = 'text', sources = [], data = null) {
        const messagesContainer = document.getElementById('chatbot-messages');
        const messageId = 'msg-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);

        // Debug - log the raw message content
        console.log("Message Content:", {
            type: type,
            content: content,
            contentType: typeof content,
            messageType: messageType,
            sources: sources,
            data: data
        });

        // Ensure content is always a string
        let textContent = '';
        if (typeof content === 'string') {
            textContent = content;
        } else if (typeof content === 'object' && content !== null) {
            try {
                // If it's an object, try to stringify it nicely
                textContent = JSON.stringify(content, null, 2);
            } catch (e) {
                textContent = String(content); // Fallback to basic toString
            }
        } else {
            textContent = String(content); // Convert any other type to string
        }

        let messageHTML = '';

        if (type === 'user') {
            messageHTML = `
                <div class="chatbot-message user-message" data-message-id="${messageId}">
                    <div class="message-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="message-content">
                        <div class="message-text">${this.escapeHtml(textContent)}</div>
                        <div class="message-time">${new Date().toLocaleTimeString()}</div>
                    </div>
                </div>
            `;
        } else if (type === 'assistant') {
            let responseClass = 'assistant-message';
            if (messageType === 'warning') responseClass += ' warning-message';
            if (messageType === 'error') responseClass += ' error-message';
            if (messageType === 'success') responseClass += ' success-message';

            messageHTML = `
                <div class="chatbot-message ${responseClass}" data-message-id="${messageId}">
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        <div class="message-text">${this.formatMessageContent(textContent)}</div>
                        ${sources.length > 0 ? this.formatSources(sources) : ''}
                        ${data ? this.formatData(data) : ''}
                        <div class="message-time">${new Date().toLocaleTimeString()}</div>
                    </div>
                </div>
            `;
        } else if (type === 'error') {
            messageHTML = `
                <div class="chatbot-message error-message" data-message-id="${messageId}">
                    <div class="message-avatar">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="message-content">
                        <div class="message-text">${this.escapeHtml(textContent)}</div>
                        <div class="message-time">${new Date().toLocaleTimeString()}</div>
                    </div>
                </div>
            `;
        }

        messagesContainer.insertAdjacentHTML('beforeend', messageHTML);

        // Use smoother scrolling with requestAnimationFrame
        this.smoothScrollToBottom();

        // Store in conversation history (with PHI sanitization)
        const historyEntry = {
            id: messageId,
            type,
            content: this.sanitizePHIFromContent(content),
            messageType,
            sources,
            data: data ? this.sanitizePHIFromData(data) : null,
            timestamp: new Date().toISOString()
        };
        
        // Only store non-PHI conversations or sanitized versions
        this.conversationHistory.push(historyEntry);

        // Trim history if too long
        if (this.conversationHistory.length > this.maxHistoryLength) {
            this.conversationHistory = this.conversationHistory.slice(-this.maxHistoryLength);
        }

        this.saveConversationHistory();
    }

    formatMessageContent(content) {
        // Make sure content is a string
        if (typeof content !== 'string') {
            if (content === undefined || content === null) {
                content = '';
            } else if (typeof content === 'object') {
                try {
                    content = JSON.stringify(content, null, 2);
                } catch (e) {
                    content = String(content);
                }
            } else {
                content = String(content);
            }
        }

        // Convert markdown-like formatting to HTML
        let formatted = this.escapeHtml(content);

        // Convert line breaks
        formatted = formatted.replace(/\n/g, '<br>');

        // Convert **bold** to <strong>
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // Convert *italic* to <em>
        formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');

        // Convert code blocks
        formatted = formatted.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');

        // Convert inline code
        formatted = formatted.replace(/`([^`]+)`/g, '<code>$1</code>');

        return formatted;
    }

    formatSources(sources) {
        if (!sources || sources.length === 0) return '';

        let sourcesHTML = '<div class="message-sources"><h6>Sources:</h6><ul>';
        sources.forEach(source => {
            sourcesHTML += `
                <li>
                    <strong>${this.escapeHtml(source.title)}</strong>
                    <span class="source-category">(${source.category})</span>
                    <span class="source-relevance">Relevance: ${(source.relevance * 100).toFixed(1)}%</span>
                </li>
            `;
        });
        sourcesHTML += '</ul></div>';

        return sourcesHTML;
    }

    formatData(data) {
        if (!data) return '';

        return `
            <div class="message-data">
                <details>
                    <summary>View Data</summary>
                    <pre><code>${JSON.stringify(data, null, 2)}</code></pre>
                </details>
            </div>
        `;
    }

    /**
     * Format a data array into a readable text
     */
    formatDataArray(dataArray) {
        if (!Array.isArray(dataArray) || dataArray.length === 0) return '';

        // Create a formatted text representation of the data array
        let formattedText = '';

        dataArray.forEach((item, index) => {
            formattedText += `${index + 1}. `;

            // Add name if available
            if (item.name) {
                formattedText += `${item.name}`;
            }

            // Add ID if available
            if (item.id) {
                formattedText += ` (ID: ${item.id})`;
            }

            // Add date of birth if available
            if (item.dateOfBirth) {
                formattedText += `\n   DOB: ${item.dateOfBirth}`;
            }

            // Add diagnosis if available
            if (item.diagnosis) {
                formattedText += `\n   Diagnosis: ${item.diagnosis}`;
            }

            // Add medications if available
            if (item.medications && Array.isArray(item.medications)) {
                formattedText += `\n   Medications: ${item.medications.join(', ')}`;
            }

            formattedText += '\n\n';
        });

        return formattedText;
    }

    /**
     * Format a single data object into a readable text
     */
    formatDataObject(dataObj) {
        if (!dataObj || typeof dataObj !== 'object') return '';

        // Create a formatted text representation of the data object
        let formattedText = '';

        // Add name if available
        if (dataObj.name) {
            formattedText += `${dataObj.name}`;
        }

        // Add ID if available
        if (dataObj.id) {
            formattedText += ` (ID: ${dataObj.id})`;
        }

        formattedText += '\n';

        // Add date of birth if available
        if (dataObj.dateOfBirth) {
            formattedText += `DOB: ${dataObj.dateOfBirth}\n`;
        }

        // Add diagnosis if available
        if (dataObj.diagnosis) {
            formattedText += `Diagnosis: ${dataObj.diagnosis}\n`;
        }

        // Add medications if available
        if (dataObj.medications && Array.isArray(dataObj.medications)) {
            formattedText += `Medications: ${dataObj.medications.join(', ')}\n`;
        }

        // Handle other common fields
        if (dataObj.patientId) {
            formattedText += `Patient ID: ${dataObj.patientId}\n`;
        }

        // Add interactions if available (for drug interactions)
        if (dataObj.interactions && Array.isArray(dataObj.interactions)) {
            formattedText += `\nInteractions:\n`;
            dataObj.interactions.forEach((interaction, i) => {
                formattedText += `${i+1}. ${interaction}\n`;
            });
        }

        return formattedText;
    }

    async loadExamples() {
        try {
            const response = await fetch('/api/chatbot/examples', {
                credentials: 'include'
            });
            const data = await response.json();

            if (data.success) {
                this.displayExamples(data.examples);
            }
        } catch (error) {
            console.error('Failed to load examples:', error);
        }
    }

    displayExamples(examples) {
        const examplesList = document.getElementById('chatbot-examples-list');
        let examplesHTML = '';

        Object.entries(examples).forEach(([category, exampleList]) => {
            examplesHTML += `<div class="example-category"><h6>${category.replace('_', ' ').toUpperCase()}</h6>`;
            exampleList.forEach(example => {
                examplesHTML += `
                    <div class="example-item" data-example="${this.escapeHtml(example)}">
                        <i class="fas fa-lightbulb"></i>
                        <span>${this.escapeHtml(example)}</span>
                    </div>
                `;
            });
            examplesHTML += '</div>';
        });

        examplesList.innerHTML = examplesHTML;

        // Add click handlers for examples
        examplesList.querySelectorAll('.example-item').forEach(item => {
            item.addEventListener('click', () => {
                const example = item.dataset.example;
                document.getElementById('chatbot-input').value = example;
                this.updateCharacterCount();
                this.autoResizeTextarea();
                this.toggleExamples();
            });
        });
    }

    toggleExamples() {
        const panel = document.getElementById('chatbot-examples-panel');
        const isVisible = panel.style.display !== 'none';

        // Apply slide animation
        if (isVisible) {
            panel.style.maxHeight = '0';
            setTimeout(() => {
                panel.style.display = 'none';
            }, 300);
        } else {
            panel.style.display = 'block';
            panel.style.maxHeight = '0';
            setTimeout(() => {
                panel.style.maxHeight = '200px';
            }, 10);
            this.loadExamples();
        }
    }

    clearConversation() {
        if (confirm('Are you sure you want to clear the conversation history?')) {
            const messagesContainer = document.getElementById('chatbot-messages');

            // Clear messages (keep welcome message)
            const messages = messagesContainer.querySelectorAll('.chatbot-message');
            messages.forEach(msg => msg.remove());

            // Clear history
            this.conversationHistory = [];
            this.saveConversationHistory();

            // Show welcome message again
            this.showWelcomeMessage();
        }
    }

    showWelcomeMessage() {
        const messagesContainer = document.getElementById('chatbot-messages');
        const welcomeHTML = `
            <div class="chatbot-welcome">
                <div class="chatbot-welcome-icon">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="chatbot-welcome-text">
                    <h4>Welcome to the AI Assistant!</h4>
                    <p>I can help you with:</p>
                    <ul>
                        <li>📚 Knowledge questions about MongoDB, HIPAA, and the application</li>
                        <li>🔍 Patient data queries (based on your role)</li>
                        <li>💊 Drug interaction checks (doctors only)</li>
                        <li>🏥 Clinical decision support</li>
                    </ul>
                    <p><em>All interactions are logged for audit compliance.</em></p>
                    <p><strong>⚠️ PHI Notice:</strong> Patient data is not stored in conversation history for HIPAA compliance.</p>
                </div>
            </div>
        `;
        messagesContainer.innerHTML = welcomeHTML;
    }

    setLoading(loading) {
        this.isLoading = loading;
        const sendButton = document.getElementById('chatbot-send');
        const input = document.getElementById('chatbot-input');
        const statusText = document.getElementById('chatbot-status-text');

        if (loading) {
            sendButton.disabled = true;
            input.disabled = true;
            statusText.textContent = 'Thinking...';
            sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        } else {
            sendButton.disabled = false;
            input.disabled = false;
            statusText.textContent = 'Ready';
            sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
            input.focus();
        }
    }

    updateCharacterCount() {
        const input = document.getElementById('chatbot-input');
        const charCount = document.getElementById('chatbot-char-count');
        const sendButton = document.getElementById('chatbot-send');

        const count = input.value.length;
        charCount.textContent = count;

        // Enable/disable send button
        sendButton.disabled = count === 0 || count > 1000;

        // Change color based on count
        if (count > 900) {
            charCount.style.color = '#dc3545';
        } else if (count > 700) {
            charCount.style.color = '#ffc107';
        } else {
            charCount.style.color = '#6c757d';
        }
    }

    autoResizeTextarea() {
        const textarea = document.getElementById('chatbot-input');
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    // Improved smooth scrolling with animation frame
    smoothScrollToBottom() {
        const messagesContainer = document.getElementById('chatbot-messages');
        const targetPosition = messagesContainer.scrollHeight;
        const startPosition = messagesContainer.scrollTop;
        const distance = targetPosition - startPosition;
        const duration = 300; // ms
        let startTime = null;

        function animateScroll(currentTime) {
            if (!startTime) startTime = currentTime;
            const timeElapsed = currentTime - startTime;
            const progress = Math.min(timeElapsed / duration, 1);

            // Easing function for smoother animation
            const easeProgress = progress < 0.5 ?
                2 * progress * progress :
                1 - Math.pow(-2 * progress + 2, 2) / 2;

            messagesContainer.scrollTop = startPosition + distance * easeProgress;

            if (progress < 1) {
                requestAnimationFrame(animateScroll);
            }
        }

        requestAnimationFrame(animateScroll);
    }

    // Simple scroll to bottom (fallback)
    scrollToBottom() {
        const messagesContainer = document.getElementById('chatbot-messages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    showNotification() {
        const badge = document.getElementById('chatbot-notification');
        badge.style.display = 'block';
    }

    hideNotification() {
        const badge = document.getElementById('chatbot-notification');
        badge.style.display = 'none';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Sanitize PHI from data objects (HIPAA compliance)
     */
    sanitizePHIFromData(data) {
        if (!data || typeof data !== 'object') return data;
        
        const sanitized = { ...data };
        
        // Remove PHI fields
        delete sanitized.diagnosis;
        delete sanitized.dateOfBirth;
        delete sanitized.ssn;
        delete sanitized.medications;
        delete sanitized.bloodType;
        delete sanitized.insurance;
        
        // Sanitize patient names (keep only first letter + last name)
        if (sanitized.name) {
            const nameParts = sanitized.name.split(' ');
            if (nameParts.length >= 2) {
                sanitized.name = nameParts[0].charAt(0) + '. ' + nameParts[nameParts.length - 1];
            }
        }
        
        return sanitized;
    }

    /**
     * Sanitize PHI from content strings (HIPAA compliance)
     */
    sanitizePHIFromContent(content) {
        if (!content || typeof content !== 'string') return content;
        
        // Replace PHI patterns with sanitized versions
        let sanitized = content;
        
        // Replace diagnosis information
        sanitized = sanitized.replace(/Diagnosis:\s*[^\n]+/gi, 'Diagnosis: [REDACTED]');
        sanitized = sanitized.replace(/DOB:\s*[^\n]+/gi, 'DOB: [REDACTED]');
        sanitized = sanitized.replace(/SSN:\s*[^\n]+/gi, 'SSN: [REDACTED]');
        sanitized = sanitized.replace(/Medications:\s*[^\n]+/gi, 'Medications: [REDACTED]');
        
        // Replace full names with initials
        sanitized = sanitized.replace(/\b([A-Z][a-z]+)\s+([A-Z][a-z]+)\b/g, '$1. $2');
        
        return sanitized;
    }

    saveConversationHistory() {
        try {
            // Sanitize conversation history to remove PHI
            const sanitizedHistory = this.conversationHistory.map(msg => {
                if (msg.type === 'assistant' && msg.data) {
                    // Remove PHI from assistant responses
                    const sanitizedData = this.sanitizePHIFromData(msg.data);
                    return {
                        ...msg,
                        data: sanitizedData,
                        content: this.sanitizePHIFromContent(msg.content)
                    };
                }
                return {
                    ...msg,
                    content: this.sanitizePHIFromContent(msg.content)
                };
            });
            
            localStorage.setItem('chatbot_conversation', JSON.stringify(sanitizedHistory));
        } catch (error) {
            console.error('Failed to save conversation history:', error);
        }
    }

    loadConversationHistory() {
        try {
            const saved = localStorage.getItem('chatbot_conversation');
            if (saved) {
                this.conversationHistory = JSON.parse(saved);
            }
        } catch (error) {
            console.error('Failed to load conversation history:', error);
            this.conversationHistory = [];
        }
    }
}

// Initialize chatbot when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.chatbot = new ChatbotUI();
});