<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Users, UserPlus } from 'lucide-vue-next';
import ProjectUserAssignForm from './ProjectUserAssignForm.vue';
import { store } from '@/actions/App/Http/Controllers/ProjectUserController';

type AvailableUser = Pick<User, 'id' | 'name' | 'email'>;

const props = defineProps<{
    projectUsers: ProjectUser[];
    canManage?: boolean;
    projectId?: string;
    availableUsers?: AvailableUser[];
}>();

const roleLabel: Record<string, string> = {
    'project-lead': 'Project Lead',
    'team-member': 'Team Member',
};

const roleVariant: Record<string, 'default' | 'secondary'> = {
    'project-lead': 'default',
    'team-member': 'secondary',
};

function initials(name: string): string {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

const isAddDialogOpen = ref(false);

const changeRole = (userId: number, newRole: string) => {
    if (!props.projectId) {
        return;
    }
    router.post(store(props.projectId).url, { user_id: userId, role: newRole }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h2 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Project Team</h2>
            <Button
                v-if="canManage && projectId"
                variant="outline"
                size="sm"
                class="h-8 px-3 text-[10px] font-black uppercase tracking-widest rounded-lg"
                @click="isAddDialogOpen = true"
            >
                <UserPlus class="w-3.5 h-3.5 mr-1.5" />
                Add User
            </Button>
        </div>

        <div v-if="projectUsers.length === 0" class="flex flex-col items-center justify-center gap-3 py-16 text-center">
            <div class="p-4 rounded-full bg-gray-50 dark:bg-gray-700">
                <Users class="w-8 h-8 text-gray-300 dark:text-gray-500" />
            </div>
            <p class="text-sm font-medium text-gray-400 dark:text-gray-500">No team members assigned yet.</p>
        </div>

        <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
            <li
                v-for="user in projectUsers"
                :key="user.id"
                class="flex items-center justify-between gap-4 px-6 py-4"
            >
                <div class="flex items-center gap-4 min-w-0">
                    <Avatar class="h-9 w-9 shrink-0 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
                        <AvatarFallback class="rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-xs font-black">
                            {{ initials(user.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="flex flex-col min-w-0 leading-tight">
                        <span class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ user.name }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ user.email }}</span>
                    </div>
                </div>

                <select
                    v-if="canManage && projectId"
                    :value="user.role"
                    class="h-7 rounded-md border border-gray-200 bg-white px-2 text-[10px] font-black uppercase tracking-wider text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-gray-200"
                    @change="changeRole(user.id, ($event.target as HTMLSelectElement).value)"
                >
                    <option value="team-member">Team Member</option>
                    <option value="project-lead">Project Lead</option>
                </select>

                <Badge v-else :variant="roleVariant[user.role]" class="shrink-0 text-[10px] font-black uppercase tracking-wider">
                    {{ roleLabel[user.role] ?? user.role }}
                </Badge>
            </li>
        </ul>
    </div>

    <Dialog v-if="canManage && projectId" v-model:open="isAddDialogOpen">
        <DialogContent class="sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle>Add Team Member</DialogTitle>
                <DialogDescription>Assign a user to this project with a role.</DialogDescription>
            </DialogHeader>
            <ProjectUserAssignForm
                :project-id="projectId"
                :available-users="availableUsers ?? []"
                @user-added="isAddDialogOpen = false"
            />
        </DialogContent>
    </Dialog>
</template>
