<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    icao_code: '',
    iata_code: '',
    country: '',
    latitude: '',
    longitude: '',
});

const submit = () => {
    form.post(route('airports.store'));
};
</script>

<template>
    <Head title="New Airport" />

    <AdminLayout title="New Airport">
        <div class="card max-w-xl p-6">
            <form class="space-y-6" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="Airport Name" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="icao_code" value="ICAO Code" />
                        <TextInput
                            id="icao_code"
                            v-model="form.icao_code"
                            type="text"
                            maxlength="4"
                            class="mt-1 block w-full uppercase"
                            placeholder="LROP"
                        />
                        <InputError class="mt-2" :message="form.errors.icao_code" />
                    </div>

                    <div>
                        <InputLabel for="iata_code" value="IATA Code (optional)" />
                        <TextInput
                            id="iata_code"
                            v-model="form.iata_code"
                            type="text"
                            maxlength="3"
                            class="mt-1 block w-full uppercase"
                            placeholder="OTP"
                        />
                        <InputError class="mt-2" :message="form.errors.iata_code" />
                    </div>
                </div>

                <div>
                    <InputLabel for="country" value="Country" />
                    <TextInput
                        id="country"
                        v-model="form.country"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.country" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="latitude" value="Latitude" />
                        <TextInput
                            id="latitude"
                            v-model="form.latitude"
                            type="number"
                            step="any"
                            min="-90"
                            max="90"
                            class="mt-1 block w-full"
                            placeholder="44.5711"
                        />
                        <InputError class="mt-2" :message="form.errors.latitude" />
                    </div>

                    <div>
                        <InputLabel for="longitude" value="Longitude" />
                        <TextInput
                            id="longitude"
                            v-model="form.longitude"
                            type="number"
                            step="any"
                            min="-180"
                            max="180"
                            class="mt-1 block w-full"
                            placeholder="26.1027"
                        />
                        <InputError class="mt-2" :message="form.errors.longitude" />
                    </div>
                </div>

                <p class="text-xs text-gray-500">
                    Timezone is calculated automatically from the coordinates
                    above once saved.
                </p>

                <div class="flex items-center justify-end gap-4">
                    <Link
                        :href="route('airports.index')"
                        class="text-sm font-medium text-gray-600 hover:text-gray-900"
                    >
                        Cancel
                    </Link>

                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Save Airport
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
