<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    company_name: '',
    address: '',
    vat_code: '',
    iban: '',
    bank_name: '',
});

const submit = () => {
    form.post(route('clients.store'));
};
</script>

<template>
    <Head title="New Client" />

    <AdminLayout title="New Client">
        <div class="max-w-xl overflow-hidden rounded-lg bg-white p-6 shadow-sm">
            <form class="space-y-6" @submit.prevent="submit">
                <div>
                    <InputLabel for="company_name" value="Company Name" />
                    <TextInput
                        id="company_name"
                        v-model="form.company_name"
                        type="text"
                        class="mt-1 block w-full"
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.company_name" />
                </div>

                <div>
                    <InputLabel for="address" value="Address" />
                    <TextInput
                        id="address"
                        v-model="form.address"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.address" />
                </div>

                <div>
                    <InputLabel for="vat_code" value="VAT Code" />
                    <TextInput
                        id="vat_code"
                        v-model="form.vat_code"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.vat_code" />
                </div>

                <div>
                    <InputLabel for="iban" value="IBAN" />
                    <TextInput
                        id="iban"
                        v-model="form.iban"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.iban" />
                </div>

                <div>
                    <InputLabel for="bank_name" value="Bank Account Name" />
                    <TextInput
                        id="bank_name"
                        v-model="form.bank_name"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.bank_name" />
                </div>

                <div class="flex items-center justify-end gap-4">
                    <Link
                        :href="route('clients.index')"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        Cancel
                    </Link>

                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Save Client
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
