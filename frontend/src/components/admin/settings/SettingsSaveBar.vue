<script setup>
defineProps({
    saving: { type: Boolean, default: false },
    dirty: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false }
});

defineEmits(['save', 'reset']);
</script>

<template>
    <div class="flex items-center justify-end gap-3 pt-2">
        <Button type="button" label="Reset" severity="secondary" outlined :disabled="!dirty || saving" @click="$emit('reset')" />
        <Button
            type="button"
            :label="saving ? 'Saving...' : 'Save Changes'"
            icon="pi pi-save"
            :loading="saving"
            :disabled="disabled || saving"
            @click="$emit('save')"
        />
    </div>
</template>
