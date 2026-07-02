<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TradeForm from '@/Components/TradeForm.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    tradeId: { type: Number, required: true },
});

const trade = ref(null);

onMounted(async () => {
    const { data } = await window.axios.get(`/api/v1/trades/${props.tradeId}`);
    trade.value = data.data;
});
</script>

<template>
    <Head title="Edit Trade" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
            >
                Edit Trade
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div v-if="!trade" class="text-gray-500 dark:text-gray-400">
                    Loading…
                </div>
                <TradeForm v-else :trade="trade" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
