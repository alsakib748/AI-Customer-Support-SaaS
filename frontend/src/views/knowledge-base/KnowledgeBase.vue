<!-- src/views/knowledge-base/KnowledgeBase.vue -->
<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useKnowledgeBaseStore } from '@/stores/knowledgeBase';
import { useAuthStore } from '@/stores/auth';
// import { useToast } from 'primevue/usetoast';

const router = useRouter();
const knowledgeBaseStore = useKnowledgeBaseStore();
const authStore = useAuthStore();
// const toast = useToast();

// ============================================
// STATE
// ============================================

const showCategoryDialog = ref(false);
const categoryFormMode = ref('create');
const selectedCategoryId = ref(null);
const categorySearch = ref('');
const sortOrder = ref(1);

const filters = reactive({
    search: '',
    status: null,
    visibility: null,
    sort: 'created_at',
    direction: 'desc',
    per_page: 20,
});

const categoryForm = reactive({
    id: null,
    name: '',
    description: '',
    status: 'active',
    sort_order: 0,
});

// ============================================
// COMPUTED
// ============================================

const loading = computed(() => knowledgeBaseStore.loading);
const saving = computed(() => knowledgeBaseStore.saving);
const articles = computed(() => knowledgeBaseStore.articles);
const categories = computed(() => knowledgeBaseStore.categories);
const totalArticles = computed(() => knowledgeBaseStore.totalArticles);
const publishedCount = computed(() => knowledgeBaseStore.publishedCount);
const draftCount = computed(() => knowledgeBaseStore.draftCount);
const archivedCount = computed(() => knowledgeBaseStore.archivedCount);

const canCreateKnowledge = computed(() => authStore.hasPermission('knowledge.create'));
const canUpdateKnowledge = computed(() => authStore.hasPermission('knowledge.update'));
const canDeleteKnowledge = computed(() => authStore.hasPermission('knowledge.delete'));

const statusOptions = [
    { label: 'All', value: null },
    { label: 'Draft', value: 'draft' },
    { label: 'Published', value: 'published' },
    { label: 'Archived', value: 'archived' },
];

const visibilityOptions = [
    { label: 'All', value: null },
    { label: 'AI Only', value: 'ai' },
    { label: 'Public', value: 'public' },
    { label: 'Internal', value: 'internal' },
];

const categoryStatusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
];

// ============================================
// METHODS
// ============================================

const loadData = async () => {
    await Promise.all([
        knowledgeBaseStore.fetchArticles({ ...filters }),
        knowledgeBaseStore.fetchCategories({ search: categorySearch.value }),
        knowledgeBaseStore.fetchStatistics(),
    ]);
};

const refreshData = () => {
    loadData();
};

const applyFilters = () => {
    knowledgeBaseStore.filters = { ...filters };
    knowledgeBaseStore.fetchArticles();
};

const clearFilters = () => {
    Object.assign(filters, {
        search: '',
        status: null,
        visibility: null,
        sort: 'created_at',
        direction: 'desc',
        per_page: 20,
    });
    selectedCategoryId.value = null;
    applyFilters();
};

const filterByCategory = (categoryId) => {
    selectedCategoryId.value = categoryId;
    filters.category_id = categoryId;
    applyFilters();
};

const onPageChange = (event) => {
    filters.per_page = event.rows;
    knowledgeBaseStore.fetchArticles({ ...filters });
};

const onSortChange = (event) => {
    filters.sort = event.sortField;
    filters.direction = event.sortOrder === 1 ? 'asc' : 'desc';
    applyFilters();
};

const loadCategories = () => {
    knowledgeBaseStore.fetchCategories({ search: categorySearch.value });
};

const viewArticle = (article) => {
    router.push(`/knowledge-base/articles/${article.id}`);
};

const editArticle = (article) => {
    router.push(`/knowledge-base/articles/${article.id}/edit`);
};

const publishArticle = async (article) => {
    try {
        await knowledgeBaseStore.publishArticle(article.id);
        await loadData();
        // toast.success('Article published successfully ✅');
    } catch (error) {
        // Error handled in store
    }
};

const unpublishArticle = async (article) => {
    try {
        await knowledgeBaseStore.unpublishArticle(article.id);
        await loadData();
        // toast.success('Article unpublished successfully 📝');
    } catch (error) {
        // Error handled in store
    }
};

const archiveArticle = async (article) => {
    if (!confirm('Are you sure you want to archive this article?')) return;
    try {
        await knowledgeBaseStore.archiveArticle(article.id);
        await loadData();
        // toast.success('Article archived successfully 📦');
    } catch (error) {
        // Error handled in store
    }
};

const confirmDeleteArticle = (article) => {
    if (confirm(`Are you sure you want to delete the article "${article.title}"?`)) {
        knowledgeBaseStore.deleteArticle(article.id);
    }
};

const openCreateCategory = () => {
    categoryFormMode.value = 'create';
    resetCategoryForm();
    showCategoryDialog.value = true;
};

const editCategory = (category) => {
    categoryFormMode.value = 'edit';
    categoryForm.id = category.id;
    categoryForm.name = category.name;
    categoryForm.description = category.description || '';
    categoryForm.status = category.status || 'active';
    categoryForm.sort_order = category.sort_order || 0;
    showCategoryDialog.value = true;
};

const resetCategoryForm = () => {
    categoryForm.id = null;
    categoryForm.name = '';
    categoryForm.description = '';
    categoryForm.status = 'active';
    categoryForm.sort_order = 0;
};

const handleCategorySubmit = async () => {
    try {
        const data = {
            name: categoryForm.name,
            description: categoryForm.description,
            status: categoryForm.status,
            sort_order: categoryForm.sort_order,
        };

        if (categoryFormMode.value === 'create') {
            await knowledgeBaseStore.createCategory(data);
            // toast.success('Category created successfully 🎉');
        } else {
            await knowledgeBaseStore.updateCategory(categoryForm.id, data);
            // toast.success('Category updated successfully 🎉');
        }

        showCategoryDialog.value = false;
        resetCategoryForm();
        await loadCategories();
    } catch (error) {
        // Error handled in store
    }
};

const confirmDeleteCategory = (category) => {
    if (confirm(`Are you sure you want to delete the category "${category.name}"?`)) {
        knowledgeBaseStore.deleteCategory(category.id);
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

watch(() => filters.search, () => {
    applyFilters();
});
</script>
<template>
    <div class="p-6">
        <!-- Page Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">Knowledge Base</h1>
                <p class="text-surface-600 dark:text-surface-400">Manage support articles and documentation</p>
            </div>
            <div class="flex gap-3">
                <Button v-if="canCreateKnowledge" label="New Article" icon="pi pi-plus" severity="primary"
                    @click="router.push('/knowledge-base/articles/create')" />
                <Button label="Refresh" icon="pi pi-refresh" severity="secondary" outlined @click="refreshData"
                    :loading="loading" />
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">{{ totalArticles }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Total Articles</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-success">{{ publishedCount }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Published</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-secondary">{{ draftCount }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Draft</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-danger">{{ archivedCount }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Archived</div>
                    </div>
                </template>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Sidebar - Categories -->
            <div class="lg:col-span-1">
                <div class="bg-surface-0 dark:bg-surface-900 rounded-lg shadow p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-surface-900 dark:text-surface-0">Categories</h3>
                        <Button v-if="canCreateKnowledge" icon="pi pi-plus" severity="primary" text rounded size="small"
                            @click="showCategoryDialog = true" tooltip="New Category" />
                    </div>

                    <div class="mb-3">
                        <InputText v-model="categorySearch" placeholder="Search categories..." class="w-full"
                            size="small" @input="loadCategories" />
                    </div>

                    <div class="space-y-1 max-h-[400px] overflow-y-auto">
                        <div v-for="category in categories" :key="category.id"
                            class="flex items-center justify-between p-2 rounded-lg cursor-pointer hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                            :class="{ 'bg-primary-50 dark:bg-primary-950': selectedCategoryId === category.id }"
                            @click="filterByCategory(category.id)">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium">{{ category.name }}</span>
                                <span class="text-xs text-surface-400">({{ category.articles_count }})</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <Tag :value="category.status_label" :severity="category.status_color" size="small" />
                                <Button v-if="canUpdateKnowledge" icon="pi pi-pencil" severity="secondary" text rounded
                                    size="small" @click.stop="editCategory(category)" />
                                <Button v-if="canDeleteKnowledge" icon="pi pi-trash" severity="danger" text rounded
                                    size="small" @click.stop="confirmDeleteCategory(category)" />
                            </div>
                        </div>

                        <div v-if="!categories.length" class="text-center text-surface-400 text-sm py-4">
                            No categories found
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content - Articles -->
            <div class="lg:col-span-3">
                <!-- Filters -->
                <div class="mb-4 flex flex-wrap gap-3 items-center">
                    <div class="flex-1 min-w-[200px]">
                        <InputText v-model="filters.search" placeholder="Search articles..." class="w-full"
                            @input="applyFilters" />
                    </div>
                    <div class="w-40">
                        <Select v-model="filters.status" :options="statusOptions" optionLabel="label"
                            optionValue="value" placeholder="Status" class="w-full" @change="applyFilters" clearable />
                    </div>
                    <div class="w-40">
                        <Select v-model="filters.visibility" :options="visibilityOptions" optionLabel="label"
                            optionValue="value" placeholder="Visibility" class="w-full" @change="applyFilters"
                            clearable />
                    </div>
                    <Button icon="pi pi-times" label="Clear" severity="secondary" outlined @click="clearFilters" />
                </div>

                <!-- Articles Table -->
                <DataTable :value="articles" :loading="loading" paginator :rows="filters.per_page"
                    :totalRecords="totalArticles" :lazy="true" @page="onPageChange" @sort="onSortChange" class="w-full"
                    v-model:sortField="filters.sort" v-model:sortOrder="sortOrder">
                    <Column field="title" header="Title" sortable>
                        <template #body="{ data }">
                            <div>
                                <div class="font-medium">{{ data.title }}</div>
                                <div class="text-sm text-surface-500">{{ data.excerpt || 'No excerpt' }}</div>
                            </div>
                        </template>
                    </Column>

                    <Column field="category.name" header="Category" sortable>
                        <template #body="{ data }">
                            {{ data.category?.name || 'Uncategorized' }}
                        </template>
                    </Column>

                    <Column field="status" header="Status" sortable>
                        <template #body="{ data }">
                            <Tag :value="data.status_label" :severity="data.status_color" />
                        </template>
                    </Column>

                    <Column field="visibility" header="Visibility" sortable>
                        <template #body="{ data }">
                            <Tag :value="data.visibility_label" :severity="data.visibility_color" />
                        </template>
                    </Column>

                    <Column field="author_name" header="Author" sortable>
                        <template #body="{ data }">
                            {{ data.author_name || '—' }}
                        </template>
                    </Column>

                    <Column field="published_at" header="Published" sortable>
                        <template #body="{ data }">
                            {{ formatDate(data.published_at) }}
                        </template>
                    </Column>

                    <Column header="Actions" style="width: 250px">
                        <template #body="{ data }">
                            <div class="flex gap-1 flex-wrap">
                                <Button icon="pi pi-eye" severity="info" text rounded @click="viewArticle(data)"
                                    tooltip="View" />
                                <Button v-if="canUpdateKnowledge" icon="pi pi-pencil" severity="warning" text rounded
                                    @click="editArticle(data)" tooltip="Edit" />
                                <Button v-if="canUpdateKnowledge && data.status !== 'published'" icon="pi pi-check"
                                    severity="success" text rounded @click="publishArticle(data)" tooltip="Publish" />
                                <Button v-if="canUpdateKnowledge && data.status === 'published'" icon="pi pi-times"
                                    severity="secondary" text rounded @click="unpublishArticle(data)"
                                    tooltip="Unpublish" />
                                <Button v-if="canUpdateKnowledge && data.status !== 'archived'" icon="pi pi-folder"
                                    severity="secondary" text rounded @click="archiveArticle(data)" tooltip="Archive" />
                                <Button v-if="canDeleteKnowledge" icon="pi pi-trash" severity="danger" text rounded
                                    @click="confirmDeleteArticle(data)" tooltip="Delete" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <!-- Category Dialog -->
        <Dialog v-model:visible="showCategoryDialog"
            :header="categoryFormMode === 'create' ? 'New Category' : 'Edit Category'" :style="{ width: '500px' }"
            modal>
            <form @submit.prevent="handleCategorySubmit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Name *</label>
                    <InputText v-model="categoryForm.name" class="w-full"
                        :class="{ 'p-invalid': getFieldError('name') }" placeholder="Category name" />
                    <small v-if="getFieldError('name')" class="text-red-500">
                        {{ getFieldError('name') }}
                    </small>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <Textarea v-model="categoryForm.description" class="w-full" rows="2"
                        placeholder="Category description" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Status</label>
                    <Select v-model="categoryForm.status" :options="categoryStatusOptions" optionLabel="label"
                        optionValue="value" class="w-full" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Sort Order</label>
                    <InputNumber v-model="categoryForm.sort_order" :min="0" class="w-full" />
                </div>
            </form>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" severity="secondary" @click="showCategoryDialog = false" />
                <Button :label="categoryFormMode === 'create' ? 'Create' : 'Save'" icon="pi pi-save" severity="primary"
                    :loading="saving" @click="handleCategorySubmit" />
            </template>
        </Dialog>

        <Toast />
    </div>
</template>

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
    background: var(--surface-ground);
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
    background: var(--surface-hover);
}

.max-h-\[400px\] {
    max-height: 400px;
}
</style>