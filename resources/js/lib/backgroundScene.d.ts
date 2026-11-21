export type BackgroundScene =
    'today' | 'food' | 'condition' | 'practice' | 'settings';

export function backgroundScene(
    component: string | null | undefined,
): BackgroundScene;
