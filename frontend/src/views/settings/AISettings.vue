<!-- src/views/settings/AISettings.vue -->
<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useAIStore } from '@/stores/ai';
// import { useToast } from 'primevue/usetoast';

const aiStore = useAIStore();
// const toast = useToast();

// ============================================
// STATE
// ============================================

const showTestDialog = ref(false);
const testMessage = ref('');
const testResponse = ref('');

const form = reactive({
    provider: 'gemini',
    model: 'gemini-1.5-flash',
    enabled: true,
    auto_reply_enabled: true,
    auto_escalation_enabled: true,
    streaming_enabled: true,
    knowledge_base_enabled: true,
    temperature: 0.7,
    max_tokens: 2000,
    system_prompt: '',
    custom_instructions: '',
});

// ============================================
// COMPUTED
// ============================================

const loading = computed(() => aiStore.loading);
const saving = computed(() => aiStore.saving);
const testing = computed(() => aiStore.testing);
const health = computed(() => aiStore.health);

const statusOptions = [
    { label: 'Enabled', value: true },
    { label: 'Disabled', value: false },
];

const providerOptions = [
    { label: 'OpenAI', value: 'openai' },
    { label: 'Anthropic', value: 'anthropic' },
    { label: 'Google Gemini', value: 'gemini' },
];

const geminiModels = [
    { label: 'Gemini 3.6 Flash', value: 'gemini-3.6-flash' },
    { label: 'Gemini 1.5 Flash (Fast & Efficient)', value: 'gemini-1.5-flash' },
    { label: 'Gemini 1.5 Pro (Powerful)', value: 'gemini-1.5-pro' },
    { label: 'Gemini 1.0 Pro', value: 'gemini-1.0-pro' },
    { label: 'Gemini 2.0 Flash (Experimental)', value: 'gemini-2.0-flash-exp' },
];

const openaiModels = [
    { label: 'GPT-4o Mini', value: 'gpt-4o-mini' },
    { label: 'GPT-4o', value: 'gpt-4o' },
    { label: 'GPT-4 Turbo', value: 'gpt-4-turbo' },
];

const anthropicModels = [
    { label: 'Claude 3 Sonnet', value: 'claude-3-sonnet-20241022' },
    { label: 'Claude 3 Haiku', value: 'claude-3-haiku-20240307' },
];

const availableModels = computed(() => {
    switch (form.provider) {
        case 'gemini': return geminiModels;
        case 'openai': return openaiModels;
        case 'anthropic': return anthropicModels;
        default: return geminiModels;
    }
});

watch(() => form.provider, () => {
    const models = availableModels.value;
    if (!models.some((model) => model.value === form.model)) {
        form.model = models[0]?.value || '';
    }
});

// ============================================
// METHODS
// ============================================

/**
 * Safely format temperature value
 */
const formatTemperature = (value) => {
    const num = parseFloat(value);
    if (isNaN(num)) return '0.70';
    return num.toFixed(2);
};

/**
 * Safely format any number
 */
const formatNumber = (value, decimals = 2) => {
    const num = parseFloat(value);
    if (isNaN(num)) return '0';
    return num.toFixed(decimals);
};

const loadData = async () => {
    await Promise.all([
        aiStore.fetchConfiguration(),
        aiStore.fetchHealth(),
    ]);

    // Populate form with configuration
    const config = aiStore.configuration;
    Object.assign(form, {
        provider: config.provider || 'gemini',
        model: config.model || 'gemini-1.5-flash',
        enabled: config.enabled !== undefined ? Boolean(config.enabled) : true,
        auto_reply_enabled: config.auto_reply_enabled !== undefined ? Boolean(config.auto_reply_enabled) : true,
        auto_escalation_enabled: config.auto_escalation_enabled !== undefined ? Boolean(config.auto_escalation_enabled) : true,
        streaming_enabled: config.streaming_enabled !== undefined ? Boolean(config.streaming_enabled) : true,
        knowledge_base_enabled: config.knowledge_base_enabled !== undefined ? Boolean(config.knowledge_base_enabled) : true,
        //  Parse temperature as a number
        temperature: parseFloat(config.temperature) || 0.7,
        //  Parse max_tokens as an integer
        max_tokens: parseInt(config.max_tokens) || 2000,
        system_prompt: config.system_prompt || '',
        custom_instructions: config.custom_instructions || '',
    });
};



const saveConfiguration = async () => {
    try {
        // Ensure numbers are properly formatted before sending
        const dataToSave = {
            ...form,
            temperature: parseFloat(form.temperature) || 0.7,
            max_tokens: parseInt(form.max_tokens) || 2000,
        };

        await aiStore.updateConfiguration(dataToSave);
    } catch (error) {
        // Error handled in store
    }
};

const testAI = () => {
    testMessage.value = 'Hello, how can you help me?';
    testResponse.value = '';
    showTestDialog.value = true;
};

const handleTest = async () => {
    try {
        const result = await aiStore.testAI(testMessage.value);
        testResponse.value = result?.response || 'Test completed successfully!';
    } catch (error) {
        // Error handled in store
    }
};

const getFieldError = (field) => {
    return aiStore.getFieldError(field);
};




// ============================================
// LIFECYCLE
// ============================================

onMounted(() => {
    loadData();
});
</script>
<template>
    <div class="p-6 max-w-4xl mx-auto">
        <!-- Page Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">AI Settings</h1>
                <p class="text-surface-600 dark:text-surface-400">Configure AI assistant for your workspace</p>
            </div>
            <div class="flex gap-3">
                <Button label="Test AI" icon="pi pi-play" severity="secondary" outlined @click="testAI"
                    :loading="testing" />
                <Button label="Save" icon="pi pi-save" severity="primary" @click="saveConfiguration"
                    :loading="saving" />
            </div>
        </div>

        <!-- Health Status -->
        <div v-if="health.status" class="mb-6">
            <div class="flex items-center gap-3 p-4 rounded-lg" :class="{
                'bg-green-50 dark:bg-green-950': health.status === 'healthy',
                'bg-yellow-50 dark:bg-yellow-950': health.status === 'degraded',
                'bg-red-50 dark:bg-red-950': health.status === 'unhealthy',
            }">
                <i class="pi" :class="{
                    'pi-check-circle text-green-600': health.status === 'healthy',
                    'pi-exclamation-triangle text-yellow-600': health.status === 'degraded',
                    'pi-times-circle text-red-600': health.status === 'unhealthy',
                }" />
                <div>
                    <span class="font-medium">AI Status:</span>
                    <span class="ml-2">{{ health.status_label || health.status }}</span>
                    <span class="ml-4 text-sm text-surface-500">
                        Success Rate: {{ formatNumber(health.success_rate, 1) }}%
                    </span>
                    <span class="ml-4 text-sm text-surface-500">
                        Avg Response: {{ formatNumber(health.avg_response_time, 0) }}ms
                    </span>
                </div>
            </div>
        </div>

        <!-- Configuration Form -->
        <div class="bg-white dark:bg-surface-900 rounded-lg shadow p-6 space-y-6">
            <!-- General Settings -->
            <div class="border-b border-surface-200 dark:border-surface-700 pb-6">
                <h3 class="text-lg font-semibold mb-4">General Settings</h3>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Status</label>
                        <Select v-model="form.enabled" :options="statusOptions" optionLabel="label" optionValue="value"
                            class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Provider</label>
                        <Select v-model="form.provider" :options="providerOptions" optionLabel="label"
                            optionValue="value" class="w-full" />
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium mb-1">Model</label>
                    <Select v-model="form.model" :options="availableModels" optionLabel="label" optionValue="value"
                        class="w-full" />
                </div>
            </div>

            <!-- AI Behavior -->
            <div class="border-b border-surface-200 dark:border-surface-700 pb-6">
                <h3 class="text-lg font-semibold mb-4">AI Behavior</h3>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="font-medium">Auto Reply</label>
                            <p class="text-sm text-surface-500">Automatically respond to customer messages</p>
                        </div>
                        <ToggleSwitch v-model="form.auto_reply_enabled" />
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label class="font-medium">Auto Escalation</label>
                            <p class="text-sm text-surface-500">Escalate to human when AI cannot answer</p>
                        </div>
                        <ToggleSwitch v-model="form.auto_escalation_enabled" />
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label class="font-medium">Streaming</label>
                            <p class="text-sm text-surface-500">Stream AI responses in real-time</p>
                        </div>
                        <ToggleSwitch v-model="form.streaming_enabled" />
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label class="font-medium">Knowledge Base</label>
                            <p class="text-sm text-surface-500">Use knowledge base for responses</p>
                        </div>
                        <ToggleSwitch v-model="form.knowledge_base_enabled" />
                    </div>
                </div>
            </div>

            <!-- Model Parameters -->
            <div class="border-b border-surface-200 dark:border-surface-700 pb-6">
                <h3 class="text-lg font-semibold mb-4">Model Parameters</h3>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Temperature</label>
                        <div class="flex items-center gap-4">
                            <Slider v-model="form.temperature" :min="0" :max="2" :step="0.01" class="flex-1" />
                            <!-- <span class="text-sm font-mono w-12">{{ form.temperature.toFixed(2) }}</span> -->
                            <span class="text-sm font-mono w-12">{{ formatTemperature(form.temperature) }}</span>
                        </div>
                        <p class="text-xs text-surface-400 mt-1">Higher = more creative, Lower = more focused</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Max Tokens</label>
                        <InputNumber v-model="form.max_tokens" :min="1" :max="8000" class="w-full" />
                        <p class="text-xs text-surface-400 mt-1">Maximum length of AI response</p>
                    </div>
                </div>
            </div>

            <!-- Prompts -->
            <div>
                <h3 class="text-lg font-semibold mb-4">Custom Prompts</h3>

                <div>
                    <label class="block text-sm font-medium mb-1">System Prompt</label>
                    <Textarea v-model="form.system_prompt" class="w-full" rows="4"
                        placeholder="Default system prompt will be used if empty" />
                    <p class="text-xs text-surface-400 mt-1">Instructions that define the AI's behavior</p>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium mb-1">Custom Instructions</label>
                    <Textarea v-model="form.custom_instructions" class="w-full" rows="3"
                        placeholder="Additional instructions for the AI" />
                    <p class="text-xs text-surface-400 mt-1">Tenant-specific instructions appended to system prompt</p>
                </div>
            </div>
        </div>

        <!-- Test Dialog -->
        <Dialog v-model:visible="showTestDialog" header="Test AI" :style="{ width: '500px' }" modal>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Test Message</label>
                    <Textarea v-model="testMessage" class="w-full" rows="3" placeholder="Type a test message..." />
                </div>

                <div v-if="testResponse" class="p-4 bg-surface-100 dark:bg-surface-800 rounded-lg">
                    <div class="text-sm font-medium mb-2">AI Response:</div>
                    <div class="text-sm">{{ testResponse }}</div>
                </div>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" severity="secondary" @click="showTestDialog = false" />
                <Button label="Send" icon="pi pi-send" severity="primary" :loading="testing" @click="handleTest"
                    :disabled="!testMessage.trim()" />
            </template>
        </Dialog>

        <!-- <Toast /> -->
    </div>
</template>

<style scoped>
:deep(.p-slider) {
    width: 100%;
}
</style>
