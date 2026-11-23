<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@starter-solutions/vue-ui/base/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@starter-solutions/vue-ui/base/dropdown-menu';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@starter-solutions/vue-ui/base/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavGroup } from '@/types';

defineProps<{
    groups: NavGroup[];
}>();

const { isCurrentUrl } = useCurrentUrl();
const { isMobile, setOpenMobile, state } = useSidebar();
const isCollapsed = computed(
    () => !isMobile.value && state.value === 'collapsed',
);

function closeMobileMenu(): void {
    if (isMobile.value) {
        setOpenMobile(false);
    }
}
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <template v-for="group in groups" :key="group.title">
                <SidebarMenuItem v-if="isCollapsed">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton
                                :tooltip="group.title"
                                :is-active="
                                    group.items.some((item) =>
                                        isCurrentUrl(item.href),
                                    )
                                "
                            >
                                <component v-if="group.icon" :is="group.icon" />
                                <span>{{ group.title }}</span>
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            side="right"
                            align="start"
                            :side-offset="8"
                            class="min-w-56 rounded-lg"
                        >
                            <DropdownMenuLabel>
                                {{ group.title }}
                            </DropdownMenuLabel>
                            <DropdownMenuItem
                                v-for="item in group.items"
                                :key="item.title"
                                as-child
                            >
                                <Link
                                    :href="item.href"
                                    :aria-current="
                                        isCurrentUrl(item.href)
                                            ? 'page'
                                            : undefined
                                    "
                                    class="flex w-full cursor-pointer items-center gap-2"
                                >
                                    <component
                                        v-if="item.icon"
                                        :is="item.icon"
                                        class="size-4"
                                    />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>

                <Collapsible
                    v-else
                    as-child
                    :default-open="
                        group.items.some((item) => isCurrentUrl(item.href))
                    "
                    class="group/collapsible"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton :tooltip="group.title">
                                <component v-if="group.icon" :is="group.icon" />
                                <span>{{ group.title }}</span>
                                <ChevronRight
                                    class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="item in group.items"
                                    :key="item.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="isCurrentUrl(item.href)"
                                    >
                                        <Link
                                            :href="item.href"
                                            :aria-current="
                                                isCurrentUrl(item.href)
                                                    ? 'page'
                                                    : undefined
                                            "
                                            @click="closeMobileMenu"
                                        >
                                            <component
                                                v-if="item.icon"
                                                :is="item.icon"
                                            />
                                            <span>{{ item.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
