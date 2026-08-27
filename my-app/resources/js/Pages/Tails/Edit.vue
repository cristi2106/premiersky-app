<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PhotoUploadField from '@/Components/PhotoUploadField.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import TextInput from '@/Components/TextInput.vue';
import { TAIL_AMENITIES } from '@/tailAmenities';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    tail: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    _method: 'put',
    tail: props.tail.tail ?? '',
    operator: props.tail.operator ?? '',
    category: props.tail.category ?? '',
    aircraft_speed_reference_id: props.tail.aircraft_speed_reference_id ?? null,
    year_of_make: props.tail.year_of_make ?? '',
    year_of_refurbishment: props.tail.year_of_refurbishment ?? '',
    max_pax: props.tail.max_pax ?? '',
    lavatory: props.tail.lavatory ?? false,
    wifi: props.tail.wifi ?? false,
    bed: props.tail.bed ?? false,
    entertainment_system: props.tail.entertainment_system ?? false,
    pets_allowed: props.tail.pets_allowed ?? false,
    smoking_allowed: props.tail.smoking_allowed ?? false,
    photo_1: null,
    photo_2: null,
    remove_photo_1: false,
    remove_photo_2: false,
});

const removePhoto1 = ref(false);
const removePhoto2 = ref(false);

const selectedType = ref(props.tail.aircraft_speed_reference ?? null);

const aircraftLabel = (aircraft) => aircraft.type_name;

const onTypeSelect = (option) => {
    selectedType.value = option;
};

const cabinFields = () => {
    const type = selectedType.value;

    if (!type) {
        return [];
    }

    return [
        ['Cabin width (m)', type.cabin_width_m],
        ['Cabin height (m)', type.cabin_height_m],
        ['Cabin length (m)', type.cabin_length_m],
        ['Cabin volume (m3)', type.cabin_volume_m3],
        ['Baggage capacity (m3)', type.baggage_capacity_m3],
        ['Seating capacity', type.seating_capacity],
    ].filter(([, value]) => value !== null && value !== undefined && value !== '');
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        remove_photo_1: removePhoto1.value,
        remove_photo_2: removePhoto2.value,
    })).post(route('tails.update', props.tail.id), { forceFormData: true });
};
</script>

<template>
    <Head title="Edit Tail" />

    <AdminLayout title="Edit Tail">
        <div class="card max-w-3xl p-6">
            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="tail" value="Tail Number" />
                        <TextInput
                            id="tail"
                            v-model="form.tail"
                            type="text"
                            class="mt-1 block w-full"
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.tail" />
                    </div>

                    <div>
                        <InputLabel for="operator" value="Operator" />
                        <TextInput
                            id="operator"
                            v-model="form.operator"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.operator" />
                    </div>

                    <div>
                        <InputLabel for="category" value="Category" />
                        <select
                            id="category"
                            v-model="form.category"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-accent-500 focus:ring-accent-500"
                        >
                            <option value="" disabled>Select a category…</option>
                            <option v-for="category in props.categories" :key="category" :value="category">
                                {{ category }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.category" />
                    </div>

                    <div>
                        <InputLabel for="aircraft_type" value="Type" />
                        <SearchableSelect
                            id="aircraft_type"
                            v-model="form.aircraft_speed_reference_id"
                            :search-url="route('aircraft-speed-references.search')"
                            :option-label="aircraftLabel"
                            :initial-label="props.tail.aircraft_speed_reference?.type_name ?? null"
                            placeholder="Search aircraft type…"
                            class="mt-1"
                            @select="onTypeSelect"
                        >
                            <template #option="{ option }">
                                <span class="text-gray-900">{{ option.type_name }}</span>
                            </template>
                        </SearchableSelect>
                        <InputError class="mt-2" :message="form.errors.aircraft_speed_reference_id" />
                    </div>

                    <div>
                        <InputLabel for="year_of_make" value="Year of Make" />
                        <TextInput
                            id="year_of_make"
                            v-model="form.year_of_make"
                            type="number"
                            min="1900"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.year_of_make" />
                    </div>

                    <div>
                        <InputLabel for="year_of_refurbishment" value="Year of Refurbishment" />
                        <TextInput
                            id="year_of_refurbishment"
                            v-model="form.year_of_refurbishment"
                            type="number"
                            min="1900"
                            placeholder="Optional"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.year_of_refurbishment" />
                    </div>

                    <div>
                        <InputLabel for="max_pax" value="Max Pax" />
                        <TextInput
                            id="max_pax"
                            v-model="form.max_pax"
                            type="number"
                            min="1"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.max_pax" />
                    </div>
                </div>

                <div v-if="cabinFields().length" class="rounded-lg bg-gray-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Cabin (reference — from linked type)
                    </p>
                    <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-3">
                        <div v-for="[label, value] in cabinFields()" :key="label" class="flex justify-between gap-2">
                            <dt class="text-gray-500">{{ label }}</dt>
                            <dd class="text-right text-gray-900">{{ value }}</dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-700">Amenities</p>
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <label
                            v-for="[key, label] in TAIL_AMENITIES"
                            :key="key"
                            class="flex items-center gap-2 text-sm text-gray-700"
                        >
                            <Checkbox v-model:checked="form[key]" />
                            {{ label }}
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <PhotoUploadField
                        label="Photo 1"
                        v-model:file="form.photo_1"
                        v-model:remove="removePhoto1"
                        :existing-url="props.tail.photo1_url"
                        :error="form.errors.photo_1"
                    />
                    <PhotoUploadField
                        label="Photo 2"
                        v-model:file="form.photo_2"
                        v-model:remove="removePhoto2"
                        :existing-url="props.tail.photo2_url"
                        :error="form.errors.photo_2"
                    />
                </div>

                <div class="flex items-center justify-end gap-4">
                    <Link
                        :href="route('tails.index')"
                        class="text-sm font-medium text-gray-600 hover:text-gray-900"
                    >
                        Cancel
                    </Link>

                    <PrimaryButton
                        :loading="form.processing"
                    >
                        Save Changes
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
