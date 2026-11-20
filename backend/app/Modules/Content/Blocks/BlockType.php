<?php

namespace App\Modules\Content\Blocks;

/**
 * One kind of lesson block. The registry is the single source of allowed types;
 * the seeder and the admin console both validate through it (ARCHITECTURE D17, D18).
 */
interface BlockType
{
    /** Stored in lesson_sections.type, e.g. "prose". */
    public function key(): string;

    public function label(): string;

    public function description(): string;

    /** How the admin editor edits it: a generated form, or a validated JSON editor. */
    public function editor(): string;

    /** The canonical JSON Schema for the payload, as decoded JSON. */
    public function schema(): object;

    /** A valid example payload, shown in the editor and used as the "new block" template. */
    public function example(): array;

    /** Light clean-up before validation and storage (trimming, defaults). Must be idempotent. */
    public function normalize(array $payload): array;

    /**
     * Problems with the payload, keyed by dot path inside the payload ("" is the payload itself).
     *
     * @return array<string, list<string>>
     */
    public function validate(array $payload): array;
}
