<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, shallowRef } from 'vue';
import Keypair from '../../Playgrounds/Keypair.vue';
import SignDecode from '../../Playgrounds/SignDecode.vue';
import RawTxEditor from '../../Playgrounds/RawTxEditor.vue';
import MempoolTracker from '../../Playgrounds/MempoolTracker.vue';
import ReorgSimulator from '../../Playgrounds/ReorgSimulator.vue';
import GasChart from '../../Playgrounds/GasChart.vue';
import NonceConflict from '../../Playgrounds/NonceConflict.vue';

const playgroundRegistry = shallowRef({
    keypair: Keypair,
    'sign-decode': SignDecode,
    'raw-tx-editor': RawTxEditor,
    'mempool-tracker': MempoolTracker,
    'reorg-simulator': ReorgSimulator,
    'gas-chart': GasChart,
    'nonce-conflict': NonceConflict,
});

const props = defineProps({
    lesson: {
        type: Object,
        required: true,
    },
    lessons: {
        type: Array,
        required: true,
    },
});

const playgroundComponent = computed(() => {
    if (!props.lesson.playground_id) return null;
    return playgroundRegistry.value[props.lesson.playground_id] ?? null;
});

const groupedByModule = computed(() => {
    const groups = new Map();
    for (const l of props.lessons) {
        if (!groups.has(l.module)) {
            groups.set(l.module, {
                module: l.module,
                displayName: l.module_display_name,
                order: l.module_order,
                lessons: [],
            });
        }
        groups.get(l.module).lessons.push(l);
    }
    return Array.from(groups.values()).sort((a, b) => a.order - b.order);
});

const flatOrder = computed(() => {
    const flat = [];
    for (const group of groupedByModule.value) {
        for (const l of group.lessons) flat.push(l);
    }
    return flat;
});

const currentIndex = computed(() =>
    flatOrder.value.findIndex((l) => l.slug === props.lesson.slug),
);
const previous = computed(() => (currentIndex.value > 0 ? flatOrder.value[currentIndex.value - 1] : null));
const next = computed(() =>
    currentIndex.value >= 0 && currentIndex.value < flatOrder.value.length - 1
        ? flatOrder.value[currentIndex.value + 1]
        : null,
);
</script>

<template>
    <Head :title="lesson.title" />

    <div class="grid grid-cols-12 gap-6">
        <!-- Left sidebar: course navigation -->
        <aside class="col-span-3 hidden md:block">
            <nav class="bg-white border border-slate-200 rounded-lg p-4 sticky top-6 text-sm">
                <div v-for="group in groupedByModule" :key="group.module" class="mb-4 last:mb-0">
                    <h3 class="text-xs uppercase tracking-wider text-slate-400 mb-2">
                        {{ group.displayName }}
                    </h3>
                    <ul class="space-y-1">
                        <li v-for="l in group.lessons" :key="l.slug">
                            <Link
                                :href="`/lessons/${l.slug}`"
                                :class="[
                                    'block px-2 py-1 rounded',
                                    l.slug === lesson.slug
                                        ? 'bg-blue-50 text-blue-800 font-medium'
                                        : 'text-slate-600 hover:bg-slate-50',
                                ]"
                            >
                                {{ l.title }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <!-- Middle: lesson content -->
        <section
            :class="[lesson.playground_id ? 'col-span-12 md:col-span-6' : 'col-span-12 md:col-span-9']"
        >
            <div class="bg-white border border-slate-200 rounded-lg p-8">
                <header class="mb-6">
                    <span class="text-xs uppercase tracking-wider text-slate-400">
                        {{ lesson.module_display_name }}
                    </span>
                    <h1 class="text-3xl font-semibold mt-1">{{ lesson.title }}</h1>
                    <p v-if="lesson.summary" class="text-slate-600 mt-2">{{ lesson.summary }}</p>
                </header>

                <article class="prose prose-slate max-w-none" v-html="lesson.body_html" />

                <nav class="flex justify-between items-center mt-10 pt-6 border-t border-slate-100 text-sm">
                    <Link
                        v-if="previous"
                        :href="`/lessons/${previous.slug}`"
                        class="text-blue-700 hover:text-blue-900"
                    >
                        ← {{ previous.title }}
                    </Link>
                    <span v-else></span>

                    <Link
                        v-if="next"
                        :href="`/lessons/${next.slug}`"
                        class="text-blue-700 hover:text-blue-900 ml-auto"
                    >
                        {{ next.title }} →
                    </Link>
                </nav>
            </div>
        </section>

        <!-- Right sidebar: playground -->
        <aside v-if="lesson.playground_id" class="col-span-12 md:col-span-3">
            <div class="bg-white border border-slate-200 rounded-lg p-4 sticky top-6">
                <component v-if="playgroundComponent" :is="playgroundComponent" />
                <div v-else class="text-sm text-slate-500">
                    Playground <code>{{ lesson.playground_id }}</code> is not registered yet.
                </div>
            </div>
        </aside>
    </div>
</template>
