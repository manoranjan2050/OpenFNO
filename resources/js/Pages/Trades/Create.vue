<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TradeForm from '@/Components/TradeForm.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="New Trade" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
            >
                New Trade
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <TradeForm />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
