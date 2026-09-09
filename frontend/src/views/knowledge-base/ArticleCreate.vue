<!-- src/views/knowledge-base/ArticleCreate.vue -->
<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useKnowledgeBaseStore } from '@/stores/knowledgeBase';
// import { useToast } from 'primevue/usetoast';

const router = useRouter();
const knowledgeBaseStore = useKnowledgeBaseStore();
// const toast = useToast();

// ============================================
// STATE
// ============================================

const form = reactive({
    title: '',
    category_id: null,
    excerpt: '',
    content: '',
    visibility: 'ai',
});

// ============================================
// COMPUTED
// ============================================

const saving = computed(() => knowledgeBaseStore.saving);
const categories = computed(() => knowledgeBaseStore.categories);

const visibilityOptions = [
    { label: 'AI Only', value: 'ai' },
    { label: 'Public', value: 'public' },
    { label: 'Internal', value: 'internal' },
];

// ============================================
// METHODS
// ============================================

const loadCategories = async () => {
    await knowledgeBaseStore.fetchAllCategories();
};

const handleSubmit = async () => {
    try {
        await knowledgeBaseStore.createArticle(form);
        // toast.success('Article created successfully 🎉');
        router.push('/knowledge-base');
    } catch (error) {
        // Error handled in store
    }
};

const getFieldError = (field) => {
    return knowledgeBaseStore.getFieldError(field);
};

// ============================================
// LIFECYCLE
// ============================================

onMounted(() => {
    loadCategories();
});
</script>
<template>
    <div class="p-6 max-w-4xl mx-auto">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Create Article</h1>
                <p class="text-surface-600">Add a new knowledge base article</p>
            </div>
            <Button icon="pi pi-arrow-left" label="Back" severity="secondary" outlined
                @click="router.push('/knowledge-base')" />
        </div>

        <div class="bg-white dark:bg-surface-900 rounded-lg shadow p-6">
            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Title *</label>
                    <InputText v-model="form.title" class="w-full" :class="{ 'p-invalid': getFieldError('title') }"
                        placeholder="Article title" />
                    <small v-if="getFieldError('title')" class="text-red-500">
                        {{ getFieldError('title') }}
                    </small>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Category</label>
                    <Select v-model="form.category_id" :options="categories" optionLabel="name" optionValue="id"
                        class="w-full" placeholder="Select Category" filter />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Excerpt</label>
                    <Textarea v-model="form.excerpt" class="w-full" rows="2"
                        placeholder="Brief summary of the article" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Content *</label>
                    <Textarea v-model="form.content" class="w-full" rows="10"
                        placeholder="Write the article content here..."
                        :class="{ 'p-invalid': getFieldError('content') }" />
                    <small v-if="getFieldError('content')" class="text-red-500">
                        {{ getFieldError('content') }}
                    </small>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Visibility *</label>
                    <Select v-model="form.visibility" :options="visibilityOptions" optionLabel="label"
                        optionValue="value" class="w-full" :class="{ 'p-invalid': getFieldError('visibility') }"
                        placeholder="Select Visibility" />
                    <small v-if="getFieldError('visibility')" class="text-red-500">
                        {{ getFieldError('visibility') }}
                    </small>
                </div>

                <div v-if="getFieldError('general')" class="text-red-500 text-sm">
                    {{ getFieldError('general') }}
                </div>

                <div class="flex gap-3 pt-4">
                    <Button type="submit" label="Create Article" icon="pi pi-plus" severity="primary"
                        :loading="saving" />
                    <Button type="button" label="Cancel" icon="pi pi-times" severity="secondary" outlined
                        @click="router.push('/knowledge-base')" />
                </div>
            </form>
        </div>

        <Toast />
    </div>
</template>
