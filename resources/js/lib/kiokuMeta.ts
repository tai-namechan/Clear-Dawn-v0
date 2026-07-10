import type { Component } from 'vue';
import {
    BookOpen,
    Bot,
    Brain,
    Bug,
    CalendarCheck,
    Compass,
    Heart,
    Lightbulb,
    Link2,
    MessageSquare,
    PenLine,
    Scale,
    Sun,
    Hash,
} from '@lucide/vue';

export type MemoryTypeKey =
    | 'error_log'
    | 'decision'
    | 'learning'
    | 'thought'
    | 'emotion'
    | 'idea'
    | 'reference'
    | 'event'
    | 'conversation';

export type SourceTypeKey =
    | 'manual'
    | 'url'
    | 'yoyu'
    | 'clear_dawn'
    | 'ai_chat'
    | 'slack';

export const MEMORY_TYPES: Record<
    MemoryTypeKey,
    { label: string; color: string; bg: string; icon: Component }
> = {
    error_log: { label: 'エラー', color: '#D9645B', bg: '#FBEBE9', icon: Bug },
    decision: { label: '判断', color: '#DF9A2E', bg: '#FBF1DE', icon: Scale },
    learning: { label: '学び', color: '#4A7DC4', bg: '#E9F0FA', icon: BookOpen },
    thought: { label: '思考', color: '#129488', bg: '#E4F4F2', icon: Brain },
    emotion: { label: '感情', color: '#D66A9C', bg: '#FAEAF2', icon: Heart },
    idea: { label: 'アイデア', color: '#C98A2E', bg: '#FAF0DC', icon: Lightbulb },
    reference: { label: '資料', color: '#6E6A7C', bg: '#EFEEF3', icon: Link2 },
    event: {
        label: '出来事',
        color: '#43A860',
        bg: '#E8F5EC',
        icon: CalendarCheck,
    },
    conversation: {
        label: '相談',
        color: '#6F5FC9',
        bg: '#EDEAF9',
        icon: MessageSquare,
    },
};

export const SOURCE_TYPES: Record<
    SourceTypeKey,
    { label: string; icon: Component; muted?: boolean }
> = {
    manual: { label: '手動', icon: PenLine },
    url: { label: 'URL', icon: Link2 },
    yoyu: { label: 'ヨユウ', icon: Sun },
    clear_dawn: { label: 'Clear Dawn', icon: Compass },
    ai_chat: { label: 'AI相談', icon: Bot },
    slack: { label: 'Slack(将来)', icon: Hash, muted: true },
};

export function memoryTypeMeta(key: string | null | undefined) {
    if (key && key in MEMORY_TYPES) {
        return MEMORY_TYPES[key as MemoryTypeKey];
    }

    return MEMORY_TYPES.thought;
}

export function sourceTypeMeta(key: string | null | undefined) {
    if (key && key in SOURCE_TYPES) {
        return SOURCE_TYPES[key as SourceTypeKey];
    }

    return SOURCE_TYPES.manual;
}

export function formatAgo(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const minutes = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);

    if (minutes < 60) {
        return `${Math.max(minutes, 1)}分前`;
    }

    if (minutes < 1440) {
        return `${Math.floor(minutes / 60)}時間前`;
    }

    return `${Math.floor(minutes / 1440)}日前`;
}
