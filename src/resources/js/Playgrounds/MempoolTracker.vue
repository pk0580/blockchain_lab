<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const state = ref(null);
const error = ref(null);
const polling = ref(false);
let timer = null;

async function tick() {
    try {
        const res = await fetch('/api/playground/regtest/mempool');
        const json = await res.json();
        if (!res.ok) {
            error.value = json?.error?.message || 'failed';
            polling.value = false;
            return;
        }
        state.value = json.data;
        error.value = null;
    } catch (e) {
        error.value = String(e);
        polling.value = false;
    }
}

function start() {
    if (timer) return;
    polling.value = true;
    tick();
    timer = setInterval(tick, 2000);
}

function stop() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
    polling.value = false;
}

onMounted(start);
onUnmounted(stop);
</script>

<template>
    <div class="space-y-3 text-sm">
        <h3 class="text-base font-semibold">Mempool tracker</h3>
        <p class="text-slate-600 text-xs leading-relaxed">
            Polls the regtest node every 2 seconds via <code>getrawmempool</code> and shows
            pending tx hashes plus current block height. Open a terminal and run
            <code>bitcoin-cli -regtest sendtoaddress …</code> — you'll see the new txid pop in
            here within two seconds, then disappear once it gets mined.
        </p>

        <div class="flex gap-2">
            <button
                type="button"
                class="px-3 py-1.5 rounded bg-blue-600 text-white text-xs font-medium hover:bg-blue-700"
                @click="start"
                v-if="!polling"
            >
                Start polling
            </button>
            <button
                type="button"
                class="px-3 py-1.5 rounded border border-slate-300 text-xs hover:bg-slate-100"
                @click="stop"
                v-else
            >
                Stop polling
            </button>
            <button
                type="button"
                class="px-3 py-1.5 rounded border border-slate-300 text-xs hover:bg-slate-100"
                @click="tick"
            >
                Refresh now
            </button>
        </div>

        <p v-if="error" class="text-xs text-rose-700 break-words">{{ error }}</p>

        <div v-if="state" class="space-y-2">
            <div class="flex gap-4 text-xs">
                <div>
                    <span class="text-slate-500">Block height:</span>
                    <span class="font-mono font-semibold">{{ state.block_count }}</span>
                </div>
                <div>
                    <span class="text-slate-500">Pending:</span>
                    <span class="font-mono font-semibold">{{ state.mempool_size }}</span>
                </div>
            </div>

            <div v-if="state.mempool_size > 0" class="space-y-1">
                <div
                    v-for="txid in state.txids"
                    :key="txid"
                    class="font-mono text-[10px] break-all bg-white border border-slate-200 rounded p-1.5"
                >
                    {{ txid }}
                </div>
            </div>
            <p v-else class="text-xs text-slate-500 italic">Mempool is empty.</p>
        </div>
    </div>
</template>
