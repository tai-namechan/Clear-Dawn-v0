<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, RefreshCw, Unplug } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes/yoyu';
import { connect, disconnect, sync } from '@/routes/yoyu/calendar';

interface CalendarConnection {
    status: string;
    account_email: string | null;
    last_synced_at: string | null;
    last_error_code: string | null;
}

interface Props {
    calendarConnection: CalendarConnection | null;
    googleEnabled: boolean;
}

const props = defineProps<Props>();

function requestSync(): void {
    router.post(sync.url(), {}, { preserveScroll: true });
}

function requestDisconnect(): void {
    if (!confirm('Googleカレンダーの接続を解除しますか？キャッシュした予定も削除されます。')) {
        return;
    }

    router.delete(disconnect.url(), { preserveScroll: true });
}

function syncedLabel(iso: string | null): string {
    if (!iso) {
        return 'まだ同期していません';
    }

    return new Date(iso).toLocaleString('ja-JP', {
        month: 'numeric',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: '設定',
    },
});
</script>

<template>
    <div class="mx-auto max-w-[640px] space-y-4">
        <Head title="ヨユウ設定" />

        <Link
            :href="home()"
            class="inline-flex items-center gap-1 text-sm text-os-sub hover:text-os-ink"
        >
            <ArrowLeft :size="14" />
            ヨユウへ戻る
        </Link>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <div
                class="mb-3 flex items-center gap-2 text-sm font-bold text-os-ink"
            >
                <Calendar :size="16" class="text-[#4A7DC4]" />
                Googleカレンダー
            </div>

            <template v-if="!googleEnabled">
                <p class="text-[13px] text-os-sub">
                    この環境ではGoogleカレンダー連携が無効です。
                </p>
            </template>

            <template v-else-if="calendarConnection === null">
                <p class="mb-3 text-[13px] leading-relaxed text-os-sub">
                    接続すると、今日の予定をヨユウのTodayに表示します（読み取り専用）。ブリーフィングへの反映は今後のアップデートで追加予定です。
                </p>
                <Button as="a" :href="connect.url()">
                    Googleカレンダーを接続
                </Button>
            </template>

            <template v-else>
                <div class="space-y-2 text-[13px]">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="rounded-full px-2.5 py-0.5 text-[11.5px] font-bold"
                            :class="{
                                'bg-[#E8F0E5] text-[#5D8A5F]':
                                    calendarConnection.status === 'connected',
                                'bg-[#FDF3E0] text-[#B8862B]':
                                    calendarConnection.status === 'syncing' ||
                                    calendarConnection.status === 'idle' ||
                                    calendarConnection.status === 'revoking',
                                'bg-[#F8E9E4] text-[#C05A48]':
                                    calendarConnection.status === 'error',
                            }"
                        >
                            {{
                                {
                                    connected: '接続中',
                                    syncing: '同期中…',
                                    idle: '同期待ち',
                                    revoking: '解除中…',
                                    error:
                                        calendarConnection.last_error_code ===
                                        'reauthorization_required'
                                            ? '要再接続'
                                            : '同期失敗',
                                }[calendarConnection.status] ??
                                calendarConnection.status
                            }}
                        </span>
                        <span
                            v-if="calendarConnection.account_email"
                            class="text-os-sub"
                        >
                            {{ calendarConnection.account_email }}
                        </span>
                    </div>

                    <p class="text-os-sub">
                        最終同期:
                        {{ syncedLabel(calendarConnection.last_synced_at) }}
                    </p>

                    <p
                        v-if="
                            calendarConnection.last_error_code ===
                            'reauthorization_required'
                        "
                        class="text-[12.5px] text-[#C05A48]"
                    >
                        Googleとの認可が無効です。再接続して権限を許可し直してください。
                    </p>
                    <p
                        v-else-if="
                            calendarConnection.last_error_code === 'sync_failed'
                        "
                        class="text-[12.5px] text-[#C05A48]"
                    >
                        一時的に同期できませんでした。しばらくして「今すぐ同期」を試すか、続く場合は再接続してください。
                    </p>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        v-if="
                            calendarConnection.last_error_code ===
                            'reauthorization_required'
                        "
                        as="a"
                        :href="connect.url()"
                    >
                        再接続する
                    </Button>
                    <Button
                        v-if="calendarConnection.status !== 'revoking'"
                        type="button"
                        variant="outline"
                        class="gap-1.5"
                        @click="requestSync"
                    >
                        <RefreshCw :size="13" />
                        今すぐ同期
                    </Button>
                    <Button
                        v-if="calendarConnection.status !== 'revoking'"
                        type="button"
                        variant="outline"
                        class="gap-1.5 text-[#C05A48]"
                        @click="requestDisconnect"
                    >
                        <Unplug :size="13" />
                        接続を解除
                    </Button>
                </div>
            </template>
        </section>
    </div>
</template>
