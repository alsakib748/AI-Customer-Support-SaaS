<!-- src/views/customers/CustomerShow.vue -->

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCustomerStore } from '@/stores/customer';
import CustomerDetails from '@/components/customers/CustomerDetails.vue';

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

const editCustomer = () => {
    router.push(`/customers/${route.params.id}/edit`);
};

onMounted(() => {
    loadCustomer();
});
</script>


<template>
    <div v-if="loading" class="flex justify-center py-12">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
    </div>
    <div v-else class="p-6 max-w-4xl mx-auto">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Customer Details</h1>
                <p class="text-surface-600">View customer information and history</p>
            </div>
            <Button icon="pi pi-arrow-left" label="Back" severity="secondary" outlined
                @click="router.push('/customers')" />
        </div>

        <div class="bg-white dark:bg-surface-900 rounded-lg shadow">
            <CustomerDetails :customer="customer" @edit="editCustomer" @close="router.push('/customers')" />
        </div>
    </div>
</template>
