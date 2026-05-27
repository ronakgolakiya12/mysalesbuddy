import {
    createRouter,
    createWebHistory,
    type RouteRecordRaw,
} from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes: RouteRecordRaw[] = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/auth/LoginPage.vue'),
        meta: { layout: 'auth', guest: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('@/pages/auth/RegisterPage.vue'),
        meta: { layout: 'auth', guest: true },
    },
    {
        path: '/',
        redirect: { name: 'meetings.index' },
    },
    {
        path: '/meetings',
        name: 'meetings.index',
        component: () => import('@/pages/meetings/MeetingsIndexPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/meetings/:id',
        name: 'meetings.show',
        component: () => import('@/pages/meetings/MeetingDetailPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/settings',
        component: () => import('@/pages/settings/SettingsPage.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                redirect: { name: 'settings.notetaker' },
            },
            {
                path: 'notetaker',
                name: 'settings.notetaker',
                component: () => import('@/pages/settings/NotetakerConfigPage.vue'),
            },
            {
                path: 'prompt',
                name: 'settings.prompt',
                component: () => import('@/pages/settings/PromptConfigPage.vue'),
            },
            {
                path: 'calendar',
                name: 'settings.calendar',
                component: () => import('@/pages/settings/CalendarPage.vue'),
            },
            {
                path: 'notifications',
                name: 'settings.notifications',
                component: () => import('@/pages/settings/NotificationsPage.vue'),
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/pages/NotFoundPage.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.initialised) {
        await auth.fetchUser();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'meetings.index' };
    }

    return true;
});

export default router;
