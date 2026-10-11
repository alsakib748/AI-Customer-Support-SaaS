<!-- src/components/customers/CustomerForm.vue -->
<script setup>
import { reactive, ref, watch } from 'vue';
import { useCustomerStore } from '@/stores/customer';

const props = defineProps({
    customer: {
        type: Object,
        default: null
    },
    isEdit: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['submit', 'cancel']);

const customerStore = useCustomerStore();
const tagInput = ref('');

const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    company_name: '',
    status: 'active',
    tags: [],
    notes: ''
});

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
    { label: 'Blocked', value: 'blocked' }
];

const resetForm = () => {
    form.first_name = '';
    form.last_name = '';
    form.email = '';
    form.phone = '';
    form.company_name = '';
    form.status = 'active';
    form.tags = [];
    form.notes = '';
    tagInput.value = '';
};

const hydrate = (customer) => {
    if (!customer) {
        resetForm();
        return;
    }

    form.first_name = customer.first_name || '';
    form.last_name = customer.last_name || '';
    form.email = customer.email || '';
    form.phone = customer.phone || '';
    form.company_name = customer.company_name || '';
    form.status = customer.status || 'active';
    form.tags = [...(customer.tags || [])];
    form.notes = customer.notes || '';
};

watch(
    () => props.customer,
    (customer) => hydrate(customer),
    { immediate: true }
);

const addTag = () => {
    const tag = tagInput.value.trim();
    if (tag && !form.tags.includes(tag)) {
        form.tags.push(tag);
        tagInput.value = '';
    }
};

const removeTag = (tag) => {
    form.tags = form.tags.filter((t) => t !== tag);
};

const getFieldError = (field) => customerStore.getFieldError(field);

const handleSubmit = () => {
    emit('submit', {
        first_name: form.first_name,
        last_name: form.last_name,
        email: form.email || null,
        phone: form.phone || null,
        company_name: form.company_name || null,
        status: form.status,
        tags: form.tags,
        notes: form.notes || null
    });
};
</script>

<template>
    <form @submit.prevent="handleSubmit" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">First Name *</label>
                <InputText v-model="form.first_name" class="w-full" :class="{ 'p-invalid': getFieldError('first_name') }" placeholder="John" />
                <small v-if="getFieldError('first_name')" class="text-red-500">
                    {{ getFieldError('first_name') }}
                </small>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Last Name</label>
                <InputText v-model="form.last_name" class="w-full" placeholder="Smith" />
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <InputText v-model="form.email" type="email" class="w-full" :class="{ 'p-invalid': getFieldError('email') }" placeholder="john@example.com" />
            <small v-if="getFieldError('email')" class="text-red-500">
                {{ getFieldError('email') }}
            </small>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Phone</label>
            <InputText v-model="form.phone" class="w-full" placeholder="+1 234 567 8900" />
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Company</label>
            <InputText v-model="form.company_name" class="w-full" placeholder="ABC Corporation" />
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Status</label>
            <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full" />
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Tags</label>
            <div class="flex gap-2 mb-2">
                <InputText v-model="tagInput" placeholder="Add a tag" class="flex-1" @keydown.enter.prevent="addTag" />
                <Button icon="pi pi-plus" severity="secondary" type="button" @click="addTag" />
            </div>
            <div class="flex flex-wrap gap-2">
                <Chip v-for="tag in form.tags" :key="tag" :label="tag" removable @remove="removeTag(tag)" />
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Notes</label>
            <Textarea v-model="form.notes" class="w-full" rows="3" placeholder="Add notes about this customer..." />
        </div>

        <div v-if="getFieldError('general')" class="text-red-500 text-sm">
            {{ getFieldError('general') }}
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <Button label="Cancel" icon="pi pi-times" severity="secondary" type="button" @click="emit('cancel')" />
            <Button :label="isEdit ? 'Save' : 'Create'" icon="pi pi-save" severity="primary" type="submit" :loading="customerStore.saving" />
        </div>
    </form>
</template>
