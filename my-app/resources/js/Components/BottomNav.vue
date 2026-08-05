<script setup>
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

// Fixed set of quick-access tabs — not a replacement for the full nav
// (still reachable via the hamburger menu), just the four most-used
// sections surfaced the way a native app's tab bar would.
const tabs = [
    {
        name: 'Clients',
        route: 'clients.index',
        active: 'clients.*',
        icon: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4',
    },
    {
        name: 'Contracts',
        route: 'contracts.index',
        active: 'contracts.*',
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    },
    {
        name: 'Tails',
        route: 'tails.index',
        active: 'tails.*',
        icon: 'M7 7h.01M7 3h5.586a1 1 0 01.707.293l6.414 6.414a1 1 0 010 1.414l-8.586 8.586a1 1 0 01-1.414 0l-6.414-6.414A1 1 0 013 12.586V7a4 4 0 014-4z',
    },
];

const quotesIcon = 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z';
const refreshIcon = 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15';

const showQuotesComingSoon = ref(false);

// A genuine browser reload — not Inertia's router.reload(), which only
// re-fetches props over XHR and would never pick up a new JS/CSS bundle
// after a deploy. This re-requests the HTML document itself, so the
// <script>/<link> tags it points at (and everything downstream) come
// from the server fresh. It's still just a same-origin reload of the
// current URL, so it doesn't touch the standalone/PWA window boundary —
// nothing here differs from what iOS considers "still the installed app".
const isRefreshing = ref(false);

function refreshPage() {
    if (isRefreshing.value) return;
    isRefreshing.value = true;

    // Let the spin actually paint before the navigation tears the page down.
    requestAnimationFrame(() => {
        setTimeout(() => window.location.reload(), 60);
    });
}
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-30 flex border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden"
    >
        <div class="grid flex-1 grid-cols-4">
            <Link
                v-for="tab in tabs"
                :key="tab.name"
                :href="route(tab.route)"
                class="flex flex-col items-center justify-center gap-1.5 pb-[50px] pt-3.5 transition duration-150 ease-in-out"
                :class="route().current(tab.active) ? 'text-accent-600' : 'text-gray-400 hover:text-gray-600'"
            >
                <svg class="h-8 w-8 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
                </svg>
                <span class="sr-only">{{ tab.name }}</span>
            </Link>

            <button
                type="button"
                class="flex flex-col items-center justify-center gap-1.5 pb-[50px] pt-3.5 text-gray-400 transition duration-150 ease-in-out hover:text-gray-600"
                @click="showQuotesComingSoon = true"
            >
                <svg class="h-8 w-8 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="quotesIcon" />
                </svg>
                <span class="sr-only">Quotes</span>
            </button>
        </div>

        <!-- Refresh: an action, not a destination, so it's set apart with its own
             divider and tint rather than sitting as a fifth equal tab. -->
        <button
            type="button"
            class="flex w-16 shrink-0 flex-col items-center justify-center gap-1.5 border-l border-gray-200 bg-gray-50 pb-[50px] pt-3.5 text-gray-400 transition duration-150 ease-in-out hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="isRefreshing"
            @click="refreshPage"
        >
            <svg
                class="h-7 w-7 shrink-0"
                :class="{ 'animate-spin': isRefreshing }"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" :d="refreshIcon" />
            </svg>
            <span class="sr-only">Refresh</span>
        </button>
    </nav>

    <Modal :show="showQuotesComingSoon" max-width="sm" @close="showQuotesComingSoon = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Quotes</h2>
            <p class="mt-1 text-sm text-gray-600">
                Coming soon — the Quotes module hasn't been built yet.
            </p>
            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="showQuotesComingSoon = false">Got it</SecondaryButton>
            </div>
        </div>
    </Modal>
</template>
