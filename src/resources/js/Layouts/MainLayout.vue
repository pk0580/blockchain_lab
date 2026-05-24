<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col">
        <header class="border-b border-slate-200 bg-white">
            <div class="max-w-6xl mx-auto px-6 py-3 flex items-center justify-between">
                <Link
                    href="/"
                    class="font-semibold text-slate-900 hover:text-blue-700 transition-colors"
                >
                    blockchain-lab
                </Link>
                <nav class="text-sm text-slate-600 flex gap-6">
                    <Link href="/lessons" class="hover:text-blue-700">Lessons</Link>
                    <Link href="/admin" class="hover:text-blue-700">Admin</Link>
                    <a
                        href="https://github.com"
                        class="hover:text-blue-700"
                        target="_blank"
                        rel="noopener"
                    >
                        Source
                    </a>
                </nav>
            </div>
        </header>

        <main class="flex-1 max-w-6xl w-full mx-auto px-6 py-8">
            <slot />
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="max-w-6xl mx-auto px-6 py-4 text-xs text-slate-500">
                Educational L1/L2 platform · Bitcoin · Ethereum · Tron · Polygon
            </div>
        </footer>
    </div>
</template>
