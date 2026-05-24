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
    <Head title="Lessons" />

    <h1 class="text-2xl font-semibold mb-6">All lessons</h1>

    <div class="space-y-6">
        <article
            v-for="group in groupedByModule"
            :key="group.module"
            class="bg-white border border-slate-200 rounded-lg p-5"
        >
            <h2 class="text-sm uppercase tracking-wider text-slate-500 mb-3">
                {{ group.order }}. {{ group.displayName }}
            </h2>
            <ul class="space-y-1.5">
                <li v-for="lesson in group.lessons" :key="lesson.slug">
                    <Link
                        :href="`/lessons/${lesson.slug}`"
                        class="text-blue-700 hover:text-blue-900 hover:underline"
                    >
                        {{ lesson.title }}
                    </Link>
                </li>
            </ul>
        </article>
    </div>
</template>
