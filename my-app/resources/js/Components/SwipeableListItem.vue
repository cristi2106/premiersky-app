<script setup>
/**
 * Wraps a mobile stacked-card row with an iOS Mail-style swipe-to-delete
 * gesture: swiping left reveals a red "Delete" action behind the card.
 * Tapping that action only emits `delete` — it never deletes anything
 * itself, so callers keep using their existing confirm-and-delete flow
 * (e.g. `@delete="confirmDeletion(client)"` wired to the same modal every
 * module already shows). This keeps swiping a "reveal" gesture, not a
 * destructive one.
 *
 * Used by the "Mobile stacked cards" section of each Index page (Clients,
 * Airports, Contracts, Tails, …) so the gesture stays identical everywhere
 * and any new module gets it for free.
 */
import { onBeforeUnmount, ref } from 'vue';
import { clearActive, openExclusive } from '@/swipeActionRegistry';

const props = defineProps({
    // Width (px) of the revealed delete action, and how far the card slides.
    actionWidth: {
        type: Number,
        default: 88,
    },
    // Screen-reader label for the delete action, e.g. "Delete Acme Corp".
    deleteLabel: {
        type: String,
        default: 'Delete',
    },
    // Turns off the swipe gesture entirely; renders as a plain card.
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['delete']);

// How far (px) a drag must travel before it's treated as horizontal swipe
// rather than a vertical scroll or a plain tap.
const DRAG_THRESHOLD = 8;

const translateX = ref(0);
const isDragging = ref(false);
const isOpen = ref(false);

let startX = 0;
let startY = 0;
let startTranslate = 0;
let axis = null; // 'x' | 'y' | null while a touch is in progress

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

const close = () => {
    isOpen.value = false;
    translateX.value = 0;
    clearActive(close);
};

defineExpose({ close });
onBeforeUnmount(() => clearActive(close));

const onTouchStart = (event) => {
    if (props.disabled || event.touches.length !== 1) {
        return;
    }

    const touch = event.touches[0];
    startX = touch.clientX;
    startY = touch.clientY;
    startTranslate = translateX.value;
    axis = null;
    isDragging.value = true;
};

const onTouchMove = (event) => {
    if (!isDragging.value) {
        return;
    }

    const touch = event.touches[0];
    const dx = touch.clientX - startX;
    const dy = touch.clientY - startY;

    if (axis === null) {
        if (Math.abs(dx) < DRAG_THRESHOLD && Math.abs(dy) < DRAG_THRESHOLD) {
            return;
        }

        axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';

        if (axis === 'y') {
            // Vertical intent: hand the gesture back to the browser so the
            // list keeps scrolling normally.
            isDragging.value = false;
            return;
        }
    }

    // Horizontal drag: move the card and stop the page from doing anything
    // else with this touch (text selection, overscroll glow, etc).
    event.preventDefault();

    const next = startTranslate + dx;
    // Rubber-band slightly past the fully-open position, never past closed.
    translateX.value =
        next < -props.actionWidth
            ? -props.actionWidth + (next + props.actionWidth) * 0.25
            : clamp(next, -props.actionWidth - 24, 0);
};

const onTouchEnd = () => {
    if (!isDragging.value) {
        return;
    }

    isDragging.value = false;

    if (axis !== 'x') {
        return;
    }

    const shouldOpen = translateX.value <= -props.actionWidth / 2;
    translateX.value = shouldOpen ? -props.actionWidth : 0;
    isOpen.value = shouldOpen;

    if (shouldOpen) {
        openExclusive(close);
    } else {
        clearActive(close);
    }
};

const onOverlayClick = () => {
    // While revealed, tapping the card itself closes it again instead of
    // activating whatever link/button sits underneath — matching iOS Mail.
    close();
};

const onDeleteClick = () => {
    close();
    emit('delete');
};
</script>

<template>
    <div class="relative overflow-hidden rounded-xl">
        <div
            v-if="!disabled"
            class="absolute inset-y-0 right-0 flex"
            :style="{ width: `${actionWidth}px` }"
        >
            <button
                type="button"
                class="flex w-full cursor-pointer items-center justify-center bg-red-600 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-400"
                :aria-label="deleteLabel"
                @click="onDeleteClick"
            >
                Delete
            </button>
        </div>

        <div
            class="card relative touch-pan-y select-none p-4"
            :style="{
                transform: `translateX(${translateX}px)`,
                transition: isDragging ? 'none' : 'transform 200ms ease-out',
            }"
            @touchstart="onTouchStart"
            @touchmove="onTouchMove"
            @touchend="onTouchEnd"
            @touchcancel="onTouchEnd"
        >
            <slot />

            <div
                v-if="isOpen"
                class="absolute inset-0"
                @click="onOverlayClick"
            />
        </div>
    </div>
</template>
