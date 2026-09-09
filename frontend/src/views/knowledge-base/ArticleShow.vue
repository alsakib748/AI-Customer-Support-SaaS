<!-- src/views/knowledge-base/ArticleShow.vue -->
<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useKnowledgeBaseStore } from '@/stores/knowledgeBase';
import { useAuthStore } from '@/stores/auth';
// import { useToast } from 'primevue/usetoast';
import DOMPurify from 'dompurify';

const route = useRoute();
const router = useRouter();
const knowledgeBaseStore = useKnowledgeBaseStore();
const authStore = useAuthStore();
// const toast = useToast();

// ============================================
// STATE
// ============================================

const loading = ref(true);
const article = ref(null);

// ============================================
// COMPUTED
// ============================================

const canUpdateKnowledge = computed(() => authStore.hasPermission('knowledge.update'));

const sanitizedContent = computed(() => {
    if (!article.value?.content) return '';
    return DOMPurify.sanitize(article.value.content);
});

// ============================================
// METHODS
// ============================================

const loadArticle = async () => {
    loading.value = true;
    try {
        await knowledgeBaseStore.fetchArticle(route.params.id);
        article.value = knowledgeBaseStore.currentArticle;
    } catch (error) {
        console.error('Failed to load article:', error);
        // toast.error('Failed to load article');
        router.push('/knowledge-base');
    } finally {
        loading.value = false;
    }
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// ============================================
// LIFECYCLE
// ============================================

onMounted(() => {
    loadArticle();
});
</script>
<template>
    <div v-if="loading" class="flex justify-center py-12">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
    </div>
    <div v-else-if="article" class="p-6 max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <router-link to="/knowledge-base" class="text-surface-500 hover:text-primary">
                        <i class="pi pi-arrow-left mr-2" />
                        Back to Knowledge Base
                    </router-link>
                </div>
                <h1 class="text-2xl font-bold">{{ article.title }}</h1>
                <div class="flex items-center gap-3 mt-2">
                    <Tag :value="article.status_label" :severity="article.status_color" />
                    <Tag :value="article.visibility_label" :severity="article.visibility_color" />
                    <span class="text-sm text-surface-500">
                        {{ article.category?.name || 'Uncategorized' }}
                    </span>
                    <span class="text-sm text-surface-400">
                        {{ article.author_name || 'Unknown' }}
                    </span>
                </div>
            </div>
            <div class="flex gap-2">
                <Button v-if="canUpdateKnowledge" icon="pi pi-pencil" label="Edit" severity="warning"
                    @click="router.push(`/knowledge-base/articles/${article.id}/edit`)" />
                <Button icon="pi pi-arrow-left" label="Back" severity="secondary" outlined
                    @click="router.push('/knowledge-base')" />
            </div>
        </div>

        <!-- Content -->
        <div class="bg-white dark:bg-surface-900 rounded-lg shadow p-6 space-y-6">
            <!-- Excerpt -->
            <div v-if="article.excerpt" class="p-4 bg-surface-50 dark:bg-surface-800 rounded-lg">
                <p class="text-surface-600 dark:text-surface-300 italic">
                    {{ article.excerpt }}
                </p>
            </div>

            <!-- Content -->
            <div class="prose prose-lg dark:prose-invert max-w-none">
                <div v-html="sanitizedContent"></div>
            </div>

            <!-- Metadata -->
            <div class="border-t border-surface-200 dark:border-surface-700 pt-4 text-sm text-surface-500">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="font-medium">Created:</span>
                        {{ formatDate(article.created_at) }}
                    </div>
                    <div>
                        <span class="font-medium">Updated:</span>
                        {{ formatDate(article.updated_at) }}
                    </div>
                    <div v-if="article.published_at">
                        <span class="font-medium">Published:</span>
                        {{ formatDate(article.published_at) }}
                    </div>
                    <div>
                        <span class="font-medium">Visibility:</span>
                        {{ article.visibility_label }}
                    </div>
                </div>
            </div>
        </div>

        <Toast />
    </div>
</template>

<style scoped>
.prose {
    max-width: none;
}

.prose h1,
.prose h2,
.prose h3,
.prose h4 {
    color: var(--surface-900);
}

.prose a {
    color: var(--primary-color);
    text-decoration: underline;
}

.prose ul,
.prose ol {
    padding-left: 1.5rem;
}

.prose code {
    background: var(--surface-200);
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    font-size: 0.875em;
}

.prose pre {
    background: var(--surface-800);
    color: var(--surface-0);
    padding: 1rem;
    border-radius: 8px;
    overflow-x: auto;
}

.prose blockquote {
    border-left: 4px solid var(--primary-color);
    padding-left: 1rem;
    color: var(--surface-600);
}
</style>
