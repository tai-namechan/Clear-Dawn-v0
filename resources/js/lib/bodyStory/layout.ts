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
    nutrition: '#F97316',
    nutritionValue: '#EA580C',
    weight: '#2563EB',
    summary: '#7C3AED',
    protein: '#43A047',
    fat: '#F97316',
    carbs: '#F5B700',
} as const;

export const STORY_LAYOUT = {
    date: { x: 72, y: 228 },
    nutrition: {
        calories: { x: 72, y: 400 },
        chart: { x: 96, y: 524, width: 888, height: 352 },
        average: { x: 72, y: 980 },
        pfc: [
            { x: 72, y: 1100, width: 296, height: 280 },
            { x: 392, y: 1100, width: 296, height: 280 },
            { x: 712, y: 1100, width: 296, height: 280 },
        ],
    },
    weight: {
        photo: { x: 72, y: 280, width: 936, height: 720, radius: 28 },
        value: { x: 72, y: 1088 },
        delta: { x: 72, y: 1188 },
        chart: { x: 96, y: 1310, width: 888, height: 280 },
    },
    weekly: {
        calorieChart: { x: 96, y: 300, width: 888, height: 300 },
        average: { x: 72, y: 680 },
        pfc: [
            { x: 72, y: 760, width: 296, height: 220 },
            { x: 392, y: 760, width: 296, height: 220 },
            { x: 712, y: 760, width: 296, height: 220 },
        ],
        weightChart: { x: 96, y: 1120, width: 888, height: 300 },
        weightAverage: { x: 72, y: 1500 },
    },
} as const;
