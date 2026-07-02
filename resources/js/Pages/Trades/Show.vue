<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PayoffChart from '@/Components/PayoffChart.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { inr, pnlClass, fmtDate, fmtDateTime, toLocalInput } from '@/utils/format';

const props = defineProps({
    tradeId: { type: Number, required: true },
});

const trade = ref(null);
const showCloseModal = ref(false);
const closing = ref(false);
const closeError = ref('');
const closeForm = ref({ closed_at: '', legs: [] });
const uploadInput = ref(null);
const uploading = ref(false);

async function load() {
    const { data } = await window.axios.get(`/api/v1/trades/${props.tradeId}`);
    trade.value = data.data;
}

onMounted(load);

const netPnl = computed(() => {
    if (!trade.value) return null;
    return trade.value.status === 'closed'
        ? trade.value.realized_pnl
        : trade.value.booked_pnl;
});

function openCloseModal() {
    closeError.value = '';
    closeForm.value = {
        closed_at: toLocalInput(new Date().toISOString()),
        legs: trade.value.legs
            .filter((l) => l.is_open)
            .map((l) => ({ id: l.id, label: legDesc(l), exit_price: '' })),
    };
    showCloseModal.value = true;
}

function legDesc(leg) {
    const strike =
        leg.instrument_type === 'FUT'
            ? 'FUT'
            : `${Number(leg.strike)} ${leg.instrument_type}`;
    return `${leg.side} ${strike} × ${leg.lots} lot(s)`;
}

async function submitClose() {
    closing.value = true;
    closeError.value = '';
    try {
        await window.axios.post(`/api/v1/trades/${props.tradeId}/close`, {
            // datetime-local is timezone-naive; send ISO so the backend stores UTC correctly
            closed_at: new Date(closeForm.value.closed_at).toISOString(),
            legs: closeForm.value.legs.map((l) => ({
                id: l.id,
                exit_price: l.exit_price,
            })),
        });
        showCloseModal.value = false;
        await load();
    } catch (e) {
        closeError.value =
            e.response?.data?.message ?? 'Failed to close the trade.';
    } finally {
        closing.value = false;
    }
}

async function reopen() {
    await window.axios.post(`/api/v1/trades/${props.tradeId}/reopen`);
    await load();
}

async function destroy() {
    if (!confirm('Delete this trade? It can be restored from the database but not from the UI.')) return;
    await window.axios.delete(`/api/v1/trades/${props.tradeId}`);
    router.visit(route('trades.index'));
}

async function uploadScreenshot(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    uploading.value = true;
    try {
        const formData = new FormData();
        formData.append('file', file);
        await window.axios.post(
            `/api/v1/trades/${props.tradeId}/attachments`,
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );
        await load();
    } finally {
        uploading.value = false;
        if (uploadInput.value) uploadInput.value.value = '';
    }
}

async function deleteAttachment(id) {
    if (!confirm('Remove this screenshot?')) return;
    await window.axios.delete(`/api/v1/attachments/${id}`);
    await load();
}
</script>

<template>
    <Head :title="trade ? `${trade.underlying} Trade` : 'Trade'" />

    <AuthenticatedLayout>
        <template #header>
            <div v-if="trade" class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        {{ trade.underlying }}
                        <span
                            v-if="trade.strategy_name"
                            class="text-gray-500 dark:text-gray-400"
                        >
                            — {{ trade.strategy_name }}
                        </span>
                    </h2>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        :class="
                            trade.status === 'open'
                                ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                        "
                    >
                        {{ trade.status }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('trades.edit', trade.id)"
                        class="rounded-md bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        Edit
                    </Link>
                    <button
                        v-if="trade.status === 'open'"
                        @click="openCloseModal"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Close Trade
                    </button>
                    <button
                        v-else
                        @click="reopen"
                        class="rounded-md bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        Reopen
                    </button>
                    <button
                        @click="destroy"
                        class="rounded-md px-3 py-2 text-sm font-medium text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="!trade" class="text-gray-500 dark:text-gray-400">
                    Loading…
                </div>

                <template v-else>
                    <!-- Summary strip -->
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ trade.status === 'closed' ? 'Realized P&L' : 'Booked P&L' }}
                            </div>
                            <div class="text-xl font-bold" :class="pnlClass(netPnl)">
                                {{ inr(netPnl) }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Opened</div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                {{ fmtDateTime(trade.opened_at) }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Closed</div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                {{ trade.closed_at ? fmtDateTime(trade.closed_at) : '—' }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Tags</div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <span
                                    v-for="tag in trade.tags"
                                    :key="tag"
                                    class="rounded bg-indigo-100 px-1.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                                >
                                    {{ tag }}
                                </span>
                                <span
                                    v-if="trade.tags.length === 0"
                                    class="text-sm text-gray-400"
                                >—</span>
                            </div>
                        </div>
                    </div>

                    <!-- Legs table -->
                    <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400"
                                >
                                    <th class="px-4 py-3">Side</th>
                                    <th class="px-4 py-3">Instrument</th>
                                    <th class="px-4 py-3">Expiry</th>
                                    <th class="px-4 py-3 text-right">Qty</th>
                                    <th class="px-4 py-3 text-right">Entry</th>
                                    <th class="px-4 py-3 text-right">Exit</th>
                                    <th class="px-4 py-3 text-right">P&L</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="leg in trade.legs"
                                    :key="leg.id"
                                    class="border-b border-gray-100 last:border-0 dark:border-gray-700"
                                >
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded px-2 py-0.5 text-xs font-bold"
                                            :class="
                                                leg.side === 'BUY'
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'
                                                    : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'
                                            "
                                        >
                                            {{ leg.side }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                        {{ leg.instrument_type === 'FUT' ? 'Future' : `${Number(leg.strike)} ${leg.instrument_type}` }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        {{ fmtDate(leg.expiry_date) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">
                                        {{ leg.lots }} × {{ leg.lot_size }} = {{ leg.quantity }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-200">
                                        {{ inr(leg.entry_price) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-200">
                                        {{ leg.exit_price !== null ? inr(leg.exit_price) : 'open' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold" :class="pnlClass(leg.pnl)">
                                        {{ leg.pnl !== null ? inr(leg.pnl) : '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Payoff chart -->
                    <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Payoff at Expiry
                        </h3>
                        <PayoffChart :legs="trade.legs" />
                    </div>

                    <!-- Notes -->
                    <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                        <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Notes
                        </h3>
                        <p
                            class="whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300"
                        >{{ trade.notes || 'No notes.' }}</p>
                    </div>

                    <!-- Screenshots -->
                    <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Screenshots ({{ trade.attachments.length }})
                            </h3>
                            <label
                                class="cursor-pointer rounded-md bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                            >
                                {{ uploading ? 'Uploading…' : '+ Upload' }}
                                <input
                                    ref="uploadInput"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    :disabled="uploading"
                                    @change="uploadScreenshot"
                                />
                            </label>
                        </div>
                        <div
                            v-if="trade.attachments.length > 0"
                            class="grid grid-cols-2 gap-4 sm:grid-cols-4"
                        >
                            <div
                                v-for="att in trade.attachments"
                                :key="att.id"
                                class="group relative"
                            >
                                <a :href="att.url" target="_blank">
                                    <img
                                        :src="att.url"
                                        :alt="att.original_name"
                                        class="h-32 w-full rounded-md object-cover"
                                    />
                                </a>
                                <button
                                    @click="deleteAttachment(att.id)"
                                    class="absolute right-1 top-1 hidden rounded bg-black/60 px-1.5 py-0.5 text-xs text-white group-hover:block"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400">
                            No screenshots attached.
                        </p>
                    </div>
                </template>
            </div>
        </div>

        <!-- Close trade modal -->
        <Teleport to="body">
            <div
                v-if="showCloseModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
                @click.self="showCloseModal = false"
            >
                <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Close Trade — enter exit prices
                    </h3>

                    <div
                        v-if="closeError"
                        class="mb-3 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400"
                    >
                        {{ closeError }}
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-600 dark:text-gray-400">
                                Closed At
                            </label>
                            <input
                                v-model="closeForm.closed_at"
                                type="datetime-local"
                                class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            />
                        </div>
                        <div
                            v-for="leg in closeForm.legs"
                            :key="leg.id"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ leg.label }}
                            </span>
                            <input
                                v-model="leg.exit_price"
                                type="number"
                                step="0.05"
                                min="0"
                                required
                                placeholder="Exit ₹"
                                class="w-32 rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            />
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-3">
                        <button
                            @click="showCloseModal = false"
                            class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                        <button
                            @click="submitClose"
                            :disabled="closing || closeForm.legs.some((l) => l.exit_price === '')"
                            class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                        >
                            {{ closing ? 'Closing…' : 'Close Trade' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
