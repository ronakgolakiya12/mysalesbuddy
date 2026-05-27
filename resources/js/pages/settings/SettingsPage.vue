<script setup lang="ts">
</script>

<template>
    <div class="grid gap-8 md:grid-cols-[220px_1fr]">
        <aside>
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Settings</h2>
            <nav class="space-y-1 text-sm">
                <router-link
                    :to="{ name: 'settings.notetaker' }"
                    class="block rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100"
                    active-class="bg-indigo-50 text-indigo-700 font-medium"
                >
                    Notetaker
                </router-link>
                <router-link
                    :to="{ name: 'settings.prompt' }"
                    class="block rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100"
                    active-class="bg-indigo-50 text-indigo-700 font-medium"
                >
                    Coaching prompt
                </router-link>
                <router-link
                    :to="{ name: 'settings.calendar' }"
                    class="block rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100"
                    active-class="bg-indigo-50 text-indigo-700 font-medium"
                >
                    Calendar
                </router-link>
                <router-link
                    :to="{ name: 'settings.notifications' }"
                    class="block rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100"
                    active-class="bg-indigo-50 text-indigo-700 font-medium"
                >
                    Notifications
                </router-link>
            </nav>
        </aside>
        <section class="bg-white rounded-lg border border-gray-200 p-6">
            <router-view />
        </section>
    </div>
</template>
