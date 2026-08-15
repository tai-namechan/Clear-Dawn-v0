import {
    BODY_STORY_COLORS,
    STORY_LAYOUT,
    STORY_SIZE,
    storyBox,
    storyX,
    storyY,
} from '@/lib/bodyStory/layout';
import {
    storyFilename,
    toNutritionViewModel,
    toWeeklyViewModel,
    toWeightViewModel,
} from '@/lib/bodyStory/viewModel';
import type {
    BodyStoryExportPayload,
    StoryChartPoint,
} from '@/types/bodyStory';

type PfcCard = {
    grams: number | null;
    percentage: number | null;
};

function loadImage(src: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.decoding = 'async';
        image.onload = () => resolve(image);
        image.onerror = () =>
            reject(new Error('template image failed to load'));
        image.src = src;
    });
}

function fillText(
    ctx: CanvasRenderingContext2D,
    text: string,
    x: number,
    y: number,
    font: string,
    color: string,
    align: CanvasTextAlign = 'left',
): void {
    ctx.font = font;
    ctx.fillStyle = color;
    ctx.textAlign = align;
    ctx.textBaseline = 'alphabetic';
    ctx.fillText(text, x, y);
}

function drawCover(
    ctx: CanvasRenderingContext2D,
    image: HTMLImageElement,
    x: number,
    y: number,
    width: number,
    height: number,
    radius: number,
): void {
    const scale = Math.max(width / image.width, height / image.height);
    const drawWidth = image.width * scale;
    const drawHeight = image.height * scale;
    const dx = x + (width - drawWidth) / 2;
    const dy = y + (height - drawHeight) / 2;

    ctx.save();
    ctx.beginPath();
    ctx.roundRect(x, y, width, height, radius);
    ctx.clip();
    ctx.drawImage(image, dx, dy, drawWidth, drawHeight);
    ctx.restore();
}

function drawLineChart(
    ctx: CanvasRenderingContext2D,
    box: { x: number; y: number; width: number; height: number },
    points: StoryChartPoint[],
    color: string,
): void {
    if (points.length === 0) {
        return;
    }

    const values = points.map((point) => point.value);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const pad = min === max ? 1 : (max - min) * 0.12;
    const low = min - pad;
    const high = max + pad;
    const range = high - low;

    const coords = points.map((point, index) => {
        const x =
            points.length === 1
                ? box.x + box.width / 2
                : box.x + (index / (points.length - 1)) * box.width;
        const y =
            box.y + box.height - ((point.value - low) / range) * box.height;

        return { x, y, label: point.label };
    });

    ctx.save();
    ctx.strokeStyle = color;
    ctx.lineWidth = 5;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    ctx.beginPath();
    coords.forEach((coord, index) => {
        if (index === 0) {
            ctx.moveTo(coord.x, coord.y);
        } else {
            ctx.lineTo(coord.x, coord.y);
        }
    });
    ctx.stroke();

    coords.forEach((coord, index) => {
        ctx.fillStyle = color;
        const radius = index === coords.length - 1 ? 8 : 6;
        ctx.beginPath();
        ctx.arc(coord.x, coord.y, radius, 0, Math.PI * 2);
        ctx.fill();
    });

    ctx.fillStyle = BODY_STORY_COLORS.axis;
    ctx.font = '500 20px "Instrument Sans", "Noto Sans JP", sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    coords.forEach((coord) => {
        ctx.fillText(coord.label, coord.x, box.y + box.height + 10);
    });
    ctx.restore();
}

function drawPfcCard(
    ctx: CanvasRenderingContext2D,
    box: { x: number; y: number; width: number; height: number },
    label: string,
    card: PfcCard,
    color: string,
): void {
    if (card.grams === null) {
        return;
    }

    fillText(
        ctx,
        label,
        box.x + box.width / 2,
        box.y + box.height * 0.28,
        '700 26px "Instrument Sans", "Noto Sans JP", sans-serif',
        color,
        'center',
    );
    fillText(
        ctx,
        `${Number.isInteger(card.grams) ? card.grams : Math.round(card.grams * 10) / 10}g`,
        box.x + box.width / 2,
        box.y + box.height * 0.58,
        '700 44px "Instrument Sans", "Noto Sans JP", sans-serif',
        BODY_STORY_COLORS.textPrimary,
        'center',
    );

    if (card.percentage !== null) {
        fillText(
            ctx,
            `${card.percentage}%`,
            box.x + box.width / 2,
            box.y + box.height * 0.82,
            '600 26px "Instrument Sans", "Noto Sans JP", sans-serif',
            color,
            'center',
        );
    }
}

async function renderPayload(
    payload: BodyStoryExportPayload,
    photo: HTMLImageElement | null,
): Promise<HTMLCanvasElement> {
    const canvas = document.createElement('canvas');
    canvas.width = STORY_SIZE.width;
    canvas.height = STORY_SIZE.height;
    const ctx = canvas.getContext('2d');

    if (ctx === null) {
        throw new Error('canvas is unavailable');
    }

    const template = await loadImage(payload.template_url);
    ctx.drawImage(template, 0, 0, STORY_SIZE.width, STORY_SIZE.height);

    if (payload.kind === 'nutrition') {
        const view = toNutritionViewModel(payload);

        if (view.displayDate !== null) {
            fillText(
                ctx,
                view.displayDate,
                storyX(STORY_LAYOUT.date.x),
                storyY(STORY_LAYOUT.date.y),
                '600 28px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.textSecondary,
                'center',
            );
        }

        if (view.calories.display !== null) {
            fillText(
                ctx,
                view.calories.display,
                storyX(STORY_LAYOUT.nutrition.calories.x),
                storyY(STORY_LAYOUT.nutrition.calories.y),
                '700 40px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.nutritionValue,
                'center',
            );
        }

        drawLineChart(
            ctx,
            storyBox(STORY_LAYOUT.nutrition.chart),
            view.chart.points,
            BODY_STORY_COLORS.nutrition,
        );

        if (view.averageCalories.display !== null) {
            fillText(
                ctx,
                view.averageCalories.display,
                storyX(STORY_LAYOUT.nutrition.average.x),
                storyY(STORY_LAYOUT.nutrition.average.y),
                '700 40px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.nutritionValue,
                'center',
            );
        }

        drawPfcCard(
            ctx,
            storyBox(STORY_LAYOUT.nutrition.pfc[0]),
            'P',
            view.protein,
            BODY_STORY_COLORS.protein,
        );
        drawPfcCard(
            ctx,
            storyBox(STORY_LAYOUT.nutrition.pfc[1]),
            'F',
            view.fat,
            BODY_STORY_COLORS.fat,
        );
        drawPfcCard(
            ctx,
            storyBox(STORY_LAYOUT.nutrition.pfc[2]),
            'C',
            view.carbs,
            BODY_STORY_COLORS.carbs,
        );
    }

    if (payload.kind === 'weight') {
        const view = toWeightViewModel(payload);
        const photoBox = STORY_LAYOUT.weight.photo;

        if (view.displayDate !== null) {
            fillText(
                ctx,
                view.displayDate,
                storyX(STORY_LAYOUT.date.x),
                storyY(STORY_LAYOUT.date.y),
                '600 26px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.textSecondary,
                'center',
            );
        }

        if (photo !== null) {
            drawCover(
                ctx,
                photo,
                storyX(photoBox.x),
                storyY(photoBox.y),
                storyX(photoBox.width),
                storyY(photoBox.height),
                storyX(photoBox.radius),
            );
        }

        if (view.weight.display !== null) {
            fillText(
                ctx,
                view.weight.display,
                storyX(STORY_LAYOUT.weight.value.x),
                storyY(STORY_LAYOUT.weight.value.y),
                '700 64px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.weight,
                'center',
            );
        }

        if (view.delta.display !== null) {
            fillText(
                ctx,
                view.delta.display,
                storyX(STORY_LAYOUT.weight.delta.x),
                storyY(STORY_LAYOUT.weight.delta.y),
                '600 40px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.weight,
                'center',
            );
        }

        drawLineChart(
            ctx,
            storyBox(STORY_LAYOUT.weight.chart),
            view.chart.points,
            BODY_STORY_COLORS.weight,
        );
    }

    if (payload.kind === 'weekly') {
        const view = toWeeklyViewModel(payload);

        if (view.displayDate !== null) {
            fillText(
                ctx,
                view.displayDate,
                storyX(STORY_LAYOUT.date.x),
                storyY(STORY_LAYOUT.date.y),
                '600 24px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.textSecondary,
                'center',
            );
        }

        drawLineChart(
            ctx,
            storyBox(STORY_LAYOUT.weekly.calorieChart),
            view.calorieChart.points,
            BODY_STORY_COLORS.summary,
        );

        if (view.averageCalories.display !== null) {
            fillText(
                ctx,
                view.averageCalories.display,
                storyX(STORY_LAYOUT.weekly.average.x),
                storyY(STORY_LAYOUT.weekly.average.y),
                '700 36px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.summary,
                'center',
            );
        }

        drawPfcCard(
            ctx,
            storyBox(STORY_LAYOUT.weekly.pfc[0]),
            'P',
            view.protein,
            BODY_STORY_COLORS.protein,
        );
        drawPfcCard(
            ctx,
            storyBox(STORY_LAYOUT.weekly.pfc[1]),
            'F',
            view.fat,
            BODY_STORY_COLORS.fat,
        );
        drawPfcCard(
            ctx,
            storyBox(STORY_LAYOUT.weekly.pfc[2]),
            'C',
            view.carbs,
            BODY_STORY_COLORS.carbs,
        );
        drawLineChart(
            ctx,
            storyBox(STORY_LAYOUT.weekly.weightChart),
            view.weightChart.points,
            BODY_STORY_COLORS.summary,
        );

        if (view.averageWeight.display !== null) {
            fillText(
                ctx,
                view.averageWeight.display,
                storyX(STORY_LAYOUT.weekly.weightAverage.x),
                storyY(STORY_LAYOUT.weekly.weightAverage.y),
                '700 28px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.summary,
            );
        }
    }

    return canvas;
}

export async function renderBodyStoryPng(
    payload: BodyStoryExportPayload,
    photo: HTMLImageElement | null = null,
): Promise<{ canvas: HTMLCanvasElement; filename: string }> {
    if (document.fonts?.ready) {
        await document.fonts.ready;
    }

    const canvas = await renderPayload(payload, photo);

    return {
        canvas,
        filename: storyFilename(payload),
    };
}

export function downloadCanvasPng(
    canvas: HTMLCanvasElement,
    filename: string,
): void {
    canvas.toBlob((blob) => {
        if (blob === null) {
            return;
        }

        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = filename;
        anchor.click();
        URL.revokeObjectURL(url);
    }, 'image/png');
}
