<?php
declare(strict_types=1);

namespace Slate\Presentation\Documents;

final class DocumentNode
{
    /** @param array<string,string> $attributes @param list<DocumentNode> $children @param array<string,mixed> $styles */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $tag,
        public readonly string $text = '',
        public readonly array $attributes = [],
        public readonly array $children = [],
        public readonly array $styles = [],
    ) {
        if ($id === '' || !preg_match('/^[a-zA-Z0-9_-]{4,80}$/', $id)) throw new \InvalidArgumentException('Document node id is invalid.');
        if (!in_array($type, ['document', 'element', 'text'], true)) throw new \InvalidArgumentException('Document node type is invalid.');
        if (!preg_match('/^[a-z][a-z0-9-]{0,31}$/', $tag)) throw new \InvalidArgumentException('Document node tag is invalid.');
        if ($type === 'text' && $children !== []) throw new \InvalidArgumentException('Text nodes cannot have children.');
        foreach ($attributes as $name => $value) {
            if (!preg_match('/^[a-zA-Z_:][a-zA-Z0-9:_.-]{0,63}$/', (string)$name)) throw new \InvalidArgumentException('Document attribute name is invalid.');
            if (!is_string($value)) throw new \InvalidArgumentException('Document attribute values must be strings.');
        }
        foreach ($children as $child) if (!$child instanceof self) throw new \InvalidArgumentException('Document children must be DocumentNode instances.');
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $children = [];
        foreach ((array)($data['children'] ?? []) as $child) $children[] = self::fromArray((array)$child);
        $attrs = [];
        foreach ((array)($data['attributes'] ?? []) as $key => $value) $attrs[(string)$key] = (string)$value;
        return new self(
            id: (string)($data['id'] ?? ''), type: (string)($data['type'] ?? 'element'), tag: strtolower((string)($data['tag'] ?? 'div')),
            text: (string)($data['text'] ?? ''), attributes: $attrs, children: $children, styles: (array)($data['styles'] ?? []),
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['id'=>$this->id,'type'=>$this->type,'tag'=>$this->tag,'text'=>$this->text,'attributes'=>$this->attributes,'children'=>array_map(fn(self $n) => $n->toArray(), $this->children),'styles'=>$this->styles];
    }
}
