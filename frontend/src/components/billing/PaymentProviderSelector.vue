<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: null },
    providers: { type: Array, default: () => [] },
});

defineEmits(['update:modelValue']);

const availableProviders = computed(() => props.providers || []);
</script>


<template>
    <div class="provider-selector">
        <h3 class="text-lg font-semibold mb-3">Choose payment method</h3>
        <div class="space-y-2">
            <label v-for="p in availableProviders" :key="p.key"
                class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-all" :class="{
                    'border-primary ring-2 ring-primary': modelValue === p.key,
                    'border-surface-200 dark:border-surface-700 hover:border-primary': modelValue !== p.key,
                    'opacity-50 cursor-not-allowed': !p.available,
                }">
                <RadioButton :value="p.key" :modelValue="modelValue" :disabled="!p.available"
                    @update:modelValue="$emit('update:modelValue', $event)" />
                <span class="font-medium">{{ p.name }}</span>
                <span v-if="!p.available" class="text-xs text-surface-500 ml-auto">Unavailable</span>
            </label>
        </div>
    </div>
</template>
