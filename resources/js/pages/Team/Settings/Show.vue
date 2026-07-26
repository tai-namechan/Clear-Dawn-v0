<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EmptyState from '@/components/team/EmptyState.vue';
import ReadonlyBanner from '@/components/team/ReadonlyBanner.vue';

interface StaffRow {
    id: string;
    name: string;
    role: string;
    role_label: string;
    status: string;
    joined_at: string | null;
}

interface InvitationRow {
    id: string;
    email_masked: string;
    role: string;
    role_label: string;
    invitee_type: string;
    expires_at: string | null;
}

interface Props {
    team: {
        name: string;
        slug: string;
        organization_type: string;
        status: string;
        timezone: string;
    };
    staff: StaffRow[];
    invitations: InvitationRow[];
    prototype_note: string;
}

defineProps<Props>();
</script>

<template>
    <div>
        <Head title="チーム設定" />
        <div>
            <p class="text-sm font-medium text-violet-600">チーム設定</p>
            <h1 class="mt-1 text-2xl font-bold">{{ team.name }}</h1>
            <p class="mt-2 text-sm text-slate-500">
                基本情報とスタッフ体制の確認のみ
            </p>
        </div>

        <div class="mt-4">
            <ReadonlyBanner :message="prototype_note" />
        </div>

        <section class="mt-6 rounded-xl border bg-white p-5">
            <h2 class="font-semibold">チーム基本情報</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">名称</dt>
                    <dd class="mt-1 font-medium">{{ team.name }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">スラッグ</dt>
                    <dd class="mt-1 font-medium">{{ team.slug }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">組織区分</dt>
                    <dd class="mt-1 font-medium">
                        {{ team.organization_type }}
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">状態</dt>
                    <dd class="mt-1 font-medium">{{ team.status }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">タイムゾーン</dt>
                    <dd class="mt-1 font-medium">{{ team.timezone }}</dd>
                </div>
            </dl>
        </section>

        <section class="mt-6 rounded-xl border bg-white p-5">
            <h2 class="font-semibold">スタッフメンバー</h2>
            <EmptyState
                v-if="staff.length === 0"
                class="mt-4"
                title="スタッフがいません"
            />
            <div v-else class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">氏名</th>
                            <th class="px-4 py-3 font-medium">役割</th>
                            <th class="px-4 py-3 font-medium">所属状態</th>
                            <th class="px-4 py-3 font-medium">参加日</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="member in staff"
                            :key="member.id"
                            class="border-t"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ member.name }}
                            </td>
                            <td class="px-4 py-3">{{ member.role_label }}</td>
                            <td class="px-4 py-3">{{ member.status }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ member.joined_at ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mt-6 rounded-xl border bg-white p-5">
            <h2 class="font-semibold">招待中メンバー</h2>
            <EmptyState
                v-if="invitations.length === 0"
                class="mt-4"
                title="招待中のメンバーはいません"
            />
            <div v-else class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">招待先</th>
                            <th class="px-4 py-3 font-medium">役割</th>
                            <th class="px-4 py-3 font-medium">有効期限</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="invitation in invitations"
                            :key="invitation.id"
                            class="border-t"
                        >
                            <td class="px-4 py-3">
                                {{ invitation.email_masked }}
                            </td>
                            <td class="px-4 py-3">
                                {{ invitation.role_label }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ invitation.expires_at ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
