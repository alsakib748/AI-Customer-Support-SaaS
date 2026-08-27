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
// import { closeButton } from '@primeuix/themes/aura/galleria';

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
    autoClose: false,
    // autoClose: 5000,
    position: 'top-right',
    theme: 'colored',
    // extra for manual close
    closeOnClick: true,
    closeButton: true,
    draggable: true,
    pauseOnHover: true,
    pauseOnFocusLoss: true
});
app.mount('#app');
