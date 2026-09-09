// src/stores/knowledgeBase.js

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import knowledgeBaseService from '@/services/knowledgeBaseService';
import { toast } from 'vue3-toastify';

export const useKnowledgeBaseStore = defineStore('knowledgeBase', () => {
    // ============================================
    // STATE
    // ============================================

    // Categories
    const categories = ref([]);
    const currentCategory = ref(null);

    // Articles
    const articles = ref([]);
    const currentArticle = ref(null);
    const statistics = ref({
        total: 0,
        draft: 0,
        published: 0,
        archived: 0,
        by_visibility: {},
        categories: 0,
        published_ai_eligible: 0
    });

    // UI State
    const pagination = ref(null);
    const loading = ref(false);
    const saving = ref(false);
    const errors = ref({});

    const filters = ref({
        search: '',
        category_id: null,
        status: null,
        visibility: null,
        sort: 'created_at',
        direction: 'desc',
        per_page: 20
    });

    const categoryFilters = ref({
        search: '',
        status: null,
        sort: 'sort_order',
        direction: 'asc',
        per_page: 20
    });

    // ============================================
    // GETTERS
    // ============================================

    const totalArticles = computed(() => pagination.value?.total || 0);
    const hasArticles = computed(() => articles.value.length > 0);
    const publishedCount = computed(() => statistics.value?.published || 0);
    const draftCount = computed(() => statistics.value?.draft || 0);
    const archivedCount = computed(() => statistics.value?.archived || 0);

    // ============================================
    // CATEGORY ACTIONS
    // ============================================

    const fetchCategories = async (params = {}) => {
        loading.value = true;
        errors.value = {};

        try {
            const mergedParams = { ...categoryFilters.value, ...params };

            Object.keys(mergedParams).forEach((key) => {
                if (mergedParams[key] === null || mergedParams[key] === undefined) {
                    delete mergedParams[key];
                }
            });

            const response = await knowledgeBaseService.getCategories(mergedParams);

            if (response.data.success) {
                categories.value = response.data.data || [];
                pagination.value = response.data.meta || null;
                return response.data;
            }
        } catch (error) {
            console.error('Failed to fetch categories:', error);
            toast.error('Failed to load categories');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchAllCategories = async () => {
        try {
            const response = await knowledgeBaseService.getAllCategories();

            if (response.data.success) {
                categories.value = response.data.data || [];
                return categories.value;
            }
        } catch (error) {
            console.error('Failed to fetch all categories:', error);
            toast.error('Failed to load categories');
            throw error;
        }
    };

    const fetchCategory = async (id) => {
        loading.value = true;

        try {
            const response = await knowledgeBaseService.getCategory(id);

            if (response.data.success) {
                currentCategory.value = response.data.data;
                return currentCategory.value;
            }
        } catch (error) {
            console.error('Failed to fetch category:', error);
            toast.error('Failed to load category');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const createCategory = async (data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await knowledgeBaseService.createCategory(data);

            if (response.data.success) {
                categories.value.unshift(response.data.data);
                toast.success(response.data.message || 'Category created successfully 🎉');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to create category:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            }

            toast.error('Failed to create category');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const updateCategory = async (id, data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await knowledgeBaseService.updateCategory(id, data);

            if (response.data.success) {
                const index = categories.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    categories.value[index] = response.data.data;
                }

                if (currentCategory.value?.id === id) {
                    currentCategory.value = response.data.data;
                }

                toast.success(response.data.message || 'Category updated successfully 🎉');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to update category:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            }

            toast.error('Failed to update category');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const deleteCategory = async (id) => {
        saving.value = true;

        try {
            const response = await knowledgeBaseService.deleteCategory(id);

            if (response.data.success) {
                categories.value = categories.value.filter((c) => c.id !== id);

                if (currentCategory.value?.id === id) {
                    currentCategory.value = null;
                }

                toast.success(response.data.message || 'Category deleted successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to delete category:', error);
            toast.error(error.response?.data?.message || 'Failed to delete category');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const activateCategory = async (id) => {
        saving.value = true;

        try {
            const response = await knowledgeBaseService.activateCategory(id);

            if (response.data.success) {
                const index = categories.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    categories.value[index] = response.data.data;
                }

                if (currentCategory.value?.id === id) {
                    currentCategory.value = response.data.data;
                }

                toast.success(response.data.message || 'Category activated successfully ✅');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to activate category:', error);
            toast.error('Failed to activate category');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const deactivateCategory = async (id) => {
        saving.value = true;

        try {
            const response = await knowledgeBaseService.deactivateCategory(id);

            if (response.data.success) {
                const index = categories.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    categories.value[index] = response.data.data;
                }

                if (currentCategory.value?.id === id) {
                    currentCategory.value = response.data.data;
                }

                toast.success(response.data.message || 'Category deactivated successfully 🔒');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to deactivate category:', error);
            toast.error('Failed to deactivate category');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    // ============================================
    // ARTICLE ACTIONS
    // ============================================

    const fetchArticles = async (params = {}) => {
        loading.value = true;
        errors.value = {};

        try {
            const mergedParams = { ...filters.value, ...params };

            Object.keys(mergedParams).forEach((key) => {
                if (mergedParams[key] === null || mergedParams[key] === undefined) {
                    delete mergedParams[key];
                }
            });

            const response = await knowledgeBaseService.getArticles(mergedParams);

            if (response.data.success) {
                articles.value = response.data.data || [];
                pagination.value = response.data.meta || null;
                return response.data;
            }
        } catch (error) {
            console.error('Failed to fetch articles:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load articles'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load articles');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchArticle = async (id) => {
        loading.value = true;
        errors.value = {};

        try {
            const response = await knowledgeBaseService.getArticle(id);

            if (response.data.success) {
                currentArticle.value = response.data.data;
                return currentArticle.value;
            }
        } catch (error) {
            console.error('Failed to fetch article:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load article'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load article');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const createArticle = async (data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await knowledgeBaseService.createArticle(data);

            if (response.data.success) {
                articles.value.unshift(response.data.data);
                toast.success(response.data.message || 'Article created successfully 🎉');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to create article:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to create article'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to create article');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const updateArticle = async (id, data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await knowledgeBaseService.updateArticle(id, data);

            if (response.data.success) {
                const index = articles.value.findIndex((a) => a.id === id);
                if (index !== -1) {
                    articles.value[index] = response.data.data;
                }

                if (currentArticle.value?.id === id) {
                    currentArticle.value = response.data.data;
                }

                toast.success(response.data.message || 'Article updated successfully 🎉');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to update article:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to update article'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to update article');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const deleteArticle = async (id) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await knowledgeBaseService.deleteArticle(id);

            if (response.data.success) {
                articles.value = articles.value.filter((a) => a.id !== id);

                if (currentArticle.value?.id === id) {
                    currentArticle.value = null;
                }

                toast.success(response.data.message || 'Article deleted successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to delete article:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to delete article'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to delete article');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const publishArticle = async (id) => {
        saving.value = true;

        try {
            const response = await knowledgeBaseService.publishArticle(id);

            if (response.data.success) {
                const updated = response.data.data;

                const index = articles.value.findIndex((a) => a.id === id);
                if (index !== -1) {
                    articles.value[index] = updated;
                }

                if (currentArticle.value?.id === id) {
                    currentArticle.value = updated;
                }

                toast.success(response.data.message || 'Article published successfully ✅');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to publish article:', error);
            toast.error(error.response?.data?.message || 'Failed to publish article');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const unpublishArticle = async (id) => {
        saving.value = true;

        try {
            const response = await knowledgeBaseService.unpublishArticle(id);

            if (response.data.success) {
                const updated = response.data.data;

                const index = articles.value.findIndex((a) => a.id === id);
                if (index !== -1) {
                    articles.value[index] = updated;
                }

                if (currentArticle.value?.id === id) {
                    currentArticle.value = updated;
                }

                toast.success(response.data.message || 'Article unpublished successfully 📝');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to unpublish article:', error);
            toast.error(error.response?.data?.message || 'Failed to unpublish article');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const archiveArticle = async (id) => {
        saving.value = true;

        try {
            const response = await knowledgeBaseService.archiveArticle(id);

            if (response.data.success) {
                const updated = response.data.data;

                const index = articles.value.findIndex((a) => a.id === id);
                if (index !== -1) {
                    articles.value[index] = updated;
                }

                if (currentArticle.value?.id === id) {
                    currentArticle.value = updated;
                }

                toast.success(response.data.message || 'Article archived successfully 📦');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to archive article:', error);
            toast.error(error.response?.data?.message || 'Failed to archive article');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const fetchStatistics = async () => {
        try {
            const response = await knowledgeBaseService.getStatistics();

            if (response.data.success) {
                statistics.value = response.data.data || statistics.value;
                return statistics.value;
            }
        } catch (error) {
            console.error('Failed to fetch statistics:', error);
            toast.error('Failed to load statistics');
            return null;
        }
    };

    const resetFilters = () => {
        filters.value = {
            search: '',
            category_id: null,
            status: null,
            visibility: null,
            sort: 'created_at',
            direction: 'desc',
            per_page: 20
        };
    };

    const resetCategoryFilters = () => {
        categoryFilters.value = {
            search: '',
            status: null,
            sort: 'sort_order',
            direction: 'asc',
            per_page: 20
        };
    };

    const getFieldError = (field) => {
        if (errors.value && errors.value[field]) {
            return Array.isArray(errors.value[field]) ? errors.value[field][0] : errors.value[field];
        }
        return null;
    };

    // ============================================
    // RETURN
    // ============================================

    return {
        // State
        categories,
        currentCategory,
        articles,
        currentArticle,
        statistics,
        pagination,
        loading,
        saving,
        errors,
        filters,
        categoryFilters,

        // Getters
        totalArticles,
        hasArticles,
        publishedCount,
        draftCount,
        archivedCount,

        // Category Actions
        fetchCategories,
        fetchAllCategories,
        fetchCategory,
        createCategory,
        updateCategory,
        deleteCategory,
        activateCategory,
        deactivateCategory,

        // Article Actions
        fetchArticles,
        fetchArticle,
        createArticle,
        updateArticle,
        deleteArticle,
        publishArticle,
        unpublishArticle,
        archiveArticle,
        fetchStatistics,

        // Utility
        resetFilters,
        resetCategoryFilters,
        getFieldError
    };
});
