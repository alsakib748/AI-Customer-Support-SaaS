<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    configured: { type: Boolean, default: false },
    cleared: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Enter a new value' }
});

defineEmits(['update:modelValue', 'update:cleared']);
</script>

<template>
    <div>
        <InputText
            type="password"
            :modelValue="modelValue"
            :disabled="cleared"
            autocomplete="new-password"
            :placeholder="cleared ? 'Value will be removed on save' : placeholder"
            class="w-full"
            @update:modelValue="$emit('update:modelValue', $event)"
        />

        <div class="flex items-center gap-2 mt-1">
            <span v-if="configured && !cleared" class="text-xs font-medium px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300"> Configured </span>
            <span v-else-if="cleared" class="text-xs font-medium px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300"> Will be removed </span>
            <span v-else class="text-xs text-surface-500 dark:text-surface-400"> Not configured </span>

            <Button v-if="configured && !cleared" type="button" text size="small" label="Clear" severity="danger" @click="$emit('update:cleared', true)" />
            <Button v-if="cleared" type="button" text size="small" label="Undo" severity="secondary" @click="$emit('update:cleared', false)" />
        </div>
    </div>
</template>
