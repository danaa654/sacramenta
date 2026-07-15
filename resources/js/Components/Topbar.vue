<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link } from '@inertiajs/vue3';

defineEmits(['toggle-sidebar']);
</script>

<template>
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-white/40 bg-white/50 px-4 backdrop-blur-md sm:px-6 lg:px-8">
        <!-- Mobile: hamburger -->
        <button
            type="button"
            class="inline-flex items-center justify-center rounded-md p-2 text-[#3f6470] hover:bg-[#E4EDE1]/60 lg:hidden"
            @click="$emit('toggle-sidebar')"
        >
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <div class="hidden lg:block"></div>

        <div class="flex items-center gap-4">
            <Dropdown align="right" width="48">
                <template #trigger>
                    <span class="inline-flex rounded-full">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-full border border-transparent bg-white/70 px-4 py-2 text-sm font-medium leading-4 text-[#3f6470] shadow-sm ring-1 ring-white/60 transition duration-200 ease-in-out hover:bg-[#E4EDE1]/80 hover:text-[#3f6470] hover:shadow-md focus:outline-none"
                        >
                            {{ $page.props.auth.user.name }}

                            <svg
                                class="-me-0.5 ms-2 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
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
                    </span>
                </template>

                <template #content>
                    <DropdownLink :href="route('profile.edit')">
                        Profile
                    </DropdownLink>
                    <DropdownLink :href="route('logout')" method="post" as="button">
                        Log Out
                    </DropdownLink>
                </template>
            </Dropdown>
        </div>
    </header>
</template>