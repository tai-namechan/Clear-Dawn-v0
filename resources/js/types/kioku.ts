export type KiokuMemory = {
    id: string;
    source_type: string;
    memory_type: string | null;
    memory_type_label: string | null;
    title: string;
    raw_content: string;
    summary: string | null;
    structured_data: Record<string, unknown> | null;
    display_fields: Array<{ key: string; label: string; type: string }>;
    tags: string[];
    captured_at: string | null;
    importance: number;
    sensitive: boolean;
    status: string;
    referenced_count: number;
};

export type MemoryTypeOption = {
    key: string;
    label: string;
};
