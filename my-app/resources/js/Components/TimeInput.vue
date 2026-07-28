<script setup>
import { computed, ref, watch } from 'vue';

// Lets someone type "1100" and land on "11:00" without having to hop
// between the hour/minute segments the native <input type="time"> forces.
const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    id: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue']);

const toDigits = (value) => (value ?? '').replace(/\D/g, '').slice(0, 4);

const digits = ref(toDigits(props.modelValue));

const display = computed(() =>
    digits.value.length <= 2 ? digits.value : `${digits.value.slice(0, 2)}:${digits.value.slice(2)}`
);

watch(
    () => props.modelValue,
    (value) => {
        const nextDigits = toDigits(value);
        if (nextDigits !== digits.value) {
            digits.value = nextDigits;
        }
    }
);

// Only emit on a complete or fully-cleared value — echoing back an
// intermediate '' for a 1-3 digit in-progress value round-trips through
// the modelValue watcher above and wipes out what's mid-typing.
const emitValue = () => {
    if (digits.value.length === 4) {
        emit('update:modelValue', `${digits.value.slice(0, 2)}:${digits.value.slice(2)}`);
    } else if (digits.value.length === 0) {
        emit('update:modelValue', '');
    }
};

const onInput = (event) => {
    digits.value = toDigits(event.target.value);
    event.target.value = display.value;
    emitValue();
};

const onKeydown = (event) => {
    if (event.key === 'Backspace') {
        event.preventDefault();
        digits.value = digits.value.slice(0, -1);
        emitValue();
    }
};
</script>

<template>
    <input
        :id="id"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        maxlength="5"
        placeholder="HH:MM"
        :value="display"
        class="rounded-lg border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:border-accent-500 focus:ring-accent-500"
        @input="onInput"
        @keydown="onKeydown"
    />
</template>
