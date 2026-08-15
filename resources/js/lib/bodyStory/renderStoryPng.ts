import {
    BODY_STORY_COLORS,
    STORY_LAYOUT,
    STORY_SIZE,
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
    gridColor: string,
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

    ctx.save();
    ctx.strokeStyle = gridColor;
    ctx.lineWidth = 1;

    for (let i = 0; i < 4; i += 1) {
        const y = box.y + (box.height / 3) * i;
        ctx.beginPath();
        ctx.moveTo(box.x, y);
        ctx.lineTo(box.x + box.width, y);
        ctx.stroke();
    }

    const coords = points.map((point, index) => {
        const x =
            points.length === 1
                ? box.x + box.width / 2
                : box.x + (index / (points.length - 1)) * box.width;
        const y =
            box.y + box.height - ((point.value - low) / range) * box.height;

        return { x, y, label: point.label };
    });

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
    ctx.font = '500 22px "Instrument Sans", "Noto Sans JP", sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    coords.forEach((coord) => {
        ctx.fillText(coord.label, coord.x, box.y + box.height + 12);
    });
    ctx.restore();
}

function drawPfcCard(
    ctx: CanvasRenderingContext2D,
    box: { x: number; y: number; width: number; height: number },
    label: string,
    card: PfcCard,
): void {
    if (card.grams === null) {
        return;
    }

    fillText(
        ctx,
        label,
        box.x + box.width / 2,
        box.y + box.height * 0.22,
        '700 28px "Instrument Sans", "Noto Sans JP", sans-serif',
        '#FFFFFF',
        'center',
    );
    fillText(
        ctx,
        `${Number.isInteger(card.grams) ? card.grams : Math.round(card.grams * 10) / 10}g`,
        box.x + box.width / 2,
        box.y + box.height * 0.55,
        '700 48px "Instrument Sans", "Noto Sans JP", sans-serif',
        '#FFFFFF',
        'center',
    );

    if (card.percentage !== null) {
        fillText(
            ctx,
            `${card.percentage}%`,
            box.x + box.width / 2,
            box.y + box.height * 0.78,
            '600 28px "Instrument Sans", "Noto Sans JP", sans-serif',
            '#FFFFFF',
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
                STORY_LAYOUT.date.x,
                STORY_LAYOUT.date.y,
                '600 32px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.textSecondary,
            );
        }

        if (view.calories.display !== null) {
            fillText(
                ctx,
                view.calories.display,
                STORY_LAYOUT.nutrition.calories.x,
                STORY_LAYOUT.nutrition.calories.y,
                '700 84px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.nutritionValue,
            );
        }

        drawLineChart(
            ctx,
            STORY_LAYOUT.nutrition.chart,
            view.chart.points,
            BODY_STORY_COLORS.nutrition,
            '#F3E8D9',
        );

        if (view.averageCalories.display !== null) {
            fillText(
                ctx,
                '平均',
                STORY_LAYOUT.nutrition.average.x,
                STORY_LAYOUT.nutrition.average.y - 48,
                '600 28px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.textSecondary,
            );
            fillText(
                ctx,
                view.averageCalories.display,
                STORY_LAYOUT.nutrition.average.x,
                STORY_LAYOUT.nutrition.average.y,
                '700 56px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.nutritionValue,
            );
        }

        drawPfcCard(ctx, STORY_LAYOUT.nutrition.pfc[0], 'P', view.protein);
        drawPfcCard(ctx, STORY_LAYOUT.nutrition.pfc[1], 'F', view.fat);
        drawPfcCard(ctx, STORY_LAYOUT.nutrition.pfc[2], 'C', view.carbs);
    }

    if (payload.kind === 'weight') {
        const view = toWeightViewModel(payload);

        if (view.displayDate !== null) {
            fillText(
                ctx,
                view.displayDate,
                STORY_LAYOUT.date.x,
                STORY_LAYOUT.date.y,
                '600 32px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.textSecondary,
            );
        }

        if (photo !== null) {
            drawCover(
                ctx,
                photo,
                STORY_LAYOUT.weight.photo.x,
                STORY_LAYOUT.weight.photo.y,
                STORY_LAYOUT.weight.photo.width,
                STORY_LAYOUT.weight.photo.height,
                STORY_LAYOUT.weight.photo.radius,
            );
        }

        if (view.weight.display !== null) {
            fillText(
                ctx,
                view.weight.display,
                STORY_LAYOUT.weight.value.x,
                STORY_LAYOUT.weight.value.y,
                '700 84px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.weight,
            );
        }

        if (view.delta.display !== null) {
            fillText(
                ctx,
                `前日比 ${view.delta.display}`,
                STORY_LAYOUT.weight.delta.x,
                STORY_LAYOUT.weight.delta.y,
                '600 36px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.weight,
            );
        }

        drawLineChart(
            ctx,
            STORY_LAYOUT.weight.chart,
            view.chart.points,
            BODY_STORY_COLORS.weight,
            '#D7E6FA',
        );
    }

    if (payload.kind === 'weekly') {
        const view = toWeeklyViewModel(payload);

        if (view.displayDate !== null) {
            fillText(
                ctx,
                view.displayDate,
                STORY_LAYOUT.date.x,
                STORY_LAYOUT.date.y,
                '600 28px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.textSecondary,
            );
        }

        drawLineChart(
            ctx,
            STORY_LAYOUT.weekly.calorieChart,
            view.calorieChart.points,
            BODY_STORY_COLORS.summary,
            '#EDE9FE',
        );

        if (view.averageCalories.display !== null) {
            fillText(
                ctx,
                '平均',
                STORY_LAYOUT.weekly.average.x,
                STORY_LAYOUT.weekly.average.y - 40,
                '600 24px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.textSecondary,
            );
            fillText(
                ctx,
                view.averageCalories.display,
                STORY_LAYOUT.weekly.average.x,
                STORY_LAYOUT.weekly.average.y,
                '700 52px "Instrument Sans", "Noto Sans JP", sans-serif',
                BODY_STORY_COLORS.summary,
            );
        }

        drawPfcCard(ctx, STORY_LAYOUT.weekly.pfc[0], 'P', view.protein);
        drawPfcCard(ctx, STORY_LAYOUT.weekly.pfc[1], 'F', view.fat);
        drawPfcCard(ctx, STORY_LAYOUT.weekly.pfc[2], 'C', view.carbs);
        drawLineChart(
            ctx,
            STORY_LAYOUT.weekly.weightChart,
            view.weightChart.points,
            BODY_STORY_COLORS.summary,
            '#EDE9FE',
        );

        if (view.averageWeight.display !== null) {
            fillText(
                ctx,
                `体重推移（平均：${view.averageWeight.display}）`,
                STORY_LAYOUT.weekly.weightAverage.x,
                STORY_LAYOUT.weekly.weightAverage.y,
                '600 32px "Instrument Sans", "Noto Sans JP", sans-serif',
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
