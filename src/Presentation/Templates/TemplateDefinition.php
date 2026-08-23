<?php
/**
 * Immutable metadata contract for reusable tenant templates.
 *
 * This value object deliberately does not render HTML. It describes the
 * contract consumed by the TemplateResolver and later by the template library.
 */
declare(strict_types=1);

namespace Slate\Presentation\Templates;

final class TemplateDefinition
{
    /** @param list<string> $regions @param list<string> $contentTypes @param list<string> $requires */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly array $regions,
        public readonly array $contentTypes = [],
        public readonly array $requires = [],
        public readonly string $schemaVersion = '1.0',
        public readonly string $status = 'draft',
    ) {
        self::assertSlug($slug);
        if ($id === '' || $name === '') throw new \InvalidArgumentException('Template id and name are required.');
        if ($regions === []) throw new \InvalidArgumentException('A template must expose at least one region.');
        if (!in_array($status, ['draft', 'active', 'archived'], true)) {
            throw new \InvalidArgumentException('Invalid template status.');
        }
        foreach ($regions as $region) {
            if (!is_string($region) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $region)) {
                throw new \InvalidArgumentException('Template regions must be safe identifiers.');
            }
        }
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: trim((string)($data['id'] ?? '')),
            name: trim((string)($data['name'] ?? '')),
            slug: trim((string)($data['slug'] ?? '')),
            regions: array_values(array_map('strval', (array)($data['regions'] ?? []))),
            contentTypes: array_values(array_map('strval', (array)($data['contentTypes'] ?? []))),
            requires: array_values(array_map('strval', (array)($data['requires'] ?? []))),
            schemaVersion: trim((string)($data['schemaVersion'] ?? '1.0')) ?: '1.0',
            status: trim((string)($data['status'] ?? 'draft')) ?: 'draft',
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'regions' => $this->regions,
            'contentTypes' => $this->contentTypes,
            'requires' => $this->requires,
            'schemaVersion' => $this->schemaVersion,
            'status' => $this->status,
        ];
    }

    private static function assertSlug(string $slug): void
    {
        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new \InvalidArgumentException('Template slug must be lowercase kebab-case.');
        }
    }
}
