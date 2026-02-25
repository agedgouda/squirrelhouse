<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import projectRoutes from '@/routes/projects/index';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Loader2 } from 'lucide-vue-next'; // Ensure this is imported
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue
} from '@/components/ui/select';

const props = defineProps<{
    project?: any;
    clients?: any[];
    projectTypes: any[];
    initialClientId?: string | number;
}>();

const emit = defineEmits(['success']);

const form = useForm({
    name: props.project?.name ?? '',
    // Use empty string to ensure the SelectValue has a "target"
    client_id: props.project?.client_id?.toString() ?? props.initialClientId?.toString() ?? '',
    project_type_id: props.project?.project_type_id?.toString() ?? '',
    description: props.project?.description ?? '',
    budget: props.project?.budget?.toString() ?? '',
    launch_date: props.project?.launch_date?.substring(0, 10) ?? '',
    status: props.project?.status ?? 'On Track',
});

const submit = () => {
    const url = props.project
        ? projectRoutes.update.url(props.project.id)
        : projectRoutes.store.url();

    const method = props.project ? 'put' : 'post';

    form[method](url, {
        preserveScroll: true,
        onSuccess: () => {
            if (!props.project) {
                form.reset();
            }
            emit('success');
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-5">
        <div class="grid gap-2">
            <Label :class="{ 'text-destructive': form.errors.name }">Project Name</Label>
            <Input v-model="form.name" placeholder="Name" required />
            <p v-if="form.errors.name" class="text-xs text-destructive font-medium">{{ form.errors.name }}</p>
        </div>

        <div v-if="!initialClientId && !project" class="grid gap-2">
            <Label :class="{ 'text-destructive': form.errors.client_id }">Client</Label>
            <Select v-model="form.client_id">
                <SelectTrigger :class="['w-full h-10 flex items-center justify-between px-3', form.errors.client_id ? 'border-destructive' : '']">
                    <SelectValue placeholder="Select Client" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="client in clients" :key="client.id" :value="client.id.toString()">
                        {{ client.company_name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p v-if="form.errors.client_id" class="text-xs text-destructive font-medium">{{ form.errors.client_id }}</p>
        </div>

        <div class="grid gap-2" v-if="!project">
            <Label :class="{ 'text-destructive': form.errors.project_type_id }">Type</Label>
            <Select v-model="form.project_type_id">
                <SelectTrigger :class="['w-full h-10 flex items-center justify-between px-3', form.errors.project_type_id ? 'border-destructive' : '']">
                    <SelectValue placeholder="Select Type" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="type in projectTypes" :key="type.id" :value="type.id.toString()">
                        {{ type.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p v-if="form.errors.project_type_id" class="text-xs text-destructive font-medium">{{ form.errors.project_type_id }}</p>
        </div>

        <div class="space-y-2">
            <Label :class="{ 'text-destructive': form.errors.description }">Description</Label>
            <Textarea
                v-model="form.description"
                placeholder="Description..."
                rows="3"
            ></Textarea>
            <p v-if="form.errors.description" class="text-xs text-destructive font-medium">{{ form.errors.description }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label :class="{ 'text-destructive': form.errors.budget }">Budget</Label>
                <Input
                    v-model="form.budget"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                />
                <p v-if="form.errors.budget" class="text-xs text-destructive font-medium">{{ form.errors.budget }}</p>
            </div>

            <div class="grid gap-2">
                <Label :class="{ 'text-destructive': form.errors.launch_date }">Launch Date</Label>
                <Input v-model="form.launch_date" type="date" />
                <p v-if="form.errors.launch_date" class="text-xs text-destructive font-medium">{{ form.errors.launch_date }}</p>
            </div>
        </div>

        <div class="grid gap-2">
            <Label :class="{ 'text-destructive': form.errors.status }">Status</Label>
            <Select v-model="form.status">
                <SelectTrigger :class="['w-full h-10 flex items-center justify-between px-3', form.errors.status ? 'border-destructive' : '']">
                    <SelectValue placeholder="Select Status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="On Track">On Track</SelectItem>
                    <SelectItem value="At Risk">At Risk</SelectItem>
                    <SelectItem value="Delayed">Delayed</SelectItem>
                </SelectContent>
            </Select>
            <p v-if="form.errors.status" class="text-xs text-destructive font-medium">{{ form.errors.status }}</p>
        </div>

        <Button
            type="submit"
            :disabled="form.processing"
            class="w-full bg-indigo-600 hover:bg-indigo-700 font-bold transition-all active:scale-[0.98]"
        >
            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
            {{ project ? 'Update Project' : 'Save Project' }}
        </Button>
    </form>
</template>
