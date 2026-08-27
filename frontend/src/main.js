import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';

import Aura from '@primeuix/themes/aura';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';

import Vue3Toastify from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

import '@/assets/tailwind.css';
import '@/assets/styles.scss';

const pinia = createPinia();

const app = createApp(App);

app.use(router);
app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            darkModeSelector: '.app-dark'
        }
    }
});
app.use(ToastService);
app.use(ConfirmationService);
app.use(pinia);
app.use(Vue3Toastify, {
    autoClose: 3000,
    position: 'top-right',
    theme: 'colored' // Options: 'light', 'dark', 'colored'
});
app.mount('#app');
