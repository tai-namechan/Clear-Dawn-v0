export function pfcEnergyShare(totals: {
    protein_g: number | string;
    fat_g: number | string;
    carb_g: number | string;
}): { p: number; f: number; c: number };
