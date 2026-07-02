<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import { inr, pnlClass, fmtDate, legLabel } from '@/utils/format';

const trades = ref([]);
const meta = ref(null);
const loading = ref(true);
const filter = ref('all'); // all | open | closed
const page = ref(1);

async function load() {
    loading.value = true;
    try {
        const params = { page: page.value };
        if (filter.value !== 'all') params.status = filter.value;
        const { data } = await window.axios.get('/api/v1/trades', { params });
        trades.value = data.data;
        meta.value = data.meta;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(filter, () => {
    page.value = 1;
    load();
});
watch(page, load);

function tradePnl(trade) {
    return trade.status === 'closed' ? trade.realized_pnl : trade.booked_pnl;
}

const filters = [
    { key: 'all', label: 'All' },
    { key: 'open', label: 'Open' },
    { key: 'closed', label: 'Closed' },
];
</script>

<template>
    <Head title="Journal" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    Trade Journal
                </h2>
                <Link
                    :href="route('trades.create')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    + New Trade
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <!-- Filter tabs -->
                <div class="flex gap-2">
                    <button
                        v-for="f in filters"
                        :key="f.key"
                        @click="filter = f.key"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                        :class="
                            filter === f.key
                                ? 'bg-indigo-600 text-white'
                                : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700'
                        "
                    >
                        {{ f.label }}
                    </button>
                </div>

                <div
                    class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800"
                >
                    <div
                        v-if="loading"
                        class="p-8 text-center text-gray-500 dark:text-gray-400"
                    >
                        Loading trades…
                    </div>

                    <div
                        v-else-if="trades.length === 0"
                        class="p-12 text-center text-gray-500 dark:text-gray-400"
                    >
                        No trades yet.
                        <Link
                            :href="route('trades.create')"
                            class="text-indigo-500 hover:underline"
                        >
                            Log your first trade →
                        </Link>
                    </div>

                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400"
                            >
                                <th class="px-4 py-3">Underlying</th>
                                <th class="px-4 py-3">Strategy</th>
                                <th class="px-4 py-3">Legs</th>
                                <th class="px-4 py-3">Opened</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">P&L</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="trade in trades"
                                :key="trade.id"
                                @click="router.visit(route('trades.show', trade.id))"
                                class="cursor-pointer border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50"
                            >
                                <td
                                    class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    {{ trade.underlying }}
                                </td>
                                <td
                                    class="px-4 py-3 text-gray-600 dark:text-gray-300"
                                >
                                    {{ trade.strategy_name || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="leg in trade.legs"
                                            :key="leg.id"
                                            class="rounded px-1.5 py-0.5 text-xs font-medium"
                                            :class="
                                                leg.side === 'BUY'
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'
                                                    : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'
                                            "
                                        >
                                            {{ legLabel(leg) }}
                                        </span>
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 text-gray-600 dark:text-gray-300"
                                >
                                    {{ fmtDate(trade.opened_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="
                                            trade.status === 'open'
                                                ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400'
                                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                        "
                                    >
                                        {{ trade.status }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-semibold"
                                    :class="pnlClass(tradePnl(trade))"
                                >
                                    {{ inr(tradePnl(trade)) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="meta && meta.last_page > 1"
                    class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400"
                >
                    <button
                        :disabled="page <= 1"
                        @click="page--"
                        class="rounded px-3 py-1 disabled:opacity-40 hover:bg-gray-200 dark:hover:bg-gray-700"
                    >
                        ← Prev
                    </button>
                    <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
                    <button
                        :disabled="page >= meta.last_page"
                        @click="page++"
                        class="rounded px-3 py-1 disabled:opacity-40 hover:bg-gray-200 dark:hover:bg-gray-700"
                    >
                        Next →
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
