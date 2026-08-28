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

// --- Hide the bar while a field is focused (soft keyboard likely open) ---
// On mobile Safari the on-screen keyboard shrinks the *visual* viewport
// but not the *layout* viewport, so a `position: fixed; bottom: …` bar
// detaches from the shrunken viewport and, mid-scroll, pops back into view
// over the content (and sometimes over the keyboard itself). Rather than
// fight the viewport maths, take the bar out entirely whenever the thing
// that summons the keyboard — an <input>/<textarea>/<select> — holds
// focus, and fade it back once focus leaves. focusin/focusout are the
// bubbling counterparts of focus/blur, so one pair of document listeners
// covers every field on every page with no per-field wiring. Opacity-only
// transition + v-if means that once the keyboard closes the bar is byte
// -for-byte what it was before — no leftover transform/pointer-events to
// affect scrolling.
const keyboardOpen = ref(false);

// Non-text input types don't raise a keyboard — focusing a checkbox or
// the file button shouldn't blank the nav.
const NON_TEXT_INPUT_TYPES = new Set([
    'checkbox', 'radio', 'button', 'submit', 'reset', 'file', 'range', 'color', 'image',
]);

function summonsKeyboard(el) {
    if (!el) return false;
    if (el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') return true;
    if (el.tagName === 'INPUT') {
        return !NON_TEXT_INPUT_TYPES.has((el.type || 'text').toLowerCase());
    }
    return false;
}

let settleTimer = null;
function syncKeyboardState() {
    // Defer a tick: during focusout, document.activeElement is briefly
    // <body> before it lands on the next element, so reading it
    // immediately would flicker the bar when tabbing field-to-field.
    clearTimeout(settleTimer);
    settleTimer = setTimeout(() => {
        keyboardOpen.value = summonsKeyboard(document.activeElement);
    }, 0);
}

onMounted(() => {
    document.addEventListener('focusin', syncKeyboardState);
    document.addEventListener('focusout', syncKeyboardState);
    syncKeyboardState(); // a field may be autofocused on load
});

onBeforeUnmount(() => {
    clearTimeout(settleTimer);
    document.removeEventListener('focusin', syncKeyboardState);
    document.removeEventListener('focusout', syncKeyboardState);
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
