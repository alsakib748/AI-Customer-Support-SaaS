<!-- src/components/ai/AIConfigurationForm.vue -->
<script setup>
import { reactive, watch, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const form = reactive({
    provider: 'openai',
    model: 'gpt-4o-mini',
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

// Initialize from props
watch(() => props.modelValue, (value) => {
    if (value) {
        Object.assign(form, value);
    }
}, { immediate: true });

// Emit changes
watch(form, () => {
    emit('update:modelValue', { ...form });
}, { deep: true });

const statusOptions = [
    { label: 'Enabled', value: true },
    { label: 'Disabled', value: false },
];

const providerOptions = [
    { label: 'Google Gemini (Recommended)', value: 'gemini' },
    { label: 'OpenAI', value: 'openai' },
    { label: 'Anthropic', value: 'anthropic' },
];

const geminiModels = [
    { label: 'Gemini 1.5 Flash (Fast & Efficient)', value: 'gemini-1.5-flash' },
    { label: 'Gemini 1.5 Pro (Powerful)', value: 'gemini-1.5-pro' },
    { label: 'Gemini 1.0 Pro', value: 'gemini-1.0-pro' },
    { label: 'Gemini 2.0 Flash (Experimental)', value: 'gemini-2.0-flash-exp' },
];

const openaiModels = [
    { label: 'GPT-4o Mini', value: 'gpt-4o-mini' },
    { label: 'GPT-4o', value: 'gpt-4o' },
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

</script>

<template>
    <div class="space-y-6">

        <!-- Provider Selection -->
        <div>
            <label class="block text-sm font-medium mb-1">AI Provider</label>
            <Select v-model="form.provider" :options="providerOptions" optionLabel="label" optionValue="value"
                class="w-full" />
            <p class="text-xs text-surface-400 mt-1">
                Gemini is recommended for best performance and cost
            </p>
        </div>

        <!-- Model Selection based on provider -->
        <div>
            <label class="block text-sm font-medium mb-1">Model</label>
            <Select v-model="form.model" :options="availableModels" optionLabel="label" optionValue="value"
                class="w-full" />
        </div>


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
                    <Select v-model="form.provider" :options="providerOptions" optionLabel="label" optionValue="value"
                        class="w-full" />
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium mb-1">Model</label>
                <InputText v-model="form.model" class="w-full" placeholder="gpt-4o-mini" />
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
                        <span class="text-sm font-mono w-12">{{ form.temperature.toFixed(2) }}</span>
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
</template>
