export const SKY_HERO_SELECTOR: string;
export const SKY_OVERLAP_PX: number;
export const SKY_FALLBACK_PX: number;

export function skyBackdropHeight(
    measurement: {
        heroBottom: number;
        containerTop: number;
        scrollTop: number;
    } | null,
): number;
