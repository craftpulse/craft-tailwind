<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

use craftpulse\tailwind\models\TypographyConfig;

// =========================================================================
// = Defaults
// =========================================================================

it('exposes the @tailwindcss/typography default sizes', function(): void {
    expect(TypographyConfig::DEFAULT_SIZES)->toBe(['sm', 'base', 'lg', 'xl', '2xl']);
});

it('exposes the @tailwindcss/typography default colors', function(): void {
    expect(TypographyConfig::DEFAULT_COLORS)->toBe(['gray', 'slate', 'zinc', 'neutral', 'stone', 'invert']);
});

it('returns the defaults when no extras are configured', function(): void {
    $config = new TypographyConfig();

    expect($config->getSizes())->toBe(TypographyConfig::DEFAULT_SIZES);
    expect($config->getColors())->toBe(TypographyConfig::DEFAULT_COLORS);
});

// =========================================================================
// = Extras append to defaults
// =========================================================================

it('appends extra sizes to the defaults', function(): void {
    $config = new TypographyConfig(extraSizes: ['huge', 'compact']);

    expect($config->getSizes())->toBe(['sm', 'base', 'lg', 'xl', '2xl', 'huge', 'compact']);
});

it('appends extra colors to the defaults', function(): void {
    $config = new TypographyConfig(extraColors: ['mybrand', 'marketing']);

    expect($config->getColors())->toBe(['gray', 'slate', 'zinc', 'neutral', 'stone', 'invert', 'mybrand', 'marketing']);
});

it('deduplicates extras that overlap with defaults', function(): void {
    // A user might paste defaults into the extras list. The merger doesn't
    // care about duplicates — but the public list should still read clean.
    $config = new TypographyConfig(
        extraSizes: ['lg', 'huge'],
        extraColors: ['slate', 'mybrand'],
    );

    expect($config->getSizes())->toBe(['sm', 'base', 'lg', 'xl', '2xl', 'huge']);
    expect($config->getColors())->toBe(['gray', 'slate', 'zinc', 'neutral', 'stone', 'invert', 'mybrand']);
});

it('deduplicates extras that repeat within themselves', function(): void {
    $config = new TypographyConfig(
        extraSizes: ['huge', 'huge', 'compact'],
        extraColors: ['mybrand', 'mybrand'],
    );

    expect($config->getExtraSizes())->toBe(['huge', 'compact']);
    expect($config->getExtraColors())->toBe(['mybrand']);
});

// =========================================================================
// = Merge-engine config shape
// =========================================================================

it('produces the classGroups shape both merge engines accept', function(): void {
    $config = new TypographyConfig();

    expect($config->toMergeConfig())->toBe([
        'classGroups' => [
            'prose-size' => [['prose' => ['sm', 'base', 'lg', 'xl', '2xl']]],
            'prose-color' => [['prose' => ['gray', 'slate', 'zinc', 'neutral', 'stone', 'invert']]],
        ],
    ]);
});

it('reflects extras in the merge-engine config', function(): void {
    $config = new TypographyConfig(
        extraSizes: ['huge'],
        extraColors: ['mybrand'],
    );

    $merge = $config->toMergeConfig();

    expect($merge['classGroups']['prose-size'][0]['prose'])
        ->toBe(['sm', 'base', 'lg', 'xl', '2xl', 'huge']);

    expect($merge['classGroups']['prose-color'][0]['prose'])
        ->toBe(['gray', 'slate', 'zinc', 'neutral', 'stone', 'invert', 'mybrand']);
});

// =========================================================================
// = Signature stability and sensitivity
// =========================================================================

it('produces a stable signature for identical inputs', function(): void {
    $a = new TypographyConfig(extraSizes: ['huge'], extraColors: ['mybrand']);
    $b = new TypographyConfig(extraSizes: ['huge'], extraColors: ['mybrand']);

    expect($a->signature())->toBe($b->signature());
});

it('produces a different signature when extra sizes change', function(): void {
    $a = new TypographyConfig(extraSizes: []);
    $b = new TypographyConfig(extraSizes: ['huge']);

    expect($a->signature())->not->toBe($b->signature());
});

it('produces a different signature when extra colors change', function(): void {
    $a = new TypographyConfig(extraColors: []);
    $b = new TypographyConfig(extraColors: ['mybrand']);

    expect($a->signature())->not->toBe($b->signature());
});

it('produces the same signature regardless of extras order', function(): void {
    // Reordering rows in the CP editable-table shouldn't trigger a merger
    // rebuild — order has no semantic meaning in a conflict group.
    $a = new TypographyConfig(extraSizes: ['huge', 'compact'], extraColors: ['mybrand', 'marketing']);
    $b = new TypographyConfig(extraSizes: ['compact', 'huge'], extraColors: ['marketing', 'mybrand']);

    expect($a->signature())->toBe($b->signature());
});
