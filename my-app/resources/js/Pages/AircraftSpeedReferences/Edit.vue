<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    aircraftSpeedReference: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    type_name: props.aircraftSpeedReference.type_name ?? '',
    cruise_speed_knots: props.aircraftSpeedReference.cruise_speed_knots ?? '',
    cabin_width_m: props.aircraftSpeedReference.cabin_width_m ?? '',
    cabin_height_m: props.aircraftSpeedReference.cabin_height_m ?? '',
    cabin_length_m: props.aircraftSpeedReference.cabin_length_m ?? '',
    cabin_volume_m3: props.aircraftSpeedReference.cabin_volume_m3 ?? '',
    baggage_capacity_m3: props.aircraftSpeedReference.baggage_capacity_m3 ?? '',
    seating_capacity: props.aircraftSpeedReference.seating_capacity ?? '',
});

const submit = () => {
    form.put(route('aircraft-speed-references.update', props.aircraftSpeedReference.id));
};
</script>

<template>
    <Head title="Edit Aircraft Type" />

    <AdminLayout title="Edit Aircraft Type">
        <div class="card max-w-xl p-6">
            <form class="space-y-6" @submit.prevent="submit">
                <div>
                    <InputLabel for="type_name" value="Aircraft Type" />
                    <TextInput
                        id="type_name"
                        v-model="form.type_name"
                        type="text"
                        class="mt-1 block w-full"
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.type_name" />
                </div>

                <div>
                    <InputLabel for="cruise_speed_knots" value="Cruise Speed (knots)" />
                    <TextInput
                        id="cruise_speed_knots"
                        v-model="form.cruise_speed_knots"
                        type="number"
                        min="1"
                        max="2000"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.cruise_speed_knots" />
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <InputLabel for="cabin_width_m" value="Cabin Width (m)" />
                        <TextInput
                            id="cabin_width_m"
                            v-model="form.cabin_width_m"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.cabin_width_m" />
                    </div>

                    <div>
                        <InputLabel for="cabin_height_m" value="Cabin Height (m)" />
                        <TextInput
                            id="cabin_height_m"
                            v-model="form.cabin_height_m"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.cabin_height_m" />
                    </div>

                    <div>
                        <InputLabel for="cabin_length_m" value="Cabin Length (m)" />
                        <TextInput
                            id="cabin_length_m"
                            v-model="form.cabin_length_m"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.cabin_length_m" />
                    </div>

                    <div>
                        <InputLabel for="cabin_volume_m3" value="Cabin Volume (m3)" />
                        <TextInput
                            id="cabin_volume_m3"
                            v-model="form.cabin_volume_m3"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.cabin_volume_m3" />
                    </div>

                    <div>
                        <InputLabel for="baggage_capacity_m3" value="Baggage Capacity (m3)" />
                        <TextInput
                            id="baggage_capacity_m3"
                            v-model="form.baggage_capacity_m3"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.baggage_capacity_m3" />
                    </div>

                    <div>
                        <InputLabel for="seating_capacity" value="Seating Capacity" />
                        <TextInput
                            id="seating_capacity"
                            v-model="form.seating_capacity"
                            type="text"
                            placeholder="8–19"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.seating_capacity" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-4">
                    <Link
                        :href="route('aircraft-speed-references.index')"
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
