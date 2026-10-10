<script setup>
import { computed } from 'vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number, Boolean, Array, null], default: null }
});

const emit = defineEmits(['update:modelValue']);

const model = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
});

const numberModel = computed({
    get: () => props.modelValue,
    set: (value) => {
        if (value === '' || value === null || value === undefined) {
            emit('update:modelValue', null);
            return;
        }
        emit('update:modelValue', props.field.type === 'int' ? parseInt(value, 10) : parseFloat(value));
    }
});

const arrayText = computed({
    get: () => (Array.isArray(props.modelValue) ? props.modelValue.join(', ') : ''),
    set: (value) =>
        emit(
            'update:modelValue',
            String(value)
                .split(',')
                .map((v) => v.trim())
                .filter(Boolean)
        )
});

const hasOptions = computed(() => Array.isArray(props.field.options) && props.field.options.length > 0);
</script>

<template>
    <div>
        <!-- Boolean -->
        <div v-if="field.type === 'bool'" class="flex items-center gap-3">
            <ToggleSwitch v-model="model" />
            <span class="text-sm text-surface-600 dark:text-surface-300">{{ model ? 'Enabled' : 'Disabled' }}</span>
        </div>

        <!-- Text area -->
        <textarea
            v-else-if="field.type === 'text'"
            v-model="model"
            rows="3"
            class="w-full rounded-md border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 px-3 py-2 text-sm text-surface-900 dark:text-surface-0 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
        ></textarea>

        <!-- Select (options) -->
        <Select v-else-if="hasOptions && field.type !== 'array'" v-model="model" :options="field.options" optionLabel="label" optionValue="value" class="w-full" />

        <!-- Multi select array -->
        <Select v-else-if="field.type === 'array' && hasOptions" v-model="model" :options="field.options" optionLabel="label" optionValue="value" multiple class="w-full" />

        <!-- Array without options (comma separated) -->
        <InputText v-else-if="field.type === 'array'" v-model="arrayText" class="w-full" placeholder="Comma separated values" />

        <!-- Numeric -->
        <InputText v-else-if="field.type === 'int' || field.type === 'float'" v-model="numberModel" type="number" class="w-full" :min="field.min ?? undefined" :max="field.max ?? undefined" :step="field.type === 'float' ? '0.1' : '1'" />

        <!-- Email -->
        <InputText v-else-if="field.type === 'email'" v-model="model" type="email" class="w-full" />

        <!-- URL -->
        <InputText v-else-if="field.type === 'url'" v-model="model" type="url" class="w-full" />

        <!-- Default string -->
        <InputText v-else v-model="model" class="w-full" />
    </div>
</template>
