<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    existingUrl: {
        type: String,
        default: null,
    },
    error: {
        type: String,
        default: null,
    },
});

// v-model:file for the newly-chosen upload (or null), v-model:remove for
// the "clear the existing photo" flag — kept separate so the parent form
// can tell "leave as-is" apart from "delete this photo" on submit.
const file = defineModel('file', { type: File, default: null });
const remove = defineModel('remove', { type: Boolean, default: false });

const input = ref(null);
const previewUrl = ref(null);

watch(file, (newFile) => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }

    if (newFile) {
        previewUrl.value = URL.createObjectURL(newFile);
    }
});

onBeforeUnmount(() => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});

const displayUrl = computed(() => previewUrl.value ?? (!remove.value ? props.existingUrl : null));

const onFileChange = (event) => {
    file.value = event.target.files[0] ?? null;
};

const chooseFile = () => {
    input.value?.click();
};

const clearSelection = () => {
    file.value = null;
    if (input.value) {
        input.value.value = '';
    }
};

const removeExisting = () => {
    remove.value = true;
};

const undoRemove = () => {
    remove.value = false;
};
</script>

<template>
    <div>
        <p class="block text-sm font-medium text-gray-700">{{ label }}</p>

        <input ref="input" type="file" accept="image/*" class="hidden" @change="onFileChange" />

        <div v-if="displayUrl" class="mt-2">
            <img
                :src="displayUrl"
                :alt="label"
                class="aspect-video w-full max-w-xs rounded-lg border border-gray-200 object-cover"
            />
            <div class="mt-2 flex items-center gap-3 text-sm font-medium">
                <button
                    type="button"
                    class="cursor-pointer text-accent-600 hover:text-accent-700"
                    @click="chooseFile"
                >
                    Replace
                </button>
                <button
                    v-if="file"
                    type="button"
                    class="cursor-pointer text-gray-500 hover:text-gray-700"
                    @click="clearSelection"
                >
                    Cancel
                </button>
                <button
                    v-else
                    type="button"
                    class="cursor-pointer text-red-600 hover:text-red-700"
                    @click="removeExisting"
                >
                    Remove
                </button>
            </div>
        </div>

        <div v-else-if="remove" class="mt-2 flex items-center gap-3 rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500">
            <span>Photo will be removed on save.</span>
            <button type="button" class="cursor-pointer font-medium text-accent-600 hover:text-accent-700" @click="undoRemove">
                Undo
            </button>
        </div>

        <div v-else class="mt-2">
            <SecondaryButton type="button" @click="chooseFile">Upload photo</SecondaryButton>
        </div>

        <InputError class="mt-2" :message="error" />
    </div>
</template>
