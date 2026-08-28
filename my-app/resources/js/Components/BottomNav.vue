<script setup>
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

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
    {
        name: 'Quotes',
        route: 'quotes.index',
        active: 'quotes.*',
        icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    },
];

const refreshIcon = 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15';

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

// --- Hide the bar only while the on-screen keyboard is actually open ---
// A `position: fixed; bottom: …` bar detaches from the *visual* viewport
// the iOS keyboard shrinks it to, and pops back over the content
// mid-scroll (sometimes over the keyboard itself).
//
// Detect the keyboard directly rather than inferring it from which field
// has focus: the Quotes trip-ID input autofocuses on load and never
// blurs, so any focus-based check can't tell "the user is typing" from
// "the user is scrolling past a still-focused field". When the keyboard
// opens, window.visualViewport.height drops well below the layout
// viewport (window.innerHeight) by the keyboard's own height — far more
// than the ~60-100px the iOS URL bar ever accounts for, and that gap
// persists for the whole time the keyboard is up regardless of scrolling.
const keyboardOpen = ref(false);

// The soft keyboard is always taller than this on a phone in portrait;
// the URL-bar / accessory-bar deltas never are.
const KEYBOARD_MIN_INSET = 150;

function syncKeyboardState() {
    const vv = window.visualViewport;

    if (!vv) {
        keyboardOpen.value = false;
        return;
    }

    // Pinch-zoom also shrinks visualViewport.height — that isn't a keyboard.
    const zoomed = vv.scale > 1.05;
    const hiddenInset = window.innerHeight - vv.height;

    keyboardOpen.value = !zoomed && hiddenInset > KEYBOARD_MIN_INSET;
}

onMounted(() => {
    window.visualViewport?.addEventListener('resize', syncKeyboardState);
    syncKeyboardState();
});

onBeforeUnmount(() => {
    window.visualViewport?.removeEventListener('resize', syncKeyboardState);
});
</script>

<template>
    <!-- Floating rounded tab bar: pinned to the bottom but inset from all
         three edges so it reads as a card hovering over the content
         rather than a flat chrome strip. The bottom offset is a full 1rem
         gap so all four rounded corners clear the screen edge / home
         indicator and stay visible; env(safe-area-inset-bottom) is added
         on top of that for any context that reports one (it's 0 in a
         plain iOS standalone viewport, which is already inset above the
         home indicator, so the 1rem alone is what does the work there).
         overflow-hidden clips the tab cells / the refresh tint to the
         rounded corners.

         v-if + fade Transition: the bar is removed while a field is
         focused (see syncKeyboardState in <script>) and fades back on
         blur. -->
    <Transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <nav
            v-if="!keyboardOpen"
            class="fixed inset-x-3 bottom-[calc(env(safe-area-inset-bottom)+1rem)] z-30 flex overflow-hidden rounded-[28px] border border-gray-200 bg-white shadow-lg md:hidden"
        >
            <div class="grid flex-1 grid-cols-4">
                <Link
                    v-for="tab in tabs"
                    :key="tab.name"
                    :href="route(tab.route)"
                    class="flex h-[80px] flex-col items-center justify-center gap-1 transition duration-150 ease-in-out"
                    :class="route().current(tab.active) ? 'text-accent-600' : 'text-gray-400 hover:text-gray-600'"
                >
                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
                    </svg>
                    <span class="text-xs font-medium leading-none">{{ tab.name }}</span>
                </Link>
            </div>

            <!-- Refresh: an action, not a destination, so it's set apart with its
                 own divider and tint rather than sitting as a fifth equal tab. -->
            <button
                type="button"
                class="flex h-[80px] w-16 shrink-0 flex-col items-center justify-center gap-1 border-l border-gray-200 bg-gray-50 text-gray-400 transition duration-150 ease-in-out hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="isRefreshing"
                @click="refreshPage"
            >
                <svg
                    class="h-6 w-6 shrink-0"
                    :class="{ 'animate-spin': isRefreshing }"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" :d="refreshIcon" />
                </svg>
                <span class="text-xs font-medium leading-none">Refresh</span>
            </button>
        </nav>
    </Transition>
</template>
