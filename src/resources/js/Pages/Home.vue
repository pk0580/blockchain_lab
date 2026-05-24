<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    lessons: {
        type: Array,
        required: true,
    },
});

const groupedByModule = computed(() => {
    const groups = new Map();
    for (const lesson of props.lessons) {
        if (!groups.has(lesson.module)) {
            groups.set(lesson.module, {
                module: lesson.module,
                displayName: lesson.module_display_name,
                order: lesson.module_order,
                lessons: [],
            });
        }
        groups.get(lesson.module).lessons.push(lesson);
    }
    return Array.from(groups.values()).sort((a, b) => a.order - b.order);
});
</script>

<template>
    <Head title="Home" />

    <section class="space-y-4 mb-10">
        <h1 class="text-3xl font-semibold tracking-tight">Learn how blockchains actually work.</h1>
        <p class="text-slate-600 max-w-2xl leading-relaxed">
            A self-contained lab that wires up a real Bitcoin / Ethereum / Tron / Polygon stack
            so you can poke at every concept — gas, mempool, confirmations, reorgs, signing — on
            top of working code, not slides.
        </p>
    </section>

    <section>
        <h2 class="text-lg font-semibold text-slate-800 mb-4">Course outline</h2>

        <div v-if="groupedByModule.length === 0" class="text-slate-500">
            No lessons yet. Add markdown files under <code>content/lessons/</code>.
        </div>

        <div v-else class="space-y-6">
            <article
                v-for="group in groupedByModule"
                :key="group.module"
                class="bg-white border border-slate-200 rounded-lg p-5"
            >
                <header class="mb-3">
                    <span class="text-xs uppercase tracking-wider text-slate-500">
                        Module {{ group.order }}
                    </span>
                    <h3 class="text-base font-semibold capitalize">{{ group.displayName }}</h3>
                </header>
                <ul class="space-y-2">
                    <li v-for="lesson in group.lessons" :key="lesson.slug">
                        <Link
                            :href="`/lessons/${lesson.slug}`"
                            class="text-blue-700 hover:text-blue-900 hover:underline"
                        >
                            {{ lesson.order }}. {{ lesson.title }}
                        </Link>
                        <span v-if="lesson.summary" class="ml-2 text-sm text-slate-500">
                            — {{ lesson.summary }}
                        </span>
                    </li>
                </ul>
            </article>
        </div>
    </section>
</template>
