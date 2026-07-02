<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { analyzeExpiryPayoff } from '@/payoff/engine';
import { inr } from '@/utils/format';

const props = defineProps({
    legs: { type: Array, required: true },
    height: { type: Number, default: 300 },
});

const analysis = computed(() => analyzeExpiryPayoff(props.legs));

const series = computed(() => [
    { name: 'P&L at expiry', data: analysis.value?.curve ?? [] },
]);

const options = computed(() => ({
    chart: {
        type: 'area',
        background: 'transparent',
        foreColor: '#9ca3af',
        toolbar: { show: false },
        zoom: { enabled: false },
        animations: { enabled: false },
    },
    theme: { mode: 'dark' },
    stroke: { curve: 'straight', width: 2 },
    colors: ['#818cf8'],
    dataLabels: { enabled: false },
    fill: {
        type: 'gradient',
        gradient: { opacityFrom: 0.25, opacityTo: 0.02 },
    },
    grid: { borderColor: '#374151' },
    xaxis: {
        type: 'numeric',
        title: { text: 'Underlying at expiry' },
        labels: {
            formatter: (v) => Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 }),
        },
        tooltip: { enabled: false },
    },
    yaxis: {
        title: { text: 'P&L (₹)' },
        labels: {
            formatter: (v) => `₹${Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`,
        },
    },
    tooltip: {
        x: {
            formatter: (v) => 'Spot ' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 }),
        },
        y: { formatter: (v) => inr(v) },
    },
    annotations: {
        yaxis: [
            {
                y: 0,
                borderColor: '#6b7280',
                strokeDashArray: 4,
            },
        ],
        xaxis: (analysis.value?.breakevens ?? []).map((be) => ({
            x: be,
            borderColor: '#f59e0b',
            strokeDashArray: 4,
            label: {
                text: `BE ${Number(be).toLocaleString('en-IN')}`,
                orientation: 'horizontal',
                style: {
                    background: '#f59e0b',
                    color: '#111827',
                    fontSize: '11px',
                },
            },
        })),
    },
}));

function fmtExtreme(v) {
    return v === 'unlimited' ? 'Unlimited' : inr(v);
}
</script>

<template>
    <div v-if="analysis">
        <div class="mb-3 flex flex-wrap gap-4 text-sm">
            <div>
                <span class="text-gray-500 dark:text-gray-400">Max Profit: </span>
                <span class="font-semibold text-green-500">
                    {{ fmtExtreme(analysis.maxProfit) }}
                </span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Max Loss: </span>
                <span class="font-semibold text-red-500">
                    {{ fmtExtreme(analysis.maxLoss) }}
                </span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Breakeven: </span>
                <span class="font-semibold text-amber-500">
                    {{
                        analysis.breakevens.length > 0
                            ? analysis.breakevens
                                  .map((b) => Number(b).toLocaleString('en-IN'))
                                  .join(' / ')
                            : '—'
                    }}
                </span>
            </div>
        </div>
        <VueApexCharts
            type="area"
            :height="height"
            :options="options"
            :series="series"
        />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
            Expiry payoff from entry prices. T+0 curve and Greeks arrive with live IV data (Phase 4/5).
        </p>
    </div>
    <p v-else class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
        Add at least one complete leg to see the payoff chart.
    </p>
</template>
