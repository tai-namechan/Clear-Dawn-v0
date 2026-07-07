<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

interface Props {
    /** AppSidebarHeader 等、狭いヘッダー行向けのコンパクト表示 */
    compact?: boolean;
}

withDefaults(defineProps<Props>(), {
    compact: false,
});

const page = usePage();
const user = computed(() => page.props.auth.user! as User);
const { getInitials } = useInitials();

const showAvatar = computed(
    () => user.value.avatar && user.value.avatar !== '',
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                data-test="header-user-menu-button"
                class="group flex shrink-0 items-center rounded-md px-2 py-1.5 transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="compact ? 'gap-2' : 'gap-2.5'"
            >
                <Avatar
                    class="overflow-hidden rounded-lg"
                    :class="compact ? 'h-7 w-7' : 'h-8 w-8'"
                >
                    <AvatarImage
                        v-if="showAvatar"
                        :src="user.avatar!"
                        :alt="user.name"
                    />
                    <AvatarFallback
                        class="rounded-lg bg-muted font-medium text-cd-ink"
                    >
                        {{ getInitials(user.name) }}
                    </AvatarFallback>
                </Avatar>
                <span
                    class="max-w-[10rem] truncate font-serif tracking-[0.06em] text-cd-ink"
                    :class="compact ? 'text-sm' : 'text-base'"
                >
                    {{ user.name }}
                </span>
                <ChevronsUpDown
                    class="size-4 shrink-0 text-cd-ink-muted opacity-70 transition-opacity group-hover:opacity-100"
                    aria-hidden="true"
                />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="min-w-56 rounded-lg">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
