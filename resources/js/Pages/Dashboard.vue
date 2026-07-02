<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { inr, pnlClass } from '@/utils/format';

const stats = ref(null);
const loading = ref(true);

onMounted(async () => {
    try {
        const { data } = await window.axios.get('/api/v1/stats');
        stats.value = data;
    } finally {
        loading.value = false;
    }
});

const equitySeries = computed(() => [
    {
        name: 'Cumulative P&L',
        data: (stats.value?.equity_curve ?? []).map((p) => ({
            x: p.date,
            y: p.pnl,
        })),
    },
]);

const equityOptions = {
    chart: {
        type: 'area',
        background: 'transparent',
        foreColor: '#9ca3af',
        toolbar: { show: false },
        zoom: { enabled: false },
    },
    theme: { mode: 'dark' },
    stroke: { curve: 'straight', width: 2 },
    fill: {
        type: 'gradient',
        gradient: { opacityFrom: 0.35, opacityTo: 0.02 },
    },
    colors: ['#34d399'],
    dataLabels: { enabled: false },
    xaxis: { type: 'datetime' },
    yaxis: {
        labels: {
            formatter: (v) => `₹${Number(v).toLocaleString('en-IN')}`,
        },
    },
    grid: { borderColor: '#374151' },
    tooltip: {
        y: { formatter: (v) => inr(v) },
    },
};

const underlyingRows = computed(() =>
    Object.entries(stats.value?.pnl_by_underlying ?? {}).sort(
        (a, b) => b[1] - a[1],
    ),
);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    Dashboard
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
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="loading" class="text-gray-500 dark:text-gray-400">
                    Loading stats…
                </div>

                <template v-else-if="stats">
                    <!-- Stat cards -->
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <StatCard
                            label="Total Realized P&L"
                            :value="inr(stats.total_pnl)"
                            :value-class="pnlClass(stats.total_pnl)"
                        />
                        <StatCard
                            label="Win Rate"
                            :value="stats.win_rate === null ? '—' : `${stats.win_rate}%`"
                        />
                        <StatCard label="Open Trades" :value="stats.open_trades" />
                        <StatCard
                            label="Closed Trades"
                            :value="stats.closed_trades"
                        />
                        <StatCard
                            label="Avg Win"
                            :value="inr(stats.avg_win)"
                            value-class="text-green-500"
                        />
                        <StatCard
                            label="Avg Loss"
                            :value="inr(stats.avg_loss)"
                            value-class="text-red-500"
                        />
                        <StatCard
                            label="Best Trade"
                            :value="inr(stats.best_trade)"
                            :value-class="pnlClass(stats.best_trade)"
                        />
                        <StatCard
                            label="Worst Trade"
                            :value="inr(stats.worst_trade)"
                            :value-class="pnlClass(stats.worst_trade)"
                        />
                    </div>

                    <!-- Equity curve -->
                    <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                        <h3
                            class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400"
                        >
                            Equity Curve (realized)
                        </h3>
                        <VueApexCharts
                            v-if="stats.equity_curve.length > 0"
                            type="area"
                            height="280"
                            :options="equityOptions"
                            :series="equitySeries"
                        />
                        <p v-else class="py-8 text-center text-gray-500">
                            Close your first trade to see the equity curve.
                        </p>
                    </div>

                    <!-- P&L by underlying -->
                    <div
                        v-if="underlyingRows.length > 0"
                        class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800"
                    >
                        <h3
                            class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400"
                        >
                            P&L by Underlying
                        </h3>
                        <table class="w-full text-sm">
                            <tbody>
                                <tr
                                    v-for="[underlying, pnl] in underlyingRows"
                                    :key="underlying"
                                    class="border-b border-gray-100 last:border-0 dark:border-gray-700"
                                >
                                    <td
                                        class="py-2 font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ underlying }}
                                    </td>
                                    <td
                                        class="py-2 text-right font-semibold"
                                        :class="pnlClass(pnl)"
                                    >
                                        {{ inr(pnl) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
