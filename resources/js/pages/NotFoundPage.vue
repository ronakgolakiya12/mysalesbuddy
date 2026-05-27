<script setup lang="ts">
</script>

<template>
    <div class="min-h-[60vh] flex flex-col items-center justify-center text-center">
        <p class="text-sm font-semibold text-indigo-600">404</p>
        <h1 class="mt-2 text-3xl font-bold text-gray-900">Page not found</h1>
        <p class="mt-3 text-sm text-gray-500 max-w-md">
            The page you're looking for doesn't exist or has been moved.
        </p>
        <router-link
            :to="{ name: 'meetings.index' }"
            class="mt-6 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
        >
            Back to meetings
        </router-link>
    </div>
</template>
