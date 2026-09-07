// public/widget/chat.js

(function () {
    'use strict';

    // Configuration
    const CONFIG = {
        apiUrl: 'https://your-app.com/api/v1/widget',
        widgetUrl: 'https://your-app.com/widget/chat.js',
        defaultPosition: 'bottom-right',
        defaultTitle: 'Chat with us'
    };

    // Get widget ID from script tag
    function getWidgetId() {
        const scripts = document.querySelectorAll('script[data-widget-id]');
        for (let script of scripts) {
            if (script.src && script.src.includes('widget/chat.js')) {
                return script.getAttribute('data-widget-id');
            }
        }
        return null;
    }

    // Load widget iframe or custom element
    function loadWidget() {
        const widgetId = getWidgetId();
        if (!widgetId) {
            console.error('Chat Widget: Missing data-widget-id attribute');
            return;
        }

        // Create container
        const container = document.createElement('div');
        container.id = 'chat-widget-container';
        container.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 999999;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        `;
        document.body.appendChild(container);

        // Load widget application
        const iframe = document.createElement('iframe');
        iframe.src = `https://your-app.com/widget/app?widget_id=${widgetId}`;
        iframe.style.cssText = `
            width: 380px;
            height: 600px;
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            background: transparent;
            transition: all 0.3s ease;
            max-height: 100vh;
        `;
        iframe.allow = 'microphone; camera; autoplay';
        container.appendChild(iframe);

        // Handle resize
        window.addEventListener('message', function (event) {
            if (event.data && event.data.type === 'chat-widget-resize') {
                iframe.style.height = event.data.height + 'px';
            }
            if (event.data && event.data.type === 'chat-widget-toggle') {
                iframe.style.display = event.data.visible ? 'block' : 'none';
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadWidget);
    } else {
        loadWidget();
    }
})();
