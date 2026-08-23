<?php
declare(strict_types=1);

use Slate\Services\Import\HtmlTemplateImporter;
use Slate\Presentation\Documents\DocumentNode;

unit('HTML importer normalizes content, extracts CSS, and removes unsafe behavior', function () {
    $result = (new HtmlTemplateImporter())->import('<style>.hero{color:red}</style><main class="hero" onclick="bad()"><h1>Hello</h1><a href="javascript:alert(1)">Read</a><script>alert(1)</script></main>');
    assert_true($result['document'] instanceof DocumentNode);
    assert_eq('.hero{color:red}', $result['css']);
    $tree = json_encode($result['document']->toArray());
    assert_true(!str_contains($tree, 'onclick'));
    assert_true(!str_contains($tree, 'javascript:'));
    assert_true(!str_contains($tree, 'alert(1)'));
    assert_true(count($result['warnings']) >= 2);
});

unit('HTML importer rejects empty source', function () {
    assert_throws(InvalidArgumentException::class, fn() => (new HtmlTemplateImporter())->import('  '));
});
