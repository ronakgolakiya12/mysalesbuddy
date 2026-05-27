<script setup lang="ts"></script>

<template>
    <div
        class="min-h-screen flex items-center justify-center bg-gray-50 px-4 py-12"
    >
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-indigo-600">MySalesBuddy</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Your AI sales call coach.
                </p>
            </div>
            <div class="bg-white shadow-md rounded-lg p-8">
                <slot />
            </div>
        </div>
    </div>
</template>
