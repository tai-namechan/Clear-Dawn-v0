export type LifeAreaColor =
    'dawn' | 'sunrise' | 'gilt' | 'moss' | 'mist' | 'lavender';

export type LifeArea = {
    id: string;
    name: string;
    color: LifeAreaColor;
    sort_order: number;
    is_active: boolean;
};

export type MatrixCellItem = {
    id: string;
    title: string;
    memo: string | null;
    is_completed: boolean;
    completed_at: string | null;
    sort_order: number;
};

export type MatrixCell = {
    id: string | null;
    items: MatrixCellItem[];
};

export type MatrixRow = {
    id: string;
    key: 'monthly' | 'current' | 'future';
    label: string;
    is_checkable: boolean;
    cells: MatrixCell[];
};
