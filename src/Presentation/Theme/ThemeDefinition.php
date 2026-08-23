<?php
/** Immutable metadata contract for reusable tenant themes. */
declare(strict_types=1);

namespace Slate\Presentation\Theme;

final class ThemeDefinition
{
    /** @param array<string,string> $tokens @param array{sans?:string,mono?:string} $fontPairing @param array<string,array<string,string>> $componentDefaults */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly array $tokens = [],
        public readonly array $fontPairing = [],
        public readonly string $chrome = 'full',
        public readonly array $componentDefaults = [],
        public readonly string $schemaVersion = '1.0',
        public readonly string $status = 'draft',
    ) {
        if ($id === '' || $name === '') throw new \InvalidArgumentException('Theme id and name are required.');
        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new \InvalidArgumentException('Theme slug must be lowercase kebab-case.');
        }
        if (!in_array($chrome, ['full', 'minimal', 'widget', 'email'], true)) {
            throw new \InvalidArgumentException('Invalid theme chrome preset.');
        }
        if (!in_array($status, ['draft', 'active', 'archived'], true)) {
            throw new \InvalidArgumentException('Invalid theme status.');
        }
        foreach ($tokens as $name => $value) {
            if (!is_string($name) || !preg_match('/^[a-z][a-z0-9-]{0,80}$/', $name)) {
                throw new \InvalidArgumentException('Theme token names must be safe kebab-case identifiers.');
            }
            if (!is_string($value) || trim($value) === '' || str_contains($value, '</')) {
                throw new \InvalidArgumentException('Theme token values must be non-empty safe strings.');
            }
        }
        foreach (['sans', 'mono'] as $font) {
            if (isset($fontPairing[$font]) && (!is_string($fontPairing[$font]) || str_contains($fontPairing[$font], '<'))) {
                throw new \InvalidArgumentException('Theme font stacks must be text values.');
            }
        }
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $tokens = [];
        foreach ((array)($data['tokens'] ?? []) as $key => $value) $tokens[(string)$key] = (string)$value;
        $fonts = [];
        foreach ((array)($data['fontPairing'] ?? []) as $key => $value) $fonts[(string)$key] = (string)$value;
        return new self(
            id: trim((string)($data['id'] ?? '')),
            name: trim((string)($data['name'] ?? '')),
            slug: trim((string)($data['slug'] ?? '')),
            tokens: $tokens,
            fontPairing: $fonts,
            chrome: trim((string)($data['chrome'] ?? 'full')) ?: 'full',
            componentDefaults: (array)($data['componentDefaults'] ?? []),
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
            'tokens' => $this->tokens,
            'fontPairing' => $this->fontPairing,
            'chrome' => $this->chrome,
            'componentDefaults' => $this->componentDefaults,
            'schemaVersion' => $this->schemaVersion,
            'status' => $this->status,
        ];
    }
}
