<script setup>
import { onMounted, ref } from 'vue';

const state = ref(null);
const error = ref(null);
const action = ref(null);
const mineCount = ref(1);

async function refresh() {
    error.value = null;
    try {
        const res = await fetch('/api/playground/regtest/state');
        const json = await res.json();
        if (!res.ok) {
            error.value = json?.error?.message || 'failed';
            return;
        }
        state.value = json.data;
    } catch (e) {
        error.value = String(e);
    }
}

async function mine() {
    action.value = `mine ${mineCount.value}`;
    error.value = null;
    try {
        const res = await fetch('/api/playground/regtest/mine', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ blocks: mineCount.value }),
        });
        const json = await res.json();
        if (!res.ok) {
            error.value = json?.error?.message || 'failed';
            return;
        }
        await refresh();
    } catch (e) {
        error.value = String(e);
    } finally {
        action.value = null;
    }
}

async function invalidate() {
    action.value = 'invalidate';
    error.value = null;
    try {
        const res = await fetch('/api/playground/regtest/invalidate-tip', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({}),
        });
        const json = await res.json();
        if (!res.ok) {
            error.value = json?.error?.message || 'failed';
            return;
        }
        await refresh();
    } catch (e) {
        error.value = String(e);
    } finally {
        action.value = null;
    }
}

onMounted(refresh);
</script>

<template>
    <div class="space-y-3 text-sm">
        <h3 class="text-base font-semibold">Reorg simulator</h3>
        <p class="text-slate-600 text-xs leading-relaxed">
            Live on the regtest network. Mine blocks to extend the chain;
            invalidate-tip drops the current best block (the platform's scanner will
            see the orphan in real time, see <code>tests/Feature/Live/ReorgSimulatorTest.php</code>).
        </p>

        <div class="flex flex-wrap gap-2 items-center">
            <label class="text-xs text-slate-500" for="mc">Blocks</label>
            <input
                id="mc"
                v-model.number="mineCount"
                type="number"
                min="1"
                max="50"
                class="w-16 text-xs rounded border border-slate-300 px-2 py-1"
            />
            <button
                type="button"
                class="px-3 py-1.5 rounded bg-blue-600 text-white text-xs font-medium hover:bg-blue-700 disabled:opacity-50"
                :disabled="action !== null"
                @click="mine"
            >
                Mine
            </button>
            <button
                type="button"
                class="px-3 py-1.5 rounded bg-rose-600 text-white text-xs font-medium hover:bg-rose-700 disabled:opacity-50"
                :disabled="action !== null || !state"
                @click="invalidate"
            >
                Invalidate tip
            </button>
            <button
                type="button"
                class="px-3 py-1.5 rounded border border-slate-300 text-xs hover:bg-slate-100"
                @click="refresh"
            >
                Refresh
            </button>
        </div>

        <p v-if="error" class="text-xs text-rose-700 break-words">{{ error }}</p>

        <div v-if="state" class="space-y-2">
            <div class="text-xs">
                <span class="text-slate-500">Height:</span>
                <span class="font-mono font-semibold ml-1">{{ state.block_count }}</span>
            </div>
            <ul class="space-y-1">
                <li
                    v-for="(b, idx) in state.recent"
                    :key="b.hash"
                    class="text-[10px] font-mono bg-white border border-slate-200 rounded p-1.5 break-all"
                    :class="idx === 0 ? 'border-blue-400 ring-1 ring-blue-100' : ''"
                >
                    <div>
                        <span class="text-slate-400">{{ b.height }}</span>
                        ·
                        <span class="font-semibold">{{ b.hash }}</span>
                    </div>
                    <div class="text-slate-400">parent: {{ b.parent_hash || '∅' }}</div>
                </li>
            </ul>
        </div>
    </div>
</template>
