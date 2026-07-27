<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: null,
    },
    searchUrl: {
        type: String,
        required: true,
    },
    optionLabel: {
        type: Function,
        required: true,
    },
    placeholder: {
        type: String,
        default: 'Search…',
    },
    id: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'select']);

const query = ref('');
const results = ref([]);
const open = ref(false);
const loading = ref(false);
const searched = ref(false);
const highlightedIndex = ref(-1);

let debounceTimer = null;
let requestToken = 0;

const runSearch = async (search) => {
    const token = ++requestToken;
    loading.value = true;

    try {
        const response = await window.axios.get(props.searchUrl, { params: { search } });

        if (token !== requestToken) {
            return;
        }

        results.value = response.data;
        highlightedIndex.value = results.value.length > 0 ? 0 : -1;
        searched.value = true;
    } finally {
        if (token === requestToken) {
            loading.value = false;
        }
    }
};

const scheduleSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => runSearch(query.value), 250);
};

const onInput = () => {
    open.value = true;

    if (props.modelValue !== null) {
        emit('update:modelValue', null);
        emit('select', null);
    }

    scheduleSearch();
};

const onFocus = () => {
    open.value = true;

    if (!searched.value) {
        runSearch(query.value);
    }
};

const select = (option) => {
    emit('update:modelValue', option.id);
    emit('select', option);
    query.value = props.optionLabel(option);
    open.value = false;
};

const clear = () => {
    emit('update:modelValue', null);
    emit('select', null);
    query.value = '';
    searched.value = false;
    results.value = [];
};

const close = () => {
    open.value = false;
};

const onKeydown = (event) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!open.value) {
            onFocus();
        }
        open.value = true;
        highlightedIndex.value = Math.min(highlightedIndex.value + 1, results.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        if (open.value && highlightedIndex.value >= 0 && results.value[highlightedIndex.value]) {
            select(results.value[highlightedIndex.value]);
        }
    } else if (event.key === 'Escape') {
        close();
    }
};

watch(
    () => props.modelValue,
    (value) => {
        if (value === null) {
            query.value = '';
        }
    }
);
</script>

<template>
    <div class="relative">
        <div v-show="open" class="fixed inset-0 z-40" @click="close" />

        <div class="relative">
            <input
                :id="id"
                v-model="query"
                type="text"
                autocomplete="off"
                :placeholder="placeholder"
                class="w-full rounded-lg border-gray-300 py-2 pl-3 pr-9 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:border-accent-500 focus:ring-accent-500"
                @input="onInput"
                @focus="onFocus"
                @keydown="onKeydown"
            />

            <button
                v-if="modelValue !== null"
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none"
                @click="clear"
            >
                <span class="sr-only">Clear</span>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div
            v-show="open"
            class="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-md"
        >
            <div v-if="loading" class="px-3 py-2 text-sm text-gray-500">
                Searching…
            </div>

            <div v-else-if="results.length === 0" class="px-3 py-2 text-sm text-gray-500">
                No matches found.
            </div>

            <ul v-else>
                <li
                    v-for="(option, index) in results"
                    :key="option.id"
                    class="cursor-pointer px-3 py-2 text-sm"
                    :class="index === highlightedIndex ? 'bg-accent-50 text-accent-700' : 'text-gray-900 hover:bg-gray-50'"
                    @mousedown.prevent="select(option)"
                    @mouseenter="highlightedIndex = index"
                >
                    <slot name="option" :option="option">
                        {{ optionLabel(option) }}
                    </slot>
                </li>
            </ul>
        </div>
    </div>
</template>
