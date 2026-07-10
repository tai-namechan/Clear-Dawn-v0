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
    error_log: { label: 'エラー', color: '#B86B66', bg: '#F6EEEC', icon: Bug },
    decision: { label: '判断', color: '#B8894A', bg: '#F7F1E6', icon: Scale },
    learning: { label: '学び', color: '#5B7AA8', bg: '#EEF2F8', icon: BookOpen },
    thought: { label: '思考', color: '#3D8A82', bg: '#EAF4F2', icon: Brain },
    emotion: { label: '感情', color: '#B87496', bg: '#F6EEF3', icon: Heart },
    idea: { label: 'アイデア', color: '#B08A45', bg: '#F7F2E7', icon: Lightbulb },
    reference: { label: '資料', color: '#7A7688', bg: '#F1F0F4', icon: Link2 },
    event: {
        label: '出来事',
        color: '#5A9470',
        bg: '#EAF4EE',
        icon: CalendarCheck,
    },
    conversation: {
        label: '相談',
        color: '#6F5FC9',
        bg: '#F0EEF8',
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
