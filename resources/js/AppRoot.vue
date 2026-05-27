<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import ToastContainer from '@/components/ui/ToastContainer.vue';

const route = useRoute();
const router = useRouter();
const ready = ref(false);

router.isReady().then(() => {
    ready.value = true;
});

const layout = computed(() =>
    route.meta.layout === 'auth' ? AuthLayout : AppLayout,
);
</script>

<template>
    <component :is="layout" v-if="ready">
        <router-view />
    </component>
    <ToastContainer />
</template>
