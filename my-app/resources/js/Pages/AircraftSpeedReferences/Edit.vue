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

                <div class="flex items-center justify-end gap-4">
                    <Link
                        :href="route('aircraft-speed-references.index')"
                        class="text-sm font-medium text-gray-600 hover:text-gray-900"
                    >
                        Cancel
                    </Link>

                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Save Changes
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
