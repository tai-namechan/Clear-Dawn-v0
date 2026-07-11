/**
 * Pure helpers for AI usage banner / chat messaging (unit-testable without Vue).
 */

export type AiUsageBannerLevel = 'warning' | 'limit';

export function aiUsageBannerLevel(atLimit: boolean): AiUsageBannerLevel {
    return atLimit ? 'limit' : 'warning';
}

export function yoyuChatQuotaExceededMessage(): string {
    return '今月のAI利用上限に達しました。原文の保存やタスク操作など、AI以外の機能は引き続き使えます。';
}

export function isYoyuChatQuotaExceeded(errorCode: string | null | undefined): boolean {
    return errorCode === 'quota_exceeded';
}
