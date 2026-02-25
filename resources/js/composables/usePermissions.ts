import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
    const page = usePage<AppPageProps>();
    const user = computed(() => page.props.auth.user);

    const hasRole = (name: string) => user.value?.roles.includes(name);
    const hasPermission = (name: string) => user.value?.permissions.includes(name);
    const isAdmin = computed(() => user.value?.is_super === true || user.value?.roles.includes('org-admin') === true);

    return { hasRole, hasPermission, isAdmin };
}
