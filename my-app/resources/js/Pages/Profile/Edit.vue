<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile" />

    <AdminLayout title="Profile">
        <div class="space-y-6">
            <div class="card p-4 sm:p-6">
                <UpdateProfileInformationForm
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div class="card p-4 sm:p-6">
                <UpdatePasswordForm class="max-w-xl" />
            </div>

            <div class="card p-4 sm:p-6">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </AdminLayout>
</template>
