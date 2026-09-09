<!-- src/views/knowledge-base/ArticleEdit.vue -->

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useKnowledgeBaseStore } from '@/stores/knowledgeBase';
// import { useToast } from 'primevue/usetoast';

const route = useRoute();
const router = useRouter();
const knowledgeBaseStore = useKnowledgeBaseStore();
// const toast = useToast();

// ============================================
// STATE
// ============================================

const loading = ref(true);
const article = ref(null);

const form = reactive({
    id: null,
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

const loadData = async () => {
    loading.value = true;
    try {
        await Promise.all([
            knowledgeBaseStore.fetchAllCategories(),
            knowledgeBaseStore.fetchArticle(route.params.id),
        ]);

        article.value = knowledgeBaseStore.currentArticle;

        if (article.value) {
            form.id = article.value.id;
            form.title = article.value.title || '';
            form.category_id = article.value.category?.id || null;
            form.excerpt = article.value.excerpt || '';
            form.content = article.value.content || '';
            form.visibility = article.value.visibility || 'ai';
        }
    } catch (error) {
        console.error('Failed to load article:', error);
        router.push('/knowledge-base');
    } finally {
        loading.value = false;
    }
};

const handleSubmit = async () => {
    try {
        const data = {
            title: form.title,
            category_id: form.category_id,
            excerpt: form.excerpt,
            content: form.content,
            visibility: form.visibility,
        };

        await knowledgeBaseStore.updateArticle(route.params.id, data);
        // toast.success('Article updated successfully 🎉');
        router.push('/knowledge-base');
    } catch (error) {
        // Error handled in store
    }
};

const publishArticle = async () => {
    try {
        await knowledgeBaseStore.publishArticle(route.params.id);
        // toast.success('Article published successfully ✅');
        await loadData();
    } catch (error) {
        // Error handled in store
    }
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getFieldError = (field) => {
    return knowledgeBaseStore.getFieldError(field);
};

// ============================================
// LIFECYCLE
// ============================================

onMounted(() => {
    loadData();
});
</script>
<template>
    <div v-if="loading" class="flex justify-center py-12">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
    </div>
    <div v-else class="p-6 max-w-4xl mx-auto">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Edit Article</h1>
                <p class="text-surface-600">{{ article?.title }}</p>
            </div>
            <div class="flex gap-2">
                <Button icon="pi pi-eye" label="View" severity="info" outlined
                    @click="router.push(`/knowledge-base/articles/${article.id}`)" />
                <Button icon="pi pi-arrow-left" label="Back" severity="secondary" outlined
                    @click="router.push('/knowledge-base')" />
            </div>
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

                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium">Status:</span>
                        <Tag :value="article?.status_label" :severity="article?.status_color" />
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium">Published:</span>
                        <span class="text-sm">{{ formatDate(article?.published_at) || 'Not published' }}</span>
                    </div>
                </div>

                <div v-if="getFieldError('general')" class="text-red-500 text-sm">
                    {{ getFieldError('general') }}
                </div>

                <div class="flex gap-3 pt-4">
                    <Button type="submit" label="Save Changes" icon="pi pi-save" severity="primary" :loading="saving" />
                    <Button v-if="article?.status !== 'published'" type="button" label="Publish" icon="pi pi-check"
                        severity="success" @click="publishArticle" />
                    <Button type="button" label="Cancel" icon="pi pi-times" severity="secondary" outlined
                        @click="router.push('/knowledge-base')" />
                </div>
            </form>
        </div>

        <Toast />
    </div>
</template>
