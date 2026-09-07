// widget/src/main.js

import { createApp } from 'vue';
import App from './App.vue';
import axios from 'axios';

// Configure axios
axios.defaults.baseURL = 'https://your-app.com/api/v1';

// Get widget ID from URL
const urlParams = new URLSearchParams(window.location.search);
const widgetId = urlParams.get('widget_id');

if (!widgetId) {
    document.body.innerHTML = '<div style="padding:20px;text-align:center;color:#666;">Widget not configured properly</div>';
} else {
    const app = createApp(App, {
        widgetId: widgetId
    });
    app.mount('#app');
}
