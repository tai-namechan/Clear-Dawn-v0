<?php

namespace App\Domain\Yoyu\Data;

/**
 * Allowlisted memory reference for briefing v2 (key assigned by server).
 */
final readonly class BriefingMemoryRef
{
    public function __construct(
        public string $key,
        public string $id,
        public string $title,
        public string $excerpt,
        public string $url,
    ) {}

    /**
     * @return array{key: string, id: string, title: string, excerpt: string, url: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'url' => $this->url,
        ];
    }

    /**
     * Prompt payload: no URL (AI must not invent links).
     *
     * @return array{key: string, title: string, excerpt: string}
     */
    public function toPromptArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
        ];
    }
}
