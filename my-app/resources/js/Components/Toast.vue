<script setup>
/**
 * App-wide success/error notifications — mounted once in AdminLayout, so
 * every page gets it automatically the same way every page already gets
 * `session()->flash('success', …)` / `->flash('error', …)` read back as
 * Inertia's shared `flash` prop (see HandleInertiaRequests::share()).
 * Controllers don't render or import anything for this; flashing the
 * session on a redirect is the entire integration.
 *
 * Deliberately not used for the Quotes "Generate Contract" confirmation
 * or the Contracts Edit "review before finalizing" banner — those need
 * to stay on screen while the user reviews what was pre-filled, which a
 * few-second auto-dismissing toast would undercut. This is for the
 * ordinary "saved / deleted" acknowledgment every other action needed
 * and had exactly none of before.
 */
import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const page = usePage();
const toasts = ref([]);
let nextId = 0;

const AUTO_DISMISS_MS = 4000;

const dismiss = (id) => {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
};

const push = (type, message) => {
    const id = nextId++;
    toasts.value.push({ id, type, message });
    setTimeout(() => dismiss(id), AUTO_DISMISS_MS);
};

// Every Inertia navigation carries the shared flash prop along, present
// or not — watching it (rather than reading once on mount) is what
// picks up a *second* toast-worthy redirect without a full page reload
// in between, e.g. saving two clients in a row.
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            push('success', flash.success);
        }

        if (flash?.error) {
            push('error', flash.error);
        }
    },
    { immediate: true, deep: true }
);
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4 sm:items-end sm:px-6">
        <TransitionGroup
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-2 sm:translate-y-0 sm:translate-x-4"
            enter-to-class="opacity-100 translate-y-0 sm:translate-x-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0 sm:translate-x-0"
            leave-to-class="opacity-0 -translate-y-2 sm:translate-y-0 sm:translate-x-4"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border p-4 shadow-lg"
                :class="
                    toast.type === 'success'
                        ? 'border-green-200 bg-green-50'
                        : 'border-red-200 bg-red-50'
                "
                role="status"
            >
                <svg
                    v-if="toast.type === 'success'"
                    class="mt-0.5 h-5 w-5 shrink-0 text-green-500"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg
                    v-else
                    class="mt-0.5 h-5 w-5 shrink-0 text-red-500"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>

                <p
                    class="flex-1 text-sm font-medium"
                    :class="toast.type === 'success' ? 'text-green-700' : 'text-red-700'"
                >
                    {{ toast.message }}
                </p>

                <button
                    type="button"
                    class="shrink-0 rounded-lg p-0.5 text-gray-400 transition hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-accent-500"
                    @click="dismiss(toast.id)"
                >
                    <span class="sr-only">Dismiss</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
