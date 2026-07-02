<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
    valueClass: { type: String, default: 'text-gray-900 dark:text-gray-100' },
});
</script>

<template>
    <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
            {{ label }}
        </div>
        <div class="mt-1 text-2xl font-semibold" :class="valueClass">
            {{ value }}
        </div>
    </div>
</template>
