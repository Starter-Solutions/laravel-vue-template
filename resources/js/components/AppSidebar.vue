<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    FolderGit2,
    Home,
    Layers,
    LayoutGrid,
    Shield,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@starter-solutions/vue-ui/base/sidebar';
import { dashboard } from '@/routes';
import { index as usersIndex } from '@/routes/admin/users';
import type { NavGroup, NavItem } from '@/types';

const page = usePage();

const adminGroup: NavGroup = {
    title: 'Admin',
    icon: Shield,
    items: [{ title: 'Users', href: usersIndex(), icon: Users }],
};

const navGroups: NavGroup[] = [
    {
        title: 'Platform',
        icon: Layers,
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
        ],
    },
];

const visibleNavGroups = computed(() =>
    page.props.auth.canManageUsers ? [adminGroup, ...navGroups] : navGroups,
);

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/Starter-Solutions/laravel-vue-template',
        icon: FolderGit2,
    },
    {
        title: 'Home',
        href: 'https://laravel-vue-template.on-forge.com/',
        icon: Home,
    },
];
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        class="group-data-[collapsible=icon]:pr-0"
    >
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="visibleNavGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
