<script setup lang="ts">
import { ref, useTemplateRef } from "vue";
import { onClickOutside } from "@vueuse/core";
import { useAuth } from "@/composables/useAuth";
import AvatarInitials from "@/components/settings/AvatarInitials.vue";
import NotificationBell from "@/components/notifications/NotificationBell.vue";

const { user, logout } = useAuth();

const userMenuOpen = ref(false);
const mobileMenuOpen = ref(false);
const userMenuRef = useTemplateRef<HTMLElement>("userMenuRef");

onClickOutside(userMenuRef, () => {
    userMenuOpen.value = false;
});

async function handleLogout(): Promise<void> {
    userMenuOpen.value = false;
    await logout();
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
            <div
                class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex h-16 items-center justify-between"
            >
                <div class="flex items-center gap-8">
                    <router-link
                        :to="{ name: 'meetings.index' }"
                        class="text-xl font-bold text-indigo-600"
                    >
                        MySalesBuddy
                    </router-link>
                    <nav class="hidden md:flex items-center gap-6 text-sm">
                        <router-link
                            :to="{ name: 'meetings.index' }"
                            class="text-gray-700 hover:text-indigo-600"
                            active-class="text-indigo-600 font-semibold"
                        >
                            Meetings
                        </router-link>
                        <router-link
                            to="/settings"
                            class="text-gray-700 hover:text-indigo-600"
                            active-class="text-indigo-600 font-semibold"
                        >
                            Settings
                        </router-link>
                    </nav>
                </div>

                <div class="flex items-center gap-3">
                    <NotificationBell />

                    <div v-if="user" ref="userMenuRef" class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-full p-1 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :aria-expanded="userMenuOpen"
                            aria-haspopup="true"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            <img
                                v-if="user.notetaker_config?.avatar_url"
                                :src="user.notetaker_config.avatar_url"
                                alt="Avatar"
                                class="h-8 w-8 rounded-full object-cover"
                            />
                            <AvatarInitials v-else :name="user.name" size="sm" />
                            <span
                                class="hidden sm:inline text-sm text-gray-700"
                                >{{ user.name }}</span
                            >
                        </button>
                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 mt-2 w-48 rounded-md border border-gray-200 bg-white shadow-lg py-1"
                            role="menu"
                        >
                            <router-link
                                to="/settings"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                role="menuitem"
                                @click="userMenuOpen = false"
                            >
                                Settings
                            </router-link>
                            <button
                                type="button"
                                class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                role="menuitem"
                                @click="handleLogout"
                            >
                                Sign out
                            </button>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="md:hidden rounded p-2 text-gray-500 hover:bg-gray-100"
                        aria-label="Toggle navigation"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <nav
                v-if="mobileMenuOpen"
                class="md:hidden border-t border-gray-200 bg-white px-4 py-3 flex flex-col gap-3 text-sm"
            >
                <router-link
                    :to="{ name: 'meetings.index' }"
                    class="text-gray-700 hover:text-indigo-600"
                    @click="mobileMenuOpen = false"
                >
                    Meetings
                </router-link>
                <router-link
                    to="/settings"
                    class="text-gray-700 hover:text-indigo-600"
                    @click="mobileMenuOpen = false"
                >
                    Settings
                </router-link>
            </nav>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <slot />
        </main>
    </div>
</template>
