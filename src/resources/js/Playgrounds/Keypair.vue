<script setup>
import { ref } from 'vue';

const loading = ref(false);
const error = ref(null);
const result = ref(null);
const mnemonicInput = ref('');

async function generate() {
    loading.value = true;
    error.value = null;
    try {
        const res = await fetch('/api/playground/keypair', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(mnemonicInput.value ? { mnemonic: mnemonicInput.value.trim() } : {}),
        });
        const json = await res.json();
        if (!res.ok) {
            error.value = json?.error?.message || 'Request failed';
            return;
        }
        result.value = json.data;
    } catch (e) {
        error.value = String(e);
    } finally {
        loading.value = false;
    }
}

function copy(value) {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        navigator.clipboard.writeText(value);
    }
}
</script>

<template>
    <div class="space-y-4 text-sm">
        <h3 class="text-base font-semibold">Keypair generator</h3>
        <p class="text-slate-600 text-xs leading-relaxed">
            Generates a fresh BIP-39 mnemonic, derives the secp256k1 keypair at
            <code>m/44'/0'/0'/0/0</code>, and shows the addresses for three families.
            <strong class="text-rose-700">Demo only.</strong> Never use these keys
            for real funds — the private key is intentionally exposed in this response.
        </p>

        <div>
            <label class="block text-xs text-slate-500 mb-1" for="mnemonic">
                Mnemonic (optional — leave empty for a random one)
            </label>
            <textarea
                id="mnemonic"
                v-model="mnemonicInput"
                rows="2"
                class="w-full text-xs font-mono rounded border border-slate-300 px-2 py-1"
                placeholder="abandon abandon abandon ..."
            ></textarea>
        </div>

        <button
            type="button"
            class="px-3 py-1.5 rounded bg-blue-600 text-white text-xs font-medium hover:bg-blue-700 disabled:opacity-50"
            :disabled="loading"
            @click="generate"
        >
            {{ loading ? 'Generating…' : 'Generate keypair' }}
        </button>

        <p v-if="error" class="text-xs text-rose-700">{{ error }}</p>

        <dl v-if="result" class="space-y-2 text-xs">
            <div>
                <dt class="text-slate-500">Mnemonic</dt>
                <dd class="font-mono break-words bg-white border border-slate-200 rounded p-2 mt-0.5">
                    {{ result.mnemonic }}
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Private key (hex)</dt>
                <dd class="font-mono break-all bg-white border border-rose-200 rounded p-2 mt-0.5 text-rose-700">
                    {{ result.private_key_hex }}
                    <button
                        type="button"
                        class="ml-2 text-blue-700 hover:underline"
                        @click="copy(result.private_key_hex)"
                    >
                        copy
                    </button>
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Public key (compressed, hex)</dt>
                <dd class="font-mono break-all bg-white border border-slate-200 rounded p-2 mt-0.5">
                    {{ result.public_key_compressed_hex }}
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Bitcoin (P2WPKH)</dt>
                <dd class="font-mono break-all bg-white border border-slate-200 rounded p-2 mt-0.5">
                    {{ result.bitcoin_address }}
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Ethereum</dt>
                <dd class="font-mono break-all bg-white border border-slate-200 rounded p-2 mt-0.5">
                    {{ result.ethereum_address }}
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Tron</dt>
                <dd class="font-mono break-all bg-white border border-slate-200 rounded p-2 mt-0.5">
                    {{ result.tron_address }}
                </dd>
            </div>
        </dl>
    </div>
</template>
