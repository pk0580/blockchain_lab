<script setup>
import { computed, onMounted, ref } from 'vue';

const data = ref(null);
const error = ref(null);

async function load(seed = 0) {
    try {
        const res = await fetch(`/api/playground/gas/chart?seed=${seed}`);
        const json = await res.json();
        if (!res.ok) {
            error.value = json?.error?.message || 'failed';
            return;
        }
        data.value = json.data;
        error.value = null;
    } catch (e) {
        error.value = String(e);
    }
}

const chart = computed(() => {
    if (!data.value?.samples) return null;
    const samples = data.value.samples;
    const maxSec = Math.max(...samples.map((s) => s.avg_seconds_to_inclusion));
    const maxFee = Math.max(...samples.map((s) => s.priority_fee_gwei));
    return { samples, maxSec, maxFee };
});

onMounted(() => load(0));
</script>

<template>
    <div class="space-y-3 text-sm">
        <h3 class="text-base font-semibold">Gas vs confirmation time</h3>
        <p class="text-slate-600 text-xs leading-relaxed">
            Higher priority fee → faster inclusion. The curve below is synthetic
            (exponential decay + noise), but the shape mirrors what real mainnet
            mempool data looks like.
        </p>

        <button
            type="button"
            class="px-3 py-1.5 rounded border border-slate-300 text-xs hover:bg-slate-100"
            @click="load(Math.floor(Math.random() * 10000))"
        >
            Re-roll noise
        </button>

        <p v-if="error" class="text-xs text-rose-700">{{ error }}</p>

        <div v-if="chart" class="bg-white border border-slate-200 rounded p-3">
            <svg viewBox="0 0 320 200" class="w-full h-48">
                <!-- axes -->
                <line x1="30" y1="180" x2="310" y2="180" stroke="#94a3b8" />
                <line x1="30" y1="20" x2="30" y2="180" stroke="#94a3b8" />

                <!-- bars -->
                <g
                    v-for="(s, idx) in chart.samples"
                    :key="idx"
                >
                    <rect
                        :x="30 + idx * (280 / chart.samples.length) + 1"
                        :y="180 - (s.avg_seconds_to_inclusion / chart.maxSec) * 150"
                        :width="(280 / chart.samples.length) - 2"
                        :height="(s.avg_seconds_to_inclusion / chart.maxSec) * 150"
                        fill="#3b82f6"
                        :opacity="0.65"
                    />
                </g>

                <!-- labels -->
                <text x="30" y="195" font-size="9" fill="#64748b">1</text>
                <text x="310" y="195" font-size="9" fill="#64748b" text-anchor="end">{{ chart.maxFee }} gwei</text>
                <text x="2" y="180" font-size="9" fill="#64748b">0s</text>
                <text x="2" y="25" font-size="9" fill="#64748b">{{ chart.maxSec.toFixed(0) }}s</text>
            </svg>
            <p class="text-[10px] text-slate-500 mt-2">{{ data.note }}</p>
        </div>
    </div>
</template>
