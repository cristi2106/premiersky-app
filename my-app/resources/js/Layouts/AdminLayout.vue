<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import BottomNav from '@/Components/BottomNav.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SidebarNav from '@/Components/SidebarNav.vue';
import Toast from '@/Components/Toast.vue';

defineProps({
    title: {
        type: String,
        default: '',
    },
});

const page = usePage();
const sidebarOpen = ref(false);

// Add new modules here as they're built out (Contracts, Quotes).
const navigation = [
    {
        name: 'Dashboard',
        route: 'dashboard',
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    },
    {
        name: 'Clients',
        route: 'clients.index',
        active: 'clients.*',
        icon: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4',
    },
    {
        name: 'Airports',
        route: 'airports.index',
        active: 'airports.*',
        icon: 'M12 21c-4.418-3.5-7-7.239-7-10.5A7 7 0 1119 10.5c0 3.261-2.582 7-7 10.5zM12 13a2.5 2.5 0 100-5 2.5 2.5 0 000 5z',
    },
    {
        name: 'Aircraft Types',
        route: 'aircraft-speed-references.index',
        active: 'aircraft-speed-references.*',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    },
    {
        name: 'Flight Calculator',
        route: 'flight-calculator.index',
        active: 'flight-calculator.*',
        icon: 'M9 7h6m0 10v-3m-3 3v-3m-3 3v-3m9-10H6a2 2 0 00-2 2v14l4-2 4 2 4-2 4 2V5a2 2 0 00-2-2z',
    },
    {
        name: 'Contracts',
        route: 'contracts.index',
        active: 'contracts.*',
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    },
    {
        name: 'Tails',
        route: 'tails.index',
        active: 'tails.*',
        icon: 'M7 7h.01M7 3h5.586a1 1 0 01.707.293l6.414 6.414a1 1 0 010 1.414l-8.586 8.586a1 1 0 01-1.414 0l-6.414-6.414A1 1 0 013 12.586V7a4 4 0 014-4z',
    },
    {
        name: 'Quotes',
        route: 'quotes.index',
        active: 'quotes.*',
        icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    },
];
</script>

<template>
    <div class="flex min-h-screen bg-gray-50">
        <!-- Mobile sidebar backdrop -->
        <Transition
            enter-active-class="transition-opacity ease-linear duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-linear duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-40 bg-gray-900/60 lg:hidden"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- Mobile sidebar panel -->
        <Transition
            enter-active-class="transition ease-in-out duration-200 transform"
            enter-from-class="-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition ease-in-out duration-200 transform"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[80vw] flex-col bg-gray-950 lg:hidden"
            >
                <div class="flex h-16 shrink-0 items-center justify-between px-4">
                    <Link
                        :href="route('dashboard')"
                        class="flex items-center gap-2"
                    >
                        <ApplicationLogo class="h-8 w-8" />
                        <span class="text-lg font-semibold tracking-tight text-white">
                            PremierSky
                        </span>
                    </Link>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-gray-400 hover:bg-white/5 hover:text-white"
                        @click="sidebarOpen = false"
                    >
                        <span class="sr-only">Close sidebar</span>
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <SidebarNav
                    :navigation="navigation"
                    @navigate="sidebarOpen = false"
                />
            </div>
        </Transition>

        <!-- Desktop sidebar -->
        <div
            class="hidden lg:fixed lg:inset-y-0 lg:z-30 lg:flex lg:w-64 lg:flex-col"
        >
            <div class="flex grow flex-col overflow-y-auto bg-gray-950">
                <div class="flex h-16 shrink-0 items-center gap-2 px-6">
                    <ApplicationLogo class="h-8 w-8" />
                    <span class="text-lg font-semibold tracking-tight text-white">
                        PremierSky
                    </span>
                </div>

                <SidebarNav :navigation="navigation" />
            </div>
        </div>

        <!-- Main column -->
        <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
            <!-- Top bar -->
            <div
                class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8"
            >
                <button
                    type="button"
                    class="-m-2.5 p-2.5 text-gray-500 hover:text-gray-700 lg:hidden"
                    @click="sidebarOpen = true"
                >
                    <span class="sr-only">Open sidebar</span>
                    <svg
                        class="h-6 w-6"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </button>

                <h1
                    class="min-w-0 flex-1 truncate text-xl font-semibold tracking-tight text-gray-900"
                >
                    {{ title }}
                </h1>

                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-lg py-1.5 pl-1.5 pr-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none"
                        >
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-900 text-sm font-semibold text-white"
                            >
                                {{ page.props.auth.user.name.charAt(0).toUpperCase() }}
                            </span>
                            <span class="hidden sm:block">
                                {{ page.props.auth.user.name }}
                            </span>
                            <svg
                                class="h-4 w-4 text-gray-400"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </button>
                    </template>

                    <template #content>
                        <div class="border-b border-gray-100 px-4 py-2">
                            <p class="text-sm font-medium text-gray-900">
                                {{ page.props.auth.user.name }}
                            </p>
                            <p class="truncate text-sm text-gray-500">
                                {{ page.props.auth.user.email }}
                            </p>
                        </div>
                        <DropdownLink :href="route('profile.edit')">
                            Profile
                        </DropdownLink>
                        <DropdownLink
                            :href="route('logout')"
                            method="post"
                            as="button"
                        >
                            Log Out
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>

            <!-- Page content -->
            <main class="flex-1">
                <div class="mx-auto max-w-7xl px-4 pb-40 pt-6 sm:px-6 md:pb-6 lg:px-8">
                    <slot />
                </div>
            </main>
        </div>

        <BottomNav />
    </div>

    <Toast />
</template>
