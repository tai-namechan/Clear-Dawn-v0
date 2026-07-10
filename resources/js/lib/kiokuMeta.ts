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

/** 書庫トーン: 和紙×墨×藍インク×朱 */
export const MEMORY_TYPES: Record<
    MemoryTypeKey,
    { label: string; color: string; bg: string; icon: Component }
> = {
    error_log: { label: 'エラー', color: '#C05A48', bg: '#F8E9E4', icon: Bug },
    decision: { label: '判断', color: '#B8862B', bg: '#F7EEDA', icon: Scale },
    learning: { label: '学び', color: '#46628F', bg: '#E9EEF5', icon: BookOpen },
    thought: { label: '思考', color: '#4E8578', bg: '#E6F0EC', icon: Brain },
    emotion: { label: '感情', color: '#B56576', bg: '#F6E8EB', icon: Heart },
    idea: { label: 'アイデア', color: '#C68A3A', bg: '#F8EFDD', icon: Lightbulb },
    reference: { label: '資料', color: '#7A7668', bg: '#EDEAE0', icon: Link2 },
    event: {
        label: '出来事',
        color: '#5D8A5F',
        bg: '#E8F0E5',
        icon: CalendarCheck,
    },
    conversation: {
        label: '相談',
        color: '#7C6FA8',
        bg: '#EEEBF4',
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
