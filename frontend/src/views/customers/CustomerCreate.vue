<script setup>
import { useRouter } from 'vue-router';
import { useCustomerStore } from '@/stores/customer';
import CustomerForm from '@/components/customers/CustomerForm.vue';

const router = useRouter();
const customerStore = useCustomerStore();

const handleSubmit = async (data) => {
    try {
        await customerStore.createCustomer(data);
        router.push('/customers');
    } catch (error) {
        console.error('Failed to create customer:', error);
    }
};

const cancel = () => {
    router.push('/customers');
};
</script>


<!-- src/views/customers/CustomerCreate.vue -->
<template>
    <div class="p-6 max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Add New Customer</h1>
            <p class="text-surface-600">Create a new customer in your workspace</p>
        </div>

        <div class="bg-white dark:bg-surface-900 p-6 rounded-lg shadow">
            <CustomerForm :is-edit="false" @submit="handleSubmit" @cancel="cancel" />
        </div>
    </div>
</template>
