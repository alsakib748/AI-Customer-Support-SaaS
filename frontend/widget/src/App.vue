<!-- widget/src/App.vue -->

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import axios from 'axios';

// ============================================
// PROPS (from URL params)
// ============================================

const props = defineProps({
    widgetId: {
        type: String,
        required: true,
    },
});

// ============================================
// STATE
// ============================================

const isOpen = ref(false);
const isLoading = ref(false);
const isSending = ref(false);
const isClosed = ref(false);
const isOffline = ref(false);
const showCustomerForm = ref(false);
const hasUnread = ref(false);
const unreadCount = ref(0);
const messages = ref([]);
const newMessage = ref('');
const sessionToken = ref(null);
const visitorToken = ref(null);
const conversationId = ref(null);
const messagesContainer = ref(null);
const messagesEnd = ref(null);

// Widget Configuration
const widgetConfig = reactive({
    headerTitle: 'Chat with us',
    welcomeMessage: 'Hi! How can we help?',
    offlineMessage: null,
    position: 'bottom-right',
    primaryColor: '#4F46E5',
    logo: null,
    avatar: null,
    showBranding: true,
    requireName: false,
    requireEmail: false,
    requirePhone: false,
});

// Customer Form
const customerForm = reactive({
    name: '',
    email: '',
    phone: '',
});

// ============================================
// COMPUTED
// ============================================

const primaryColor = computed(() => widgetConfig.primaryColor || '#4F46E5');

const hasMessages = computed(() => messages.value.length > 0);

const statusClass = computed(() => {
    if (isOffline.value) return 'status-offline';
    return 'status-online';
});

const statusText = computed(() => {
    if (isOffline.value) return 'Offline';
    return 'Online';
});

const widgetStyles = computed(() => {
    const position = widgetConfig.position || 'bottom-right';
    const styles = {
        position: 'fixed',
        zIndex: 999999,
    };

    if (position.includes('bottom')) {
        styles.bottom = '20px';
    } else {
        styles.top = '20px';
    }

    if (position.includes('right')) {
        styles.right = '20px';
    } else {
        styles.left = '20px';
    }

    return styles;
});

// ============================================
// METHODS
// ============================================

const openWidget = () => {
    isOpen.value = true;
    hasUnread.value = false;
    unreadCount.value = 0;
    loadMessages();
};

const closeWidget = () => {
    isOpen.value = false;
};

const toggleWidget = () => {
    if (isOpen.value) {
        closeWidget();
    } else {
        openWidget();
    }
};

const formatTime = (timestamp) => {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesEnd.value) {
            messagesEnd.value.scrollIntoView({ behavior: 'smooth' });
        }
    });
};

// ============================================
// API METHODS
// ============================================

const bootstrapWidget = async () => {
    try {
        const response = await axios.post('/widget/bootstrap', {
            widget_id: props.widgetId,
            origin: window.location.origin,
        });

        if (response.data.success) {
            const config = response.data.data.widget;
            widgetConfig.headerTitle = config.header_title || 'Chat with us';
            widgetConfig.welcomeMessage = config.welcome_message;
            widgetConfig.offlineMessage = config.offline_message;
            widgetConfig.position = config.position || 'bottom-right';
            widgetConfig.primaryColor = config.primary_color || '#4F46E5';
            widgetConfig.logo = config.logo;
            widgetConfig.avatar = config.avatar;
            widgetConfig.showBranding = config.show_branding;
            widgetConfig.requireName = config.require_name || false;
            widgetConfig.requireEmail = config.require_email || false;
            widgetConfig.requirePhone = config.require_phone || false;

            showCustomerForm.value = widgetConfig.requireName || widgetConfig.requireEmail || widgetConfig.requirePhone;

            // Check if visitor token exists
            const savedVisitorToken = localStorage.getItem('widget_visitor_token');
            if (savedVisitorToken) {
                visitorToken.value = savedVisitorToken;
            }

            // Create session
            await createSession();
        }
    } catch (error) {
        console.error('Widget bootstrap failed:', error);
    }
};

const createSession = async () => {
    try {
        const response = await axios.post('/widget/session', {
            widget_id: props.widgetId,
            visitor_token: visitorToken.value || null,
            metadata: {
                page_url: window.location.href,
                referrer: document.referrer,
                language: navigator.language,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                screen: `${window.screen.width}x${window.screen.height}`,
                user_agent: navigator.userAgent,
            },
        });

        if (response.data.success) {
            const data = response.data.data;
            sessionToken.value = data.session_token;
            if (data.visitor_token) {
                visitorToken.value = data.visitor_token;
                localStorage.setItem('widget_visitor_token', data.visitor_token);
            }

            if (data.has_conversation) {
                await loadMessages();
            }
        }
    } catch (error) {
        console.error('Session creation failed:', error);
    }
};

const loadMessages = async () => {
    if (!sessionToken.value) return;

    isLoading.value = true;
    try {
        const response = await axios.get('/widget/messages', {
            params: {
                session_token: sessionToken.value,
                limit: 50,
            },
        });

        if (response.data.success) {
            messages.value = response.data.data || [];
            scrollToBottom();
        }
    } catch (error) {
        console.error('Load messages failed:', error);
    } finally {
        isLoading.value = false;
    }
};

const sendMessage = async () => {
    const content = newMessage.value.trim();
    if (!content || isSending.value || isClosed.value) return;

    // Check if customer info is required
    if (showCustomerForm.value) {
        const isValid = await submitCustomerInfo();
        if (!isValid) return;
    }

    isSending.value = true;

    try {
        const payload = {
            session_token: sessionToken.value,
            content: content,
        };

        // Add customer info if available
        if (customerForm.name) payload.name = customerForm.name;
        if (customerForm.email) payload.email = customerForm.email;
        if (customerForm.phone) payload.phone = customerForm.phone;

        const response = await axios.post('/widget/messages', payload);

        if (response.data.success) {
            newMessage.value = '';
            // Add message to list
            const newMsg = response.data.data.message;
            messages.value.push({
                id: newMsg.id,
                content: newMsg.content,
                created_at: newMsg.created_at,
                sender: {
                    type: 'customer',
                    name: customerForm.name || 'You',
                },
            });

            // Update conversation info
            if (response.data.data.conversation) {
                conversationId.value = response.data.data.conversation.id;
            }

            showCustomerForm.value = false;
            scrollToBottom();

            // Poll for new messages (agent responses)
            startPolling();
        }
    } catch (error) {
        console.error('Send message failed:', error);
    } finally {
        isSending.value = false;
    }
};

const submitCustomerInfo = async () => {
    // Validate required fields
    if (widgetConfig.requireName && !customerForm.name.trim()) {
        alert('Please enter your name.');
        return false;
    }

    if (widgetConfig.requireEmail && !customerForm.email.trim()) {
        alert('Please enter your email.');
        return false;
    }

    if (widgetConfig.requireEmail && customerForm.email && !isValidEmail(customerForm.email)) {
        alert('Please enter a valid email address.');
        return false;
    }

    if (widgetConfig.requirePhone && !customerForm.phone.trim()) {
        alert('Please enter your phone number.');
        return false;
    }

    // If we have a session, send the customer info
    if (sessionToken.value) {
        try {
            // Send a test message to register customer info
            const response = await axios.post('/widget/messages', {
                session_token: sessionToken.value,
                content: customerForm.name || 'Starting chat',
                name: customerForm.name,
                email: customerForm.email,
                phone: customerForm.phone,
            });

            if (response.data.success) {
                showCustomerForm.value = false;
                return true;
            }
        } catch (error) {
            console.error('Customer info submission failed:', error);
            return false;
        }
    }

    return true;
};

const isValidEmail = (email) => {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
};

// ============================================
// POLLING (Temp until WebSockets)
// ============================================

let pollingInterval = null;

const startPolling = () => {
    stopPolling();
    pollingInterval = setInterval(async () => {
        if (!sessionToken.value || !isOpen.value) return;

        try {
            const response = await axios.get('/widget/messages', {
                params: {
                    session_token: sessionToken.value,
                    limit: 50,
                },
            });

            if (response.data.success) {
                const newMessages = response.data.data || [];
                if (newMessages.length > messages.value.length) {
                    messages.value = newMessages;
                    scrollToBottom();

                    // Show notification if new message from agent
                    if (!document.hasFocus() && newMessages.length > 0) {
                        const lastMsg = newMessages[newMessages.length - 1];
                        if (lastMsg.sender.type === 'agent' || lastMsg.sender.type === 'ai') {
                            hasUnread.value = true;
                            unreadCount.value = newMessages.length - (messages.value.length - newMessages.length);
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    }, 3000);
};

const stopPolling = () => {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
};

// ============================================
// LIFECYCLE
// ============================================

onMounted(() => {
    bootstrapWidget();

    // Handle window focus for unread detection
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            hasUnread.value = false;
            unreadCount.value = 0;
        }
    });
});

onUnmounted(() => {
    stopPolling();
});

// Watch messages for scrolling
watch(() => messages.value.length, () => {
    scrollToBottom();
});
</script>

<template>
    <div class="chat-widget" :style="widgetStyles">
        <!-- Chat Button (Closed State) -->
        <div v-if="!isOpen" class="chat-button" @click="openWidget">
            <div class="chat-button-inner">
                <svg v-if="!hasUnread" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                </svg>
                <span v-else class="unread-badge">{{ unreadCount }}</span>
            </div>
        </div>

        <!-- Chat Window (Open State) -->
        <div v-else class="chat-window">
            <!-- Header -->
            <div class="chat-header" :style="{ backgroundColor: primaryColor }">
                <div class="chat-header-content">
                    <div class="chat-header-info">
                        <img v-if="avatar" :src="avatar" class="chat-avatar" />
                        <div class="chat-header-text">
                            <h3>{{ headerTitle }}</h3>
                            <span class="chat-status" :class="statusClass">
                                {{ statusText }}
                            </span>
                        </div>
                    </div>
                    <button class="chat-close" @click="closeWidget">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Messages -->
            <div class="chat-messages" ref="messagesContainer">
                <!-- Welcome Message -->
                <div v-if="!hasMessages && !isLoading" class="welcome-message">
                    <div class="welcome-icon">💬</div>
                    <p class="welcome-text">{{ welcomeMessage || 'Hi! How can we help you today?' }}</p>
                </div>

                <!-- Loading -->
                <div v-if="isLoading" class="loading-messages">
                    <div class="typing-indicator">
                        <span></span><span></span><span></span>
                    </div>
                </div>

                <!-- Messages -->
                <div v-for="message in messages" :key="message.id" class="message-wrapper">
                    <div class="message" :class="{
                        'message-customer': message.sender.type === 'customer',
                        'message-agent': message.sender.type === 'agent',
                        'message-ai': message.sender.type === 'ai',
                        'message-system': message.sender.type === 'system',
                    }">
                        <div class="message-content">{{ message.content }}</div>
                        <div class="message-time">{{ formatTime(message.created_at) }}</div>
                    </div>
                </div>

                <div ref="messagesEnd"></div>
            </div>

            <!-- Customer Info Form (if required) -->
            <div v-if="showCustomerForm" class="customer-form">
                <div class="form-group">
                    <label>Your Name</label>
                    <input v-model="customerForm.name" placeholder="Enter your name"
                        @keyup.enter="submitCustomerInfo" />
                </div>
                <div v-if="requireEmail" class="form-group">
                    <label>Email Address</label>
                    <input v-model="customerForm.email" type="email" placeholder="Enter your email"
                        @keyup.enter="submitCustomerInfo" />
                </div>
                <div v-if="requirePhone" class="form-group">
                    <label>Phone Number</label>
                    <input v-model="customerForm.phone" type="tel" placeholder="Enter your phone"
                        @keyup.enter="submitCustomerInfo" />
                </div>
                <button class="form-submit" @click="submitCustomerInfo">Start Chat</button>
            </div>

            <!-- Composer -->
            <div v-else class="chat-composer">
                <textarea v-model="newMessage" placeholder="Type a message..." rows="1"
                    @keydown.enter.prevent="sendMessage" :disabled="isSending || isClosed"></textarea>
                <button class="send-button" @click="sendMessage"
                    :disabled="!newMessage.trim() || isSending || isClosed">
                    <svg v-if="!isSending" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13" />
                        <polygon points="22 2 15 22 11 13 2 9 22 2" />
                    </svg>
                    <div v-else class="spinner"></div>
                </button>
            </div>

            <!-- Offline Message -->
            <div v-if="isOffline" class="offline-message">
                <p>{{ offlineMessage || 'Our team is currently offline. Please leave a message and we\'ll get back to
                you.' }}</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ============================================ */
/* WIDGET STYLES */
/* ============================================ */

.chat-widget {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
}

/* ============================================ */
/* CHAT BUTTON */
/* ============================================ */

.chat-button {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: v-bind(primaryColor);
    color: white;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chat-button:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 30px rgba(0, 0, 0, 0.25);
}

.chat-button-inner {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.unread-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #EF4444;
    color: white;
    font-size: 12px;
    font-weight: bold;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ============================================ */
/* CHAT WINDOW */
/* ============================================ */

.chat-window {
    width: 380px;
    max-height: 600px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* ============================================ */
/* HEADER */
/* ============================================ */

.chat-header {
    padding: 16px 20px;
    color: white;
    flex-shrink: 0;
}

.chat-header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chat-header-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.chat-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.3);
    object-fit: cover;
}

.chat-header-text h3 {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}

.chat-status {
    font-size: 12px;
    opacity: 0.8;
}

.status-online {
    color: #A7F3D0;
}

.status-offline {
    color: #FCA5A5;
}

.chat-close {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    opacity: 0.7;
    padding: 4px;
    border-radius: 4px;
    transition: opacity 0.2s ease;
}

.chat-close:hover {
    opacity: 1;
}

/* ============================================ */
/* MESSAGES */
/* ============================================ */

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px 20px;
    background: #F9FAFB;
    max-height: 400px;
}

/* Scrollbar */
.chat-messages::-webkit-scrollbar {
    width: 4px;
}

.chat-messages::-webkit-scrollbar-track {
    background: transparent;
}

.chat-messages::-webkit-scrollbar-thumb {
    background: #D1D5DB;
    border-radius: 2px;
}

/* ============================================ */
/* WELCOME MESSAGE */
/* ============================================ */

.welcome-message {
    text-align: center;
    padding: 40px 20px;
    color: #6B7280;
}

.welcome-icon {
    font-size: 48px;
    margin-bottom: 12px;
}

.welcome-text {
    font-size: 16px;
    line-height: 1.6;
}

/* ============================================ */
/* MESSAGE BUBBLES */
/* ============================================ */

.message-wrapper {
    margin-bottom: 12px;
}

.message {
    max-width: 85%;
    padding: 10px 14px;
    border-radius: 12px;
    word-wrap: break-word;
}

.message-customer {
    background: white;
    color: #111827;
    border-bottom-left-radius: 4px;
    margin-right: auto;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.message-agent {
    background: v-bind(primaryColor);
    color: white;
    border-bottom-right-radius: 4px;
    margin-left: auto;
}

.message-ai {
    background: #F3E8FF;
    color: #581C87;
    border-bottom-left-radius: 4px;
    margin-right: auto;
}

.message-system {
    background: #F3F4F6;
    color: #6B7280;
    text-align: center;
    max-width: 100%;
    font-size: 12px;
    border-radius: 8px;
}

.message-time {
    font-size: 10px;
    opacity: 0.6;
    margin-top: 4px;
}

.message-customer .message-time {
    text-align: left;
}

.message-agent .message-time {
    text-align: right;
}

/* ============================================ */
/* TYPING INDICATOR */
/* ============================================ */

.typing-indicator {
    display: flex;
    gap: 4px;
    padding: 12px 16px;
    background: white;
    border-radius: 12px;
    width: fit-content;
}

.typing-indicator span {
    width: 8px;
    height: 8px;
    background: #9CA3AF;
    border-radius: 50%;
    animation: typing 1.4s infinite both;
}

.typing-indicator span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {

    0%,
    60%,
    100% {
        transform: translateY(0);
        opacity: 0.4;
    }

    30% {
        transform: translateY(-8px);
        opacity: 1;
    }
}

/* ============================================ */
/* COMPOSER */
/* ============================================ */

.chat-composer {
    display: flex;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid #E5E7EB;
    background: white;
    flex-shrink: 0;
}

.chat-composer textarea {
    flex: 1;
    border: none;
    outline: none;
    resize: none;
    font-size: 14px;
    line-height: 1.5;
    font-family: inherit;
    padding: 8px 0;
    max-height: 80px;
}

.chat-composer textarea::placeholder {
    color: #9CA3AF;
}

.send-button {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: v-bind(primaryColor);
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: opacity 0.2s ease;
}

.send-button:hover:not(:disabled) {
    opacity: 0.8;
}

.send-button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* ============================================ */
/* SPINNER */
/* ============================================ */

.spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* ============================================ */
/* CUSTOMER FORM */
/* ============================================ */

.customer-form {
    padding: 16px 20px;
    background: white;
    border-top: 1px solid #E5E7EB;
}

.form-group {
    margin-bottom: 12px;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 4px;
}

.form-group input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #D1D5DB;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s ease;
}

.form-group input:focus {
    border-color: v-bind(primaryColor);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.form-submit {
    width: 100%;
    padding: 10px;
    background: v-bind(primaryColor);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s ease;
}

.form-submit:hover {
    opacity: 0.9;
}

/* ============================================ */
/* OFFLINE MESSAGE */
/* ============================================ */

.offline-message {
    padding: 12px 16px;
    background: #FEF2F2;
    color: #991B1B;
    text-align: center;
    font-size: 13px;
    border-top: 1px solid #FECACA;
}

/* ============================================ */
/* LOADING */
/* ============================================ */

.loading-messages {
    display: flex;
    justify-content: center;
    padding: 20px;
}

/* ============================================ */
/* RESPONSIVE */
/* ============================================ */

@media (max-width: 480px) {
    .chat-window {
        width: 100vw;
        max-height: 100vh;
        border-radius: 0;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        top: 0;
    }

    .chat-messages {
        max-height: none;
        flex: 1;
    }

    .chat-button {
        width: 56px;
        height: 56px;
    }
}
</style>
