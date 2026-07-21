export type KiokuMemory = {
    id: string;
    client_capture_id: string | null;
    source_type: string;
    memory_type: string | null;
    memory_type_label: string | null;
    title: string;
    raw_content: string | null;
    transcript_text: string | null;
    summary: string | null;
    structured_data: Record<string, unknown> | null;
    display_fields: Array<{ key: string; label: string; type: string }>;
    tags: string[];
    captured_at: string | null;
    importance: number;
    sensitive: boolean;
    status: string;
    transcription_status: string | null;
    referenced_count: number;
};

export type KiokuTagMode = 'and' | 'or';

/** Library URL / Inertia filters (docs/architecture/kioku-knowledge-retrieval.md §3). */
export type KiokuHomeFilters = {
    q: string | null;
    types: string[];
    tags: string[];
    tag_mode: KiokuTagMode;
};

export type KiokuTagCount = {
    tag: string;
    count: number;
};

/** PUT /kioku/memories/{memory}/tags body. */
export type UpdateMemoryTagsPayload = {
    tags: string[];
};

export type MemoryTypeOption = {
    key: string;
    label: string;
};
