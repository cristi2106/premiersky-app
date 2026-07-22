<script setup>
import { Link } from '@inertiajs/vue3';

// Expects a Laravel paginator object serialized as-is (current_page,
// last_page, from, to, total, links, prev_page_url, next_page_url, ...).
const props = defineProps({
    meta: {
        type: Object,
        required: true,
    },
});

// Laravel's own `links` array already includes "&laquo; Previous" and
// "Next &raquo;" entries at each end — drop them here since we render our
// own prev/next controls instead, so we're left with just the page numbers.
const pageLinks = () =>
    props.meta.links.slice(1, props.meta.links.length - 1);
</script>

<template>
    <div
        v-if="meta.last_page > 1"
        class="card flex flex-col items-center justify-between gap-3 px-4 py-3 sm:flex-row sm:px-6"
    >
        <p class="text-sm text-gray-600">
            Showing
            <span class="font-medium text-gray-900">{{ meta.from }}</span>
            to
            <span class="font-medium text-gray-900">{{ meta.to }}</span>
            of
            <span class="font-medium text-gray-900">{{ meta.total }}</span>
            results
        </p>

        <div class="flex items-center gap-1">
            <Link
                :href="meta.prev_page_url ?? '#'"
                preserve-scroll
                preserve-state
                class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 transition duration-150 ease-in-out"
                :class="
                    meta.prev_page_url
                        ? 'hover:bg-gray-100'
                        : 'pointer-events-none text-gray-300'
                "
            >
                Previous
            </Link>

            <div class="hidden items-center gap-1 sm:flex">
                <template v-for="(link, index) in pageLinks()" :key="index">
                    <span
                        v-if="link.url === null"
                        class="px-2 text-sm text-gray-400"
                        v-html="link.label"
                    />
                    <Link
                        v-else
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        class="min-w-[2.25rem] rounded-lg px-3 py-1.5 text-center text-sm font-medium transition duration-150 ease-in-out"
                        :class="
                            link.active
                                ? 'bg-accent-600 text-white'
                                : 'text-gray-600 hover:bg-gray-100'
                        "
                        v-html="link.label"
                    />
                </template>
            </div>

            <p class="px-2 text-sm text-gray-500 sm:hidden">
                Page {{ meta.current_page }} of {{ meta.last_page }}
            </p>

            <Link
                :href="meta.next_page_url ?? '#'"
                preserve-scroll
                preserve-state
                class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 transition duration-150 ease-in-out"
                :class="
                    meta.next_page_url
                        ? 'hover:bg-gray-100'
                        : 'pointer-events-none text-gray-300'
                "
            >
                Next
            </Link>
        </div>
    </div>
</template>
