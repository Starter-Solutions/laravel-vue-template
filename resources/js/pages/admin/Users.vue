<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowDownUp,
    ArrowUp,
    BadgeCheck,
    BadgeMinus,
    Ellipsis,
    Plus,
} from '@lucide/vue';
import { useLaravelPagination } from '@starter-solutions/inertia-data-table-vue';
import type { Paginated } from '@starter-solutions/inertia-data-table-vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@starter-solutions/vue-ui/base/alert-dialog';
import { Badge } from '@starter-solutions/vue-ui/base/badge';
import { Button } from '@starter-solutions/vue-ui/base/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@starter-solutions/vue-ui/base/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@starter-solutions/vue-ui/base/dropdown-menu';
import { Input } from '@starter-solutions/vue-ui/base/input';
import { Label } from '@starter-solutions/vue-ui/base/label';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@starter-solutions/vue-ui/base/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@starter-solutions/vue-ui/base/tooltip';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    destroy,
    index,
    passwordReset,
    store,
    update,
} from '@/routes/admin/users';

type ListedUser = {
    id: number;
    name: string;
    email: string;
    email_verified: boolean;
    is_admin: boolean;
};

const verificationStatus = {
    verified: 'Email verified',
    unverified: 'Email not verified',
};

const props = defineProps<{ users: Paginated<ListedUser> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const { data, pagination, getPaginatedRequest } = useLaravelPagination(
    () => props.users,
);

const columns = [
    { key: 'name', title: 'Name' },
    { key: 'email', title: 'Email' },
] as const;

const formOpen = ref(false);
const editingUser = ref<ListedUser | null>(null);
const form = useForm({ name: '', email: '' });
const deleteOpen = ref(false);
const deletingUser = ref<ListedUser | null>(null);
const processingAction = ref(false);

function sortBy(column: (typeof columns)[number]['key']) {
    router.reload({
        only: ['users'],
        data: {
            tableKey: 'users',
            ...getPaginatedRequest({
                page: 1,
                sort_by: column,
                descending:
                    pagination.value.sort_by === column &&
                    !pagination.value.descending,
            }),
        },
    });
}

function goToPage(page: number) {
    router.reload({
        only: ['users'],
        data: {
            tableKey: 'users',
            ...getPaginatedRequest({ page }),
        },
    });
}

function openUserForm(user: ListedUser | null = null) {
    editingUser.value = user;
    form.name = user?.name ?? '';
    form.email = user?.email ?? '';
    form.clearErrors();
    formOpen.value = true;
}

function saveUser() {
    const action = editingUser.value ? update(editingUser.value.id) : store();

    form.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            formOpen.value = false;
        },
    });
}

function sendPasswordReset(user: ListedUser) {
    processingAction.value = true;

    router.visit(passwordReset(user.id), {
        preserveScroll: true,
        onFinish: () => {
            processingAction.value = false;
        },
    });
}

function confirmDelete(user: ListedUser) {
    deletingUser.value = user;
    deleteOpen.value = true;
}

function deleteUser() {
    if (!deletingUser.value) {
        return;
    }

    processingAction.value = true;
    router.visit(destroy(deletingUser.value.id), {
        onSuccess: () => {
            deleteOpen.value = false;
        },
        onFinish: () => {
            processingAction.value = false;
        },
    });
}
</script>

<template>
    <Head title="Users" />

    <section class="flex flex-col gap-4 p-4 sm:p-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold tracking-tight">Users</h1>

            <Button @click="openUserForm()">
                <Plus />
                Create user
            </Button>
        </div>

        <div class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead
                            v-for="column in columns"
                            :key="column.key"
                            :aria-sort="
                                pagination.sort_by === column.key
                                    ? pagination.descending
                                        ? 'descending'
                                        : 'ascending'
                                    : undefined
                            "
                        >
                            <Button
                                variant="ghost"
                                size="sm"
                                class="-ml-2"
                                @click="sortBy(column.key)"
                            >
                                {{ column.title }}
                                <ArrowDownUp
                                    v-if="pagination.sort_by !== column.key"
                                />
                                <ArrowDown v-else-if="pagination.descending" />
                                <ArrowUp v-else />
                            </Button>
                        </TableHead>
                        <TableHead class="w-12">
                            <span class="sr-only">Actions</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableEmpty v-if="!data.length" :colspan="3">
                        No users found.
                    </TableEmpty>
                    <TableRow v-for="user in data" :key="user.id">
                        <TableCell>
                            <div class="flex items-center gap-2 font-medium">
                                {{ user.name }}
                                <Badge v-if="user.is_admin" variant="secondary">
                                    Admin
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center gap-2">
                                {{ user.email }}
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <span
                                                role="img"
                                                tabindex="0"
                                                class="inline-flex shrink-0 rounded-sm"
                                                :aria-label="
                                                    user.email_verified
                                                        ? verificationStatus.verified
                                                        : verificationStatus.unverified
                                                "
                                            >
                                                <BadgeCheck
                                                    v-if="user.email_verified"
                                                    aria-hidden="true"
                                                    class="size-4 text-blue-600 dark:text-blue-400"
                                                />
                                                <BadgeMinus
                                                    v-else
                                                    aria-hidden="true"
                                                    class="size-4 text-muted-foreground"
                                                />
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            {{
                                                user.email_verified
                                                    ? verificationStatus.verified
                                                    : verificationStatus.unverified
                                            }}
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </TableCell>
                        <TableCell class="text-right">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="'Actions for ' + user.name"
                                        :disabled="processingAction"
                                    >
                                        <Ellipsis />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        @select="openUserForm(user)"
                                    >
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @select="sendPasswordReset(user)"
                                    >
                                        Password reset
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        :disabled="user.is_admin"
                                        @select="confirmDelete(user)"
                                    >
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="pagination.last_page > 1 || pagination.current_page > 1"
            class="flex flex-wrap items-center justify-between gap-2"
        >
            <p class="text-sm text-muted-foreground">
                Page {{ pagination.current_page }} of {{ pagination.last_page }}
            </p>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!pagination.prev_page_url"
                    @click="
                        goToPage(
                            Math.min(
                                pagination.current_page - 1,
                                pagination.last_page,
                            ),
                        )
                    "
                >
                    Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!pagination.next_page_url"
                    @click="goToPage(pagination.current_page + 1)"
                >
                    Next
                </Button>
            </div>
        </div>
    </section>

    <Dialog v-model:open="formOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    editingUser ? 'Edit user' : 'Create user'
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        editingUser
                            ? 'Update the name and email for this account.'
                            : 'An invitation will be sent to this email address.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="saveUser">
                <div class="grid gap-2">
                    <Label for="user-name">Name</Label>
                    <Input id="user-name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="user-email">Email</Label>
                    <Input
                        id="user-email"
                        v-model="form.email"
                        type="email"
                        :readonly="editingUser?.is_admin"
                        required
                    />
                    <InputError :message="form.errors.email" />
                </div>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing">
                        {{ editingUser ? 'Save changes' : 'Create user' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="deleteOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete user?</AlertDialogTitle>
                <AlertDialogDescription>
                    This permanently deletes the account for
                    {{ deletingUser?.email }}.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="processingAction"
                    >Cancel</AlertDialogCancel
                >
                <Button
                    variant="destructive"
                    :disabled="processingAction"
                    @click="deleteUser"
                >
                    Delete user
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
