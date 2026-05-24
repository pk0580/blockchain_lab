<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
});

const data = ref(props.dashboard);
const lastError = ref(null);
let pollHandle = null;

async function refresh() {
    try {
        const res = await fetch('/api/admin/dashboard', { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const body = await res.json();
        data.value = body.data;
        lastError.value = null;
    } catch (e) {
        lastError.value = String(e?.message ?? e);
    }
}

onMounted(() => {
    pollHandle = setInterval(refresh, 5000);
});

onBeforeUnmount(() => {
    if (pollHandle) clearInterval(pollHandle);
});

const statusClass = (status) =>
    ({
        healthy: 'bg-emerald-100 text-emerald-800',
        degraded: 'bg-amber-100 text-amber-800',
        unhealthy: 'bg-rose-100 text-rose-800',
        unknown: 'bg-slate-100 text-slate-600',
    }[status] ?? 'bg-slate-100 text-slate-600');

const generatedRelative = computed(() => {
    if (!data.value?.generated_at) return '';
    const ts = new Date(data.value.generated_at).getTime();
    const ageSec = Math.max(0, Math.round((Date.now() - ts) / 1000));
    return `${ageSec}s ago`;
});
</script>

<template>
    <Head title="Admin · blockchain-lab" />

    <div class="space-y-6">
        <header class="flex items-baseline justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Admin dashboard</h1>
                <p class="text-sm text-slate-500">
                    Read-only снимок состояния платформы. Обновляется каждые 5 секунд.
                </p>
            </div>
            <div class="text-xs text-slate-500">
                Updated <span class="font-mono">{{ generatedRelative }}</span>
                <span v-if="lastError" class="ml-2 text-rose-600">poll failed: {{ lastError }}</span>
            </div>
        </header>

        <!-- Node health -->
        <section class="bg-white border border-slate-200 rounded-lg p-5">
            <h2 class="font-semibold mb-3">Node health</h2>
            <table v-if="data.node_health.rows.length" class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-400">
                    <tr>
                        <th class="py-1 pr-3">Chain</th>
                        <th class="py-1 pr-3">Endpoint</th>
                        <th class="py-1 pr-3">Status</th>
                        <th class="py-1 pr-3 text-right">Head</th>
                        <th class="py-1 pr-3 text-right">Latency</th>
                        <th class="py-1">Observed</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in data.node_health.rows"
                        :key="`${row.chain_id}|${row.endpoint_url}`"
                        class="border-t border-slate-100"
                    >
                        <td class="py-1 pr-3">
                            <span class="font-medium">{{ row.chain_name }}</span>
                            <span class="text-slate-400 ml-1">({{ row.chain_family }})</span>
                        </td>
                        <td class="py-1 pr-3 font-mono text-xs truncate max-w-xs" :title="row.endpoint_url">
                            {{ row.endpoint_url }}
                        </td>
                        <td class="py-1 pr-3">
                            <span :class="['inline-block px-2 py-0.5 rounded text-xs', statusClass(row.status)]">
                                {{ row.status }}
                            </span>
                        </td>
                        <td class="py-1 pr-3 text-right font-mono">{{ row.head_height ?? '—' }}</td>
                        <td class="py-1 pr-3 text-right font-mono">
                            {{ row.latency_ms != null ? `${row.latency_ms}ms` : '—' }}
                        </td>
                        <td class="py-1 text-slate-500 text-xs">{{ row.observed_at ?? 'never' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-sm text-slate-500">
                Нет зарегистрированных сетей. Запустите <code>php artisan chain:register …</code>.
            </p>
        </section>

        <!-- Mempool -->
        <section class="bg-white border border-slate-200 rounded-lg p-5">
            <h2 class="font-semibold mb-3">Mempool</h2>
            <table v-if="data.mempool.rows.length" class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-400">
                    <tr>
                        <th class="py-1 pr-3">Chain</th>
                        <th class="py-1 pr-3 text-right">Tx count</th>
                        <th class="py-1">Note</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in data.mempool.rows"
                        :key="row.chain_id"
                        class="border-t border-slate-100"
                    >
                        <td class="py-1 pr-3">
                            <span class="font-medium">{{ row.chain_name }}</span>
                            <span class="text-slate-400 ml-1">({{ row.chain_family }})</span>
                        </td>
                        <td class="py-1 pr-3 text-right font-mono">{{ row.tx_count ?? '—' }}</td>
                        <td class="py-1 text-xs text-slate-500">{{ row.error ?? '' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-sm text-slate-500">No chains.</p>
        </section>

        <!-- Withdrawal queue -->
        <section class="bg-white border border-slate-200 rounded-lg p-5">
            <h2 class="font-semibold mb-3">Withdrawal queue</h2>
            <div class="flex flex-wrap gap-2 mb-3">
                <span
                    v-for="(count, status) in data.withdrawals.counts_by_status"
                    :key="status"
                    class="px-2 py-1 rounded bg-slate-100 text-xs"
                >
                    <span class="font-mono text-slate-800">{{ status }}</span>: {{ count }}
                </span>
                <span
                    v-if="!Object.keys(data.withdrawals.counts_by_status).length"
                    class="text-xs text-slate-500"
                >
                    No withdrawals yet.
                </span>
            </div>
            <table v-if="data.withdrawals.recent.length" class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-400">
                    <tr>
                        <th class="py-1 pr-3">ID</th>
                        <th class="py-1 pr-3">Chain</th>
                        <th class="py-1 pr-3">Status</th>
                        <th class="py-1 pr-3 text-right">Amount</th>
                        <th class="py-1 pr-3 text-right">Confs</th>
                        <th class="py-1">Requested</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in data.withdrawals.recent"
                        :key="row.id"
                        class="border-t border-slate-100"
                    >
                        <td class="py-1 pr-3 font-mono text-xs">{{ row.id.slice(0, 8) }}</td>
                        <td class="py-1 pr-3">{{ row.chain_id }}</td>
                        <td class="py-1 pr-3">
                            <span class="px-1.5 py-0.5 rounded bg-slate-100 text-xs">{{ row.status }}</span>
                        </td>
                        <td class="py-1 pr-3 text-right font-mono">{{ row.amount }} {{ row.currency }}</td>
                        <td class="py-1 pr-3 text-right font-mono">{{ row.confirmations }}</td>
                        <td class="py-1 text-xs text-slate-500">{{ row.requested_at }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Ledger -->
        <section class="bg-white border border-slate-200 rounded-lg p-5">
            <h2 class="font-semibold mb-3">
                Ledger
                <span class="font-normal text-xs text-slate-500">(total: {{ data.ledger.total_entries }})</span>
            </h2>
            <div class="flex flex-wrap gap-2 mb-3">
                <span
                    v-for="(count, status) in data.ledger.counts_by_status"
                    :key="status"
                    class="px-2 py-1 rounded bg-slate-100 text-xs"
                >
                    <span class="font-mono text-slate-800">{{ status }}</span>: {{ count }}
                </span>
            </div>
            <table v-if="data.ledger.recent_entries.length" class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-400">
                    <tr>
                        <th class="py-1 pr-3">Direction</th>
                        <th class="py-1 pr-3 text-right">Amount</th>
                        <th class="py-1 pr-3">Op</th>
                        <th class="py-1 pr-3">Status</th>
                        <th class="py-1">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in data.ledger.recent_entries"
                        :key="row.id"
                        class="border-t border-slate-100"
                    >
                        <td class="py-1 pr-3">
                            <span
                                :class="[
                                    'px-1.5 py-0.5 rounded text-xs',
                                    row.direction === 'credit'
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-rose-100 text-rose-800',
                                ]"
                            >
                                {{ row.direction }}
                            </span>
                        </td>
                        <td class="py-1 pr-3 text-right font-mono">{{ row.amount }} {{ row.currency }}</td>
                        <td class="py-1 pr-3 text-xs">{{ row.operation_type }}</td>
                        <td class="py-1 pr-3 text-xs">{{ row.status }}</td>
                        <td class="py-1 text-xs text-slate-500">{{ row.created_at }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-sm text-slate-500">No entries.</p>
        </section>

        <!-- Outbox lag -->
        <section class="bg-white border border-slate-200 rounded-lg p-5">
            <h2 class="font-semibold mb-3">Outbox + webhook lag</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <div class="p-3 rounded border border-slate-100">
                    <div class="text-xs uppercase text-slate-400">Unpublished</div>
                    <div class="text-2xl font-mono mt-1">{{ data.outbox.unpublished_messages }}</div>
                </div>
                <div class="p-3 rounded border border-slate-100">
                    <div class="text-xs uppercase text-slate-400">Oldest unpublished</div>
                    <div class="text-xs font-mono mt-1">{{ data.outbox.oldest_unpublished_at ?? '—' }}</div>
                </div>
                <div class="p-3 rounded border border-slate-100">
                    <div class="text-xs uppercase text-slate-400">Deliveries pending</div>
                    <div class="text-2xl font-mono mt-1">{{ data.outbox.deliveries_pending }}</div>
                </div>
                <div class="p-3 rounded border border-slate-100">
                    <div class="text-xs uppercase text-slate-400">Deliveries failed</div>
                    <div
                        :class="[
                            'text-2xl font-mono mt-1',
                            data.outbox.deliveries_failed > 0 ? 'text-rose-700' : '',
                        ]"
                    >
                        {{ data.outbox.deliveries_failed }}
                    </div>
                </div>
            </div>
            <p class="text-xs text-slate-500 mt-3">
                Delivered (lifetime): <span class="font-mono">{{ data.outbox.deliveries_delivered }}</span>
            </p>
        </section>
    </div>
</template>
