<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useAuth } from '@/composables/useAuth';
import FormInput from '@/components/ui/FormInput.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import type { ValidationErrors } from '@/types';

const { login, loading, fieldError } = useAuth();

const form = reactive({ email: '', password: '' });
const errors = ref<ValidationErrors>({});

async function submit(): Promise<void> {
    errors.value = {};
    const result = await login(form.email, form.password);
    if (result !== null) {
        errors.value = result;
    }
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Sign in</h2>
            <p class="text-sm text-gray-500 mt-1">Welcome back. Please sign in to continue.</p>
        </div>

        <FormInput
            v-model="form.email"
            label="Email"
            type="email"
            autocomplete="email"
            required
            :error="fieldError(errors, 'email')"
        />

        <FormInput
            v-model="form.password"
            label="Password"
            type="password"
            autocomplete="current-password"
            required
            :error="fieldError(errors, 'password')"
        />

        <PrimaryButton type="submit" :loading="loading" block>
            {{ loading ? 'Signing in...' : 'Sign in' }}
        </PrimaryButton>

        <p class="text-sm text-center text-gray-600">
            Don't have an account?
            <router-link :to="{ name: 'register' }" class="text-indigo-600 hover:text-indigo-700 font-medium">
                Create one
            </router-link>
        </p>
    </form>
</template>
