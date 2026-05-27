<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useAuth } from '@/composables/useAuth';
import FormInput from '@/components/ui/FormInput.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import type { ValidationErrors } from '@/types';

const { register, loading, fieldError } = useAuth();

const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});
const errors = ref<ValidationErrors>({});

async function submit(): Promise<void> {
    errors.value = {};
    const result = await register(
        form.name,
        form.email,
        form.password,
        form.password_confirmation,
    );
    if (result !== null) {
        errors.value = result;
    }
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Create your account</h2>
            <p class="text-sm text-gray-500 mt-1">Start getting AI-powered coaching on every sales call.</p>
        </div>

        <FormInput
            v-model="form.name"
            label="Full name"
            autocomplete="name"
            required
            :error="fieldError(errors, 'name')"
        />

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
            autocomplete="new-password"
            required
            :error="fieldError(errors, 'password')"
        />

        <FormInput
            v-model="form.password_confirmation"
            label="Confirm password"
            type="password"
            autocomplete="new-password"
            required
            :error="fieldError(errors, 'password_confirmation')"
        />

        <PrimaryButton type="submit" :loading="loading" block>
            {{ loading ? 'Creating account...' : 'Create account' }}
        </PrimaryButton>

        <p class="text-sm text-center text-gray-600">
            Already have an account?
            <router-link :to="{ name: 'login' }" class="text-indigo-600 hover:text-indigo-700 font-medium">
                Sign in
            </router-link>
        </p>
    </form>
</template>
