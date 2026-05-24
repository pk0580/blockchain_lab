<script setup>
import { ref } from 'vue';

const loading = ref(false);
const error = ref(null);
const result = ref(null);
const chain = ref('bitcoin');
const rawHex = ref('');

async function decode() {
    loading.value = true;
    error.value = null;
    result.value = null;
    try {
        const res = await fetch('/api/playground/decode', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ chain: chain.value, raw_hex: rawHex.value.trim() }),
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
    <div class="space-y-4 text-sm">
        <h3 class="text-base font-semibold">Raw TX editor</h3>
        <p class="text-slate-600 text-xs leading-relaxed">
            Paste a signed transaction hex (Bitcoin or any EVM chain) and the backend will
            deserialize it into structured fields — inputs/outputs for UTXO chains, nonce
            and gas for EVM. The decoding uses the same libraries the platform uses to
            verify broadcasts (<code>btcd/wire</code> and <code>go-ethereum/core/types</code>).
        </p>

        <div class="flex gap-2 items-center">
            <label class="text-xs text-slate-500" for="chain">Chain</label>
            <select
                id="chain"
                v-model="chain"
                class="text-xs rounded border border-slate-300 px-2 py-1"
            >
                <option value="bitcoin">bitcoin</option>
                <option value="ethereum">ethereum</option>
            </select>
        </div>

        <div>
            <label class="block text-xs text-slate-500 mb-1" for="raw">Raw hex</label>
            <textarea
                id="raw"
                v-model="rawHex"
                rows="4"
                class="w-full text-xs font-mono rounded border border-slate-300 px-2 py-1"
                placeholder="0100000001..."
            ></textarea>
        </div>

        <button
            type="button"
            class="px-3 py-1.5 rounded bg-blue-600 text-white text-xs font-medium hover:bg-blue-700 disabled:opacity-50"
            :disabled="loading || !rawHex"
            @click="decode"
        >
            {{ loading ? 'Decoding…' : 'Decode' }}
        </button>

        <p v-if="error" class="text-xs text-rose-700 break-words">{{ error }}</p>

        <!-- Bitcoin output -->
        <div v-if="result && chain === 'bitcoin'" class="space-y-3 text-xs">
            <div class="bg-white border border-slate-200 rounded p-3">
                <div><span class="text-slate-500">Version:</span> {{ result.version }}</div>
                <div><span class="text-slate-500">Locktime:</span> {{ result.locktime }}</div>
                <div><span class="text-slate-500">Size:</span> {{ result.size_bytes }} bytes</div>
                <div class="font-mono break-all"><span class="text-slate-500">TxID:</span> {{ result.txid_hex }}</div>
            </div>

            <div>
                <h4 class="font-semibold mb-1">Inputs ({{ result.inputs.length }})</h4>
                <div
                    v-for="(input, idx) in result.inputs"
                    :key="idx"
                    class="bg-white border border-slate-200 rounded p-2 mb-1 font-mono break-all"
                >
                    <div>prev: {{ input.prev_txid }}:{{ input.prev_vout }}</div>
                    <div>sequence: {{ input.sequence }}</div>
                    <div v-if="input.script_sig_hex">scriptSig: {{ input.script_sig_hex }}</div>
                </div>
            </div>

            <div>
                <h4 class="font-semibold mb-1">Outputs ({{ result.outputs.length }})</h4>
                <div
                    v-for="(out, idx) in result.outputs"
                    :key="idx"
                    class="bg-white border border-slate-200 rounded p-2 mb-1 font-mono break-all"
                >
                    <div>value: {{ out.value_sats }} sat</div>
                    <div>scriptPubKey: {{ out.script_pubkey_hex }}</div>
                </div>
            </div>
        </div>

        <!-- Ethereum output -->
        <dl v-if="result && chain === 'ethereum'" class="space-y-2 text-xs">
            <div>
                <dt class="text-slate-500">Type</dt>
                <dd>{{ result.tx_type }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Chain ID</dt>
                <dd>{{ result.chain_id }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Nonce</dt>
                <dd>{{ result.nonce }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">To</dt>
                <dd class="font-mono break-all">{{ result.to || '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Value (wei)</dt>
                <dd class="font-mono break-all">{{ result.value_wei }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Gas limit</dt>
                <dd>{{ result.gas_limit }}</dd>
            </div>
            <div v-if="result.gas_price_wei">
                <dt class="text-slate-500">Gas price (wei)</dt>
                <dd class="font-mono">{{ result.gas_price_wei }}</dd>
            </div>
            <div v-if="result.max_fee_per_gas_wei">
                <dt class="text-slate-500">Max fee per gas (wei)</dt>
                <dd class="font-mono">{{ result.max_fee_per_gas_wei }}</dd>
            </div>
            <div v-if="result.max_priority_fee_per_gas_wei">
                <dt class="text-slate-500">Max priority fee (wei)</dt>
                <dd class="font-mono">{{ result.max_priority_fee_per_gas_wei }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Data</dt>
                <dd class="font-mono break-all">{{ result.data_hex || '0x' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Hash</dt>
                <dd class="font-mono break-all">{{ result.hash }}</dd>
            </div>
        </dl>
    </div>
</template>
