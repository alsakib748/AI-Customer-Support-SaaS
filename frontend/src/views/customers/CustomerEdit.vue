<!-- src/views/customers/CustomerEdit.vue -->
<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCustomerStore } from '@/stores/customer';
import CustomerForm from '@/components/customers/CustomerForm.vue';

const route = useRoute();
const router = useRouter();
const customerStore = useCustomerStore();

const customer = ref(null);
const loading = ref(true);

const loadCustomer = async () => {
    try {
        loading.value = true;
        await customerStore.fetchCustomer(route.params.id);
        customer.value = customerStore.currentCustomer;
    } catch (error) {
        console.error('Failed to load customer:', error);
        router.push('/customers');
    } finally {
        loading.value = false;
    }
};

const handleSubmit = async (data) => {
    try {
        await customerStore.updateCustomer(route.params.id, data);
        router.push(`/customers/${route.params.id}`);
    } catch (error) {
        console.error('Failed to update customer:', error);
    }
};

const cancel = () => {
    router.push(`/customers/${route.params.id}`);
};

onMounted(() => {
    loadCustomer();
});
</script>


<template>
    <div v-if="loading" class="flex justify-center py-12">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
    </div>
    <div v-else class="p-6 max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Edit Customer</h1>
            <p class="text-surface-600">Update customer information</p>
        </div>

        <div class="bg-white dark:bg-surface-900 p-6 rounded-lg shadow">
            <CustomerForm :customer="customer" :is-edit="true" @submit="handleSubmit" @cancel="cancel" />
        </div>
    </div>
</template>
