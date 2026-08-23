<?php
declare(strict_types=1);

namespace Slate\Services\Import;

use Slate\Presentation\Documents\DocumentNode;

final class HtmlTemplateImporter
{
    /** @return array{document:DocumentNode, css:string, warnings:list<string>} */
    public function import(string $html): array
    {
        if (trim($html) === '') throw new \InvalidArgumentException('HTML source cannot be empty.');
        if (!class_exists('DOMDocument')) throw new \RuntimeException('The DOM extension is required for HTML import.');

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok || !$dom->documentElement) throw new \InvalidArgumentException('HTML could not be parsed.');

        $warnings = [];
        $css = '';
        foreach (iterator_to_array($dom->getElementsByTagName('script')) as $script) {
            $warnings[] = 'Script content was removed from the imported template.';
            $script->parentNode?->removeChild($script);
        }
        foreach (iterator_to_array($dom->getElementsByTagName('style')) as $style) {
            $css .= trim((string)$style->textContent) . "\n";
            $style->parentNode?->removeChild($style);
        }
        foreach (iterator_to_array($dom->getElementsByTagName('*')) as $element) {
            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                $name = strtolower((string)$attribute->name);
                $value = trim((string)$attribute->value);
                if (str_starts_with($name, 'on')) {
                    $element->removeAttribute($attribute->name);
                    $warnings[] = 'Inline event-handler attributes were removed.';
                } elseif (in_array($name, ['href','src','action'], true) && preg_match('/^(?:javascript|data):/i', $value)) {
                    $element->removeAttribute($attribute->name);
                    $warnings[] = 'Unsafe javascript/data URL was removed.';
                }
            }
        }
        $root = $dom->getElementsByTagName('body')->item(0) ?: $dom->documentElement;
        $children = [];
        foreach ($root->childNodes as $child) {
            $node = $this->node($child, $warnings);
            if ($node) $children[] = $node;
        }
        if ($children === []) $warnings[] = 'The imported template has no editable body content.';
        $document = new DocumentNode($this->id(), 'document', 'document', '', [], $children);
        return ['document'=>$document, 'css'=>trim($css), 'warnings'=>array_values(array_unique($warnings))];
    }

    private function node(\DOMNode $source, array &$warnings): ?DocumentNode
    {
        if ($source->nodeType === XML_TEXT_NODE) {
            $text = preg_replace('/\s+/', ' ', (string)$source->nodeValue) ?? '';
            return trim($text) === '' ? null : new DocumentNode($this->id(), 'text', 'span', $text);
        }
        if ($source->nodeType !== XML_ELEMENT_NODE) return null;
        $tag = strtolower((string)$source->nodeName);
        if (in_array($tag, ['script','style','iframe','object','embed'], true)) return null;
        $attributes = [];
        foreach (iterator_to_array($source->attributes ?? []) as $attribute) {
            $name = strtolower((string)$attribute->name);
            $value = trim((string)$attribute->value);
            if (str_starts_with($name, 'on') || preg_match('/^(?:javascript|data):/i', $value)) continue;
            $attributes[$name] = $value;
        }
        $children = [];
        foreach ($source->childNodes as $child) {
            $node = $this->node($child, $warnings);
            if ($node) $children[] = $node;
        }
        $type = $tag === 'html' ? 'document' : 'element';
        return new DocumentNode($this->id(), $type, preg_replace('/[^a-z0-9-]/', '', $tag) ?: 'div', '', $attributes, $children);
    }

    private function id(): string { return 'node_' . bin2hex(random_bytes(8)); }
}
