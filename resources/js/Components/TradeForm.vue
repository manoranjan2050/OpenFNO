<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import PayoffChart from '@/Components/PayoffChart.vue';
import { toLocalInput } from '@/utils/format';

const props = defineProps({
    // existing trade object (API shape) for edit mode; null for create
    trade: { type: Object, default: null },
});

const emptyLeg = () => ({
    instrument_type: 'CE',
    expiry_date: '',
    strike: '',
    side: 'SELL',
    lots: 1,
    lot_size: '',
    entry_price: '',
});

const form = reactive({
    underlying: props.trade?.underlying ?? '',
    strategy_name: props.trade?.strategy_name ?? '',
    opened_at: props.trade ? toLocalInput(props.trade.opened_at) : toLocalInput(new Date().toISOString()),
    notes: props.trade?.notes ?? '',
    tagsInput: (props.trade?.tags ?? []).join(', '),
    legs: props.trade
        ? props.trade.legs.map((l) => ({
              instrument_type: l.instrument_type,
              expiry_date: l.expiry_date,
              strike: l.strike ?? '',
              side: l.side,
              lots: l.lots,
              lot_size: l.lot_size,
              entry_price: l.entry_price,
              exit_price: l.exit_price,
              exit_at: l.exit_at,
          }))
        : [emptyLeg()],
});

const errors = ref({});
const submitting = ref(false);

function addLeg() {
    // copy expiry/lot size from the previous leg — multi-leg strategies share them
    const prev = form.legs[form.legs.length - 1];
    form.legs.push({
        ...emptyLeg(),
        expiry_date: prev?.expiry_date ?? '',
        lot_size: prev?.lot_size ?? '',
    });
}

function removeLeg(index) {
    if (form.legs.length > 1) form.legs.splice(index, 1);
}

async function submit() {
    submitting.value = true;
    errors.value = {};

    const payload = {
        underlying: form.underlying.trim().toUpperCase(),
        strategy_name: form.strategy_name || null,
        // datetime-local is timezone-naive; send ISO so the backend stores UTC correctly
        opened_at: new Date(form.opened_at).toISOString(),
        notes: form.notes || null,
        tags: form.tagsInput
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean),
        legs: form.legs.map((l) => ({
            ...l,
            strike: l.instrument_type === 'FUT' ? null : l.strike,
        })),
    };

    try {
        const { data } = props.trade
            ? await window.axios.put(`/api/v1/trades/${props.trade.id}`, payload)
            : await window.axios.post('/api/v1/trades', payload);
        router.visit(route('trades.show', data.data.id));
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else {
            errors.value = { general: ['Something went wrong. Please try again.'] };
        }
    } finally {
        submitting.value = false;
    }
}

const inputClass =
    'w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
const labelClass = 'mb-1 block text-sm font-medium text-gray-600 dark:text-gray-400';
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div
            v-if="errors.general"
            class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400"
        >
            {{ errors.general[0] }}
        </div>

        <!-- Trade info -->
        <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label :class="labelClass">Underlying *</label>
                    <input
                        v-model="form.underlying"
                        list="underlyings"
                        required
                        placeholder="NIFTY"
                        :class="inputClass"
                    />
                    <datalist id="underlyings">
                        <option>NIFTY</option>
                        <option>BANKNIFTY</option>
                        <option>FINNIFTY</option>
                        <option>MIDCPNIFTY</option>
                        <option>SENSEX</option>
                    </datalist>
                    <p v-if="errors.underlying" class="mt-1 text-xs text-red-500">
                        {{ errors.underlying[0] }}
                    </p>
                </div>
                <div>
                    <label :class="labelClass">Strategy Name</label>
                    <input
                        v-model="form.strategy_name"
                        list="strategy-names"
                        placeholder="Iron Condor"
                        :class="inputClass"
                    />
                    <datalist id="strategy-names">
                        <option>Iron Condor</option>
                        <option>Short Straddle</option>
                        <option>Short Strangle</option>
                        <option>Bull Call Spread</option>
                        <option>Bear Put Spread</option>
                        <option>Calendar Spread</option>
                        <option>Naked Option</option>
                        <option>Directional Future</option>
                    </datalist>
                </div>
                <div>
                    <label :class="labelClass">Opened At *</label>
                    <input
                        v-model="form.opened_at"
                        type="datetime-local"
                        required
                        :class="inputClass"
                    />
                    <p v-if="errors.opened_at" class="mt-1 text-xs text-red-500">
                        {{ errors.opened_at[0] }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Legs -->
        <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Legs ({{ form.legs.length }})
                </h3>
                <button
                    type="button"
                    @click="addLeg"
                    class="rounded-md bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                >
                    + Add Leg
                </button>
            </div>
            <p v-if="errors.legs" class="mb-2 text-xs text-red-500">
                {{ errors.legs[0] }}
            </p>

            <div class="space-y-3">
                <div
                    v-for="(leg, i) in form.legs"
                    :key="i"
                    class="grid grid-cols-2 items-end gap-3 rounded-md border border-gray-200 p-3 sm:grid-cols-8 dark:border-gray-700"
                >
                    <div>
                        <label :class="labelClass">Side</label>
                        <select v-model="leg.side" :class="inputClass">
                            <option>BUY</option>
                            <option>SELL</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass">Type</label>
                        <select v-model="leg.instrument_type" :class="inputClass">
                            <option>CE</option>
                            <option>PE</option>
                            <option>FUT</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass">Strike</label>
                        <input
                            v-model="leg.strike"
                            type="number"
                            step="0.05"
                            min="0"
                            :disabled="leg.instrument_type === 'FUT'"
                            :required="leg.instrument_type !== 'FUT'"
                            :class="inputClass"
                            class="disabled:opacity-40"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">Expiry *</label>
                        <input
                            v-model="leg.expiry_date"
                            type="date"
                            required
                            :class="inputClass"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">Lots *</label>
                        <input
                            v-model.number="leg.lots"
                            type="number"
                            min="1"
                            required
                            :class="inputClass"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">Lot Size *</label>
                        <input
                            v-model.number="leg.lot_size"
                            type="number"
                            min="1"
                            required
                            placeholder="75"
                            :class="inputClass"
                        />
                    </div>
                    <div>
                        <label :class="labelClass">Entry ₹ *</label>
                        <input
                            v-model="leg.entry_price"
                            type="number"
                            step="0.05"
                            min="0"
                            required
                            :class="inputClass"
                        />
                    </div>
                    <div class="flex justify-end">
                        <button
                            type="button"
                            @click="removeLeg(i)"
                            :disabled="form.legs.length === 1"
                            class="rounded-md px-3 py-2 text-sm text-red-500 hover:bg-red-50 disabled:opacity-30 dark:hover:bg-red-900/20"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live payoff preview -->
        <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
            <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                Payoff Preview
            </h3>
            <PayoffChart :legs="form.legs" :height="260" />
        </div>

        <!-- Notes & tags -->
        <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
            <div class="space-y-4">
                <div>
                    <label :class="labelClass">Notes</label>
                    <textarea
                        v-model="form.notes"
                        rows="4"
                        placeholder="Setup, reasoning, adjustments…"
                        :class="inputClass"
                    ></textarea>
                </div>
                <div>
                    <label :class="labelClass">Tags (comma-separated)</label>
                    <input
                        v-model="form.tagsInput"
                        placeholder="expiry-play, hedged, weekly"
                        :class="inputClass"
                    />
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <button
                type="submit"
                :disabled="submitting"
                class="rounded-md bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
            >
                {{ submitting ? 'Saving…' : trade ? 'Update Trade' : 'Save Trade' }}
            </button>
        </div>
    </form>
</template>
