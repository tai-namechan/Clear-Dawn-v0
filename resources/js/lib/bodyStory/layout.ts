export const TEMPLATE_SIZE = {
    width: 941,
    height: 1672,
} as const;

export const STORY_SIZE = {
    width: 1080,
    height: 1920,
} as const;

export const BODY_STORY_COLORS = {
    textPrimary: '#171717',
    textSecondary: '#5F6368',
    surface: '#FFFFFF',
    grid: '#E5E7EB',
    axis: '#9CA3AF',
    nutrition: '#E67A2E',
    nutritionValue: '#C2410C',
    weight: '#2563EB',
    summary: '#6D28D9',
    protein: '#2E7D32',
    fat: '#E65100',
    carbs: '#B45309',
    body: '#FFFFFF',
} as const;

/**
 * Overlay slots in native template pixels (941x1672).
 * Renderer scales these onto the 1080x1920 export canvas.
 */
export const STORY_LAYOUT = {
    date: { x: 470, y: 292 },
    nutrition: {
        calories: { x: 470, y: 338 },
        chart: { x: 160, y: 445, width: 615, height: 400 },
        average: { x: 470, y: 990 },
        pfc: [
            { x: 132, y: 1159, width: 190, height: 176 },
            { x: 372, y: 1159, width: 189, height: 176 },
            { x: 612, y: 1159, width: 190, height: 176 },
        ],
    },
    weight: {
        photo: { x: 170, y: 310, width: 600, height: 520, radius: 24 },
        value: { x: 470, y: 980 },
        delta: { x: 470, y: 1140 },
        chart: { x: 190, y: 1285, width: 560, height: 265 },
    },
    weekly: {
        calorieChart: { x: 245, y: 455, width: 490, height: 340 },
        average: { x: 470, y: 965 },
        pfc: [
            { x: 156, y: 1085, width: 186, height: 174 },
            { x: 375, y: 1085, width: 185, height: 174 },
            { x: 594, y: 1085, width: 185, height: 174 },
        ],
        weightChart: { x: 225, y: 1345, width: 525, height: 230 },
        weightAverage: { x: 430, y: 1318 },
    },
    body: {
        leftArm: { lean: { x: 156, y: 518 }, fat: { x: 156, y: 638 } },
        torso: { lean: { x: 156, y: 922 }, fat: { x: 156, y: 1042 } },
        leftLeg: { lean: { x: 156, y: 1280 }, fat: { x: 156, y: 1392 } },
        rightArm: { lean: { x: 777, y: 518 }, fat: { x: 777, y: 638 } },
        abdominal: { x: 778, y: 990 },
        rightLeg: { lean: { x: 777, y: 1280 }, fat: { x: 777, y: 1392 } },
        weight: { x: 141, y: 1582 },
        skeletal: { x: 344, y: 1582 },
        bodyFat: { x: 559, y: 1582 },
        date: { x: 783, y: 1582 },
    },
} as const;

export function storyX(templateX: number): number {
    return (templateX * STORY_SIZE.width) / TEMPLATE_SIZE.width;
}

export function storyY(templateY: number): number {
    return (templateY * STORY_SIZE.height) / TEMPLATE_SIZE.height;
}

export function storyBox(box: {
    x: number;
    y: number;
    width: number;
    height: number;
}): { x: number; y: number; width: number; height: number } {
    return {
        x: storyX(box.x),
        y: storyY(box.y),
        width: storyX(box.width),
        height: storyY(box.height),
    };
}
