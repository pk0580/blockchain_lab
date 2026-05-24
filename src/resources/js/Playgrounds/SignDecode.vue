<script setup>
import { ref } from 'vue';

const loading = ref(false);
const error = ref(null);
const result = ref(null);
const privateKey = ref('');
const messageHex = ref('');

function toHex(text) {
    let out = '';
    for (let i = 0; i < text.length; i++) {
        out += text.charCodeAt(i).toString(16).padStart(2, '0');
    }
    return out;
}

async function sign() {
    loading.value = true;
    error.value = null;
    try {
        const res = await fetch('/api/playground/sign', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                private_key_hex: privateKey.value.trim(),
                message_hex: messageHex.value.trim(),
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

function fromAscii() {
    messageHex.value = toHex(messageHex.value);
}
</script>

<template>
    <div class="space-y-4 text-sm">
        <h3 class="text-base font-semibold">Sign &amp; decode</h3>
        <p class="text-slate-600 text-xs leading-relaxed">
            Computes <code>sha256d(message_hex)</code> and signs the resulting 32-byte digest
            with the given private key (raw ECDSA over secp256k1). This is the same primitive
            Bitcoin uses; for Ethereum the digest function differs (Keccak256) but the curve
            and signing math are identical.
        </p>

        <div>
            <label class="block text-xs text-slate-500 mb-1" for="priv">Private key (64 hex chars)</label>
            <input
                id="priv"
                v-model="privateKey"
                class="w-full text-xs font-mono rounded border border-slate-300 px-2 py-1"
                placeholder="ff…"
            />
        </div>

        <div>
            <label class="block text-xs text-slate-500 mb-1" for="msg">Message (hex)</label>
            <textarea
                id="msg"
                v-model="messageHex"
                rows="2"
                class="w-full text-xs font-mono rounded border border-slate-300 px-2 py-1"
                placeholder="48656c6c6f"
            ></textarea>
            <button
                type="button"
                class="text-xs text-blue-700 hover:underline mt-1"
                @click="fromAscii"
            >
                Convert above text to hex
            </button>
        </div>

        <button
            type="button"
            class="px-3 py-1.5 rounded bg-blue-600 text-white text-xs font-medium hover:bg-blue-700 disabled:opacity-50"
            :disabled="loading || !privateKey || !messageHex"
            @click="sign"
        >
            {{ loading ? 'Signing…' : 'Sign' }}
        </button>

        <p v-if="error" class="text-xs text-rose-700 break-words">{{ error }}</p>

        <dl v-if="result" class="space-y-2 text-xs">
            <div>
                <dt class="text-slate-500">Digest (sha256d, hex)</dt>
                <dd class="font-mono break-all bg-white border border-slate-200 rounded p-2 mt-0.5">
                    {{ result.digest_hex }}
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">r</dt>
                <dd class="font-mono break-all bg-white border border-slate-200 rounded p-2 mt-0.5">
                    {{ result.r_hex }}
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">s</dt>
                <dd class="font-mono break-all bg-white border border-slate-200 rounded p-2 mt-0.5">
                    {{ result.s_hex }}
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">DER signature</dt>
                <dd class="font-mono break-all bg-white border border-slate-200 rounded p-2 mt-0.5">
                    {{ result.signature_der_hex }}
                </dd>
            </div>
        </dl>
    </div>
</template>
