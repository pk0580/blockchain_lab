<script setup>
import { ref } from 'vue';

const nonce = ref(7);
const feeA = ref(10);
const feeB = ref(11);
const bump = ref(10);
const result = ref(null);
const error = ref(null);
const loading = ref(false);

async function simulate() {
    loading.value = true;
    error.value = null;
    try {
        const res = await fetch('/api/playground/nonce/simulate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                nonce: nonce.value,
                tx_a: { tag: 'A', priority_fee_gwei: feeA.value },
                tx_b: { tag: 'B', priority_fee_gwei: feeB.value },
                replacement_bump_percent: bump.value,
            }),
        });
        const json = await res.json();
        if (!res.ok) {
            error.value = json?.error?.message || JSON.stringify(json);
            return;
        }
        result.value = json.data;
    } catch (e) {
        error.value = String(e);
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="space-y-3 text-sm">
        <h3 class="text-base font-semibold">Nonce conflict</h3>
        <p class="text-slate-600 text-xs leading-relaxed">
            Two transactions from the same sender with the same nonce can't both be mined.
            By EIP-1559 mempool rules, the replacement must bump the priority fee by at
            least a configured percentage (Geth default is 10%). Play with the values to
            see when B can replace A.
        </p>

        <div class="space-y-2 text-xs">
            <div class="flex gap-2 items-center">
                <label class="w-24 text-slate-500">Nonce</label>
                <input v-model.number="nonce" type="number" min="0" class="flex-1 rounded border border-slate-300 px-2 py-1" />
            </div>
            <div class="flex gap-2 items-center">
                <label class="w-24 text-slate-500">A priority fee</label>
                <input v-model.number="feeA" type="number" min="0" step="0.5" class="flex-1 rounded border border-slate-300 px-2 py-1" />
                <span>gwei</span>
            </div>
            <div class="flex gap-2 items-center">
                <label class="w-24 text-slate-500">B priority fee</label>
                <input v-model.number="feeB" type="number" min="0" step="0.5" class="flex-1 rounded border border-slate-300 px-2 py-1" />
                <span>gwei</span>
            </div>
            <div class="flex gap-2 items-center">
                <label class="w-24 text-slate-500">Required bump</label>
                <input v-model.number="bump" type="number" min="0" max="200" class="flex-1 rounded border border-slate-300 px-2 py-1" />
                <span>%</span>
            </div>
        </div>

        <button
            type="button"
            class="px-3 py-1.5 rounded bg-blue-600 text-white text-xs font-medium hover:bg-blue-700 disabled:opacity-50"
            :disabled="loading"
            @click="simulate"
        >
            {{ loading ? 'Simulating…' : 'Simulate' }}
        </button>

        <p v-if="error" class="text-xs text-rose-700">{{ error }}</p>

        <div
            v-if="result"
            class="text-xs space-y-1 bg-white border border-slate-200 rounded p-3"
        >
            <div>
                Threshold:
                <span class="font-mono font-semibold">{{ result.threshold_priority_fee_gwei }}</span>
                gwei
            </div>
            <div class="font-semibold" :class="result.winner_tag === 'B' ? 'text-blue-700' : 'text-slate-700'">
                Winner: {{ result.winner_tag }}
            </div>
            <p class="text-slate-600">{{ result.verdict }}</p>
        </div>
    </div>
</template>
