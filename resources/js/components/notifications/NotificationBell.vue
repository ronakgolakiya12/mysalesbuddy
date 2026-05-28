<script setup lang="ts">
import { ref, useTemplateRef } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { useNotificationsStore } from '@/stores/notifications';
import { useNotifications } from '@/composables/useNotifications';
import { useToast } from '@/composables/useToast';
import NotificationItem from '@/components/notifications/NotificationItem.vue';

const store = useNotificationsStore();
const toast = useToast();
const isOpen = ref(false);
const bellContainerRef = useTemplateRef<HTMLElement>('bellContainerRef');

useNotifications();

void store.fetch();

onClickOutside(bellContainerRef, () => {
    isOpen.value = false;
});

function toggle(): void {
    isOpen.value = !isOpen.value;
}

async function handleDismiss(id: string): Promise<void> {
    try {
        await store.markRead(id);
    } catch {
        toast.error('Could not mark as read.');
    }
}

async function handleMarkAllRead(): Promise<void> {
    try {
        await store.markAllRead();
    } catch {
        toast.error('Could not mark all as read.');
    }
}

function handleNavigate(): void {
    isOpen.value = false;
}
</script>

<template>
    <div ref="bellContainerRef" class="relative">
        <button
            type="button"
            class="relative rounded-full p-2 text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            aria-label="Notifications"
            :aria-expanded="isOpen"
            aria-haspopup="true"
            @click="toggle"
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
                    d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                />
            </svg>
            <span
                v-if="store.unreadCount > 0"
                class="absolute top-0 right-0 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"
                data-testid="unread-badge"
            >{{ store.unreadCount > 9 ? '9+' : store.unreadCount }}</span>
        </button>

        <Transition name="dropdown">
            <div
                v-if="isOpen"
                class="absolute right-0 top-12 z-20 w-80 max-w-[90vw] rounded-md border border-gray-200 bg-white shadow-lg"
                role="dialog"
                aria-label="Notifications"
            >
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                    <button
                        v-if="store.unreadCount > 0"
                        type="button"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-700"
                        @click="handleMarkAllRead"
                    >
                        Mark all read
                    </button>
                </div>

                <div v-if="store.loading && store.unreadNotifications.length === 0" class="space-y-2 px-4 py-4">
                    <div v-for="i in 3" :key="i" class="flex gap-3">
                        <div class="h-9 w-9 animate-pulse rounded-full bg-gray-200" />
                        <div class="flex-1 space-y-2">
                            <div class="h-3 w-3/4 animate-pulse rounded bg-gray-200" />
                            <div class="h-3 w-1/2 animate-pulse rounded bg-gray-100" />
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="store.unreadNotifications.length === 0"
                    class="px-4 py-10 text-center text-sm text-gray-500"
                    data-testid="empty-state"
                >
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-emerald-600">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    You're all caught up.
                </div>

                <ul v-else class="max-h-96 divide-y divide-gray-100 overflow-y-auto">
                    <NotificationItem
                        v-for="notification in store.unreadNotifications"
                        :key="notification.id"
                        :notification="notification"
                        @dismiss="handleDismiss"
                        @navigate="handleNavigate"
                    />
                </ul>

                <div class="border-t border-gray-200 px-4 py-2 text-right">
                    <router-link
                        :to="{ name: 'settings.notifications' }"
                        class="text-xs font-medium text-gray-600 hover:text-indigo-600"
                        @click="isOpen = false"
                    >
                        Notification settings
                    </router-link>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
