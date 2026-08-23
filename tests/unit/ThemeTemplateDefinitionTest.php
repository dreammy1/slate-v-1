<?php
declare(strict_types=1);

use Slate\Presentation\Theme\ThemeDefinition;
use Slate\Presentation\Templates\TemplateDefinition;

unit('ThemeDefinition round-trips reusable theme metadata', function () {
    $theme = ThemeDefinition::fromArray([
        'id' => 'theme-1', 'name' => 'Horizon', 'slug' => 'horizon',
        'tokens' => ['slate-color-accent' => '#0E7490'],
        'fontPairing' => ['sans' => 'system-ui, sans-serif'],
        'chrome' => 'minimal', 'status' => 'active',
    ]);
    assert_eq('Horizon', $theme->name);
    assert_eq('#0E7490', $theme->toArray()['tokens']['slate-color-accent']);
    assert_eq('minimal', $theme->chrome);
});

unit('ThemeDefinition rejects unsafe token values and invalid slugs', function () {
    assert_throws(InvalidArgumentException::class, fn() => new ThemeDefinition('1', 'Bad', 'Bad Slug'));
    assert_throws(InvalidArgumentException::class, fn() => new ThemeDefinition('1', 'Bad', 'bad', ['x' => '</style>']));
    assert_throws(InvalidArgumentException::class, fn() => new ThemeDefinition('1', 'Bad', 'bad', [], [], 'unknown'));
});

unit('TemplateDefinition validates regions and round-trips metadata', function () {
    $template = TemplateDefinition::fromArray([
        'id' => 'tpl-1', 'name' => 'Landing', 'slug' => 'landing',
        'regions' => ['header', 'content', 'footer'],
        'contentTypes' => ['landing'], 'requires' => ['content-builder'], 'status' => 'active',
    ]);
    assert_eq(['header', 'content', 'footer'], $template->regions);
    assert_eq('content-builder', $template->toArray()['requires'][0]);
});

unit('TemplateDefinition rejects missing regions, unsafe region names, and invalid status', function () {
    assert_throws(InvalidArgumentException::class, fn() => new TemplateDefinition('1', 'Empty', 'empty', []));
    assert_throws(InvalidArgumentException::class, fn() => new TemplateDefinition('1', 'Bad', 'bad', ['<script>']));
    assert_throws(InvalidArgumentException::class, fn() => new TemplateDefinition('1', 'Bad', 'bad', ['content'], [], [], '1.0', 'published'));
});
