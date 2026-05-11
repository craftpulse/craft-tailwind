<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

use craftpulse\tailwind\models\Settings;
use craftpulse\tailwind\services\TailwindService;

// =========================================================================
// = Helpers
// =========================================================================

/**
 * Builds a TailwindService with injected settings for testing.
 */
function makeService(array $settings = []): TailwindService
{
    $defaults = [
        'tailwindVersion' => '3',
        'cacheSize' => 500,
    ];

    $service = new TailwindService();
    $service->settings = new Settings(array_merge($defaults, $settings));

    return $service;
}

// =========================================================================
// = Service: Merge + Cache + Counters
// =========================================================================

it('returns the merged result through the service', function(): void {
    $service = makeService();

    expect($service->merge('bg-red-500', 'bg-blue-500'))->toBe('bg-blue-500');
});

it('returns an empty string for empty input without touching the cache', function(): void {
    $service = makeService();

    expect($service->merge(''))->toBe('');
    expect($service->getCacheCount())->toBe(0);
    expect($service->getCacheMissCount())->toBe(0);
    expect($service->getCacheHitCount())->toBe(0);
});

it('counts the first merge as a miss and stores the result', function(): void {
    $service = makeService();

    $service->merge('bg-red-500', 'bg-blue-500');

    expect($service->getCacheMissCount())->toBe(1);
    expect($service->getCacheHitCount())->toBe(0);
    expect($service->getCacheCount())->toBe(1);
});

it('counts repeat calls with the same input as cache hits', function(): void {
    $service = makeService();

    $service->merge('bg-red-500', 'bg-blue-500');
    $service->merge('bg-red-500', 'bg-blue-500');
    $service->merge('bg-red-500', 'bg-blue-500');

    expect($service->getCacheMissCount())->toBe(1);
    expect($service->getCacheHitCount())->toBe(2);
    expect($service->getCacheCount())->toBe(1);
});

it('normalizes whitespace before cache lookup', function(): void {
    $service = makeService();

    $service->merge('bg-red-500   bg-blue-500');
    $service->merge('  bg-red-500 bg-blue-500  ');

    expect($service->getCacheMissCount())->toBe(1);
    expect($service->getCacheHitCount())->toBe(1);
});

// =========================================================================
// = Service: LRU Eviction
// =========================================================================

it('evicts the oldest entry when the cache reaches capacity', function(): void {
    $service = makeService(['cacheSize' => 2]);

    $service->merge('bg-red-500');
    $service->merge('bg-blue-500');
    $service->merge('bg-green-500');

    expect($service->getCacheCount())->toBe(2);

    // The oldest (bg-red-500) was evicted, so calling it again is a fresh miss.
    $service->merge('bg-red-500');

    expect($service->getCacheMissCount())->toBe(4);
    expect($service->getCacheHitCount())->toBe(0);
});

it('keeps a touched entry alive when newer entries arrive', function(): void {
    $service = makeService(['cacheSize' => 2]);

    $service->merge('bg-red-500');                      // miss=1
    $service->merge('bg-blue-500');                     // miss=2
    $service->merge('bg-red-500');                      // hit=1, refresh red
    $service->merge('bg-green-500');                    // miss=3, evict blue
    $service->merge('bg-red-500');                      // hit=2 — red survived

    expect($service->getCacheMissCount())->toBe(3);
    expect($service->getCacheHitCount())->toBe(2);
});

it('handles a capacity-1 cache as a single-slot LRU', function(): void {
    $service = makeService(['cacheSize' => 1]);

    $service->merge('bg-red-500');                      // miss=1, cache=[red]
    $service->merge('bg-blue-500');                     // miss=2, evict red, cache=[blue]
    $service->merge('bg-blue-500');                     // hit=1, cache=[blue]
    $service->merge('bg-red-500');                      // miss=3, evict blue, cache=[red]

    expect($service->getCacheCount())->toBe(1);
    expect($service->getCacheMissCount())->toBe(3);
    expect($service->getCacheHitCount())->toBe(1);
});

it('disables caching entirely when cacheSize is zero', function(): void {
    $service = makeService(['cacheSize' => 0]);

    $service->merge('bg-red-500');
    $service->merge('bg-red-500');
    $service->merge('bg-red-500');

    expect($service->getCacheCount())->toBe(0);
    expect($service->getCacheHitCount())->toBe(0);
    expect($service->getCacheMissCount())->toBe(3);
});

// =========================================================================
// = Service: clearCache
// =========================================================================

it('preserves the recording-enabled state across clearCache', function(): void {
    $service = makeService();

    $service->enableRecording();
    $service->merge('bg-red-500');
    $service->clearCache();
    $service->merge('bg-blue-500');

    expect($service->getRecordedMerges())->toHaveCount(1);
});

it('rebuilds the v3 merger when the prefix changes between calls', function(): void {
    $service = makeService(['tailwindVersion' => '3', 'prefix' => null]);

    $first = $service->merge('px-4 px-6');

    expect($first)->toBe('px-6');

    // Swap to a prefixed configuration. A stale merger would still
    // recognize unprefixed `px-*` as conflicts; the rebuilt merger
    // should treat them as opaque strings under the `tw-` prefix.
    $service->settings = new Settings([
        'tailwindVersion' => '3',
        'prefix' => 'tw',
        'cacheSize' => 500,
    ]);
    $service->clearCache();

    $second = $service->merge('tw-px-4 tw-px-6');

    expect($second)->toBe('tw-px-6');
});

// =========================================================================
// = Service: Prefix — v3 + v4 wire-up and input normalization
// =========================================================================

it('emits the v3 fused prefix shape when prefix is set on v3', function(): void {
    $service = makeService(['tailwindVersion' => '3', 'prefix' => 'tw']);

    expect($service->merge('tw-px-4 tw-px-6'))->toBe('tw-px-6');
    // Variant before prefix, the v3 syntax shape.
    expect($service->merge('hover:tw-bg-red-500 hover:tw-bg-blue-500'))
        ->toBe('hover:tw-bg-blue-500');
});

it('emits the v4 variant-style prefix shape when prefix is set on v4', function(): void {
    $service = makeService(['tailwindVersion' => '4', 'prefix' => 'tw']);

    expect($service->merge('tw:px-4 tw:px-6'))->toBe('tw:px-6');
    // Prefix is leftmost in v4, even before variants.
    expect($service->merge('tw:hover:bg-red-500 tw:hover:bg-blue-500'))
        ->toBe('tw:hover:bg-blue-500');
});

it('rebuilds the v4 merger when the prefix changes between calls', function(): void {
    $service = makeService(['tailwindVersion' => '4', 'prefix' => null]);

    // Without a configured prefix, the v4 lib treats `tw:` as an unknown
    // modifier — the underlying `px-{4,6}` still resolves, so we have to
    // probe with a class shape that only merges once the prefix is wired.
    expect($service->merge('px-4 px-6'))->toBe('px-6');

    $service->settings = new Settings([
        'tailwindVersion' => '4',
        'prefix' => 'tw',
        'cacheSize' => 500,
    ]);
    $service->clearCache();

    expect($service->merge('tw:px-4 tw:px-6'))->toBe('tw:px-6');
});

it('strips a trailing hyphen from the stored prefix before feeding the engine', function(): void {
    // v3 doc-canonical form is `'tw-'`. After normalization the v3 lib
    // receives `'tw-'` (bare + appended `-`), not `'tw--'`, so prefixed
    // classes merge cleanly without double hyphens leaking through.
    $service = makeService(['tailwindVersion' => '3', 'prefix' => 'tw-']);

    expect($service->merge('tw-px-4 tw-px-6'))->toBe('tw-px-6');
});

// =========================================================================
// = Service: Typography conflict resolution (opt-in)
// =========================================================================

it('does not resolve prose-* conflicts when typography is off (v3)', function(): void {
    $service = makeService(['tailwindVersion' => '3', 'typography' => false]);

    // Without the typography conflict groups wired, both prose-sm and
    // prose-lg are unknown classes to the merger and pass through.
    expect($service->merge('prose prose-sm prose-lg'))->toBe('prose prose-sm prose-lg');
});

it('does not resolve prose-* conflicts when typography is off (v4)', function(): void {
    $service = makeService(['tailwindVersion' => '4', 'typography' => false]);

    expect($service->merge('prose prose-sm prose-lg'))->toBe('prose prose-sm prose-lg');
});

it('resolves prose-size last-wins when typography is on (v3 and v4)', function(): void {
    foreach (['3', '4'] as $version) {
        $service = makeService(['tailwindVersion' => $version, 'typography' => true]);

        expect($service->merge('prose prose-sm prose-lg'))->toBe('prose prose-lg');
        expect($service->merge('prose prose-base prose-sm prose-lg'))->toBe('prose prose-lg');
    }
});

it('resolves prose-color last-wins when typography is on (v3 and v4)', function(): void {
    foreach (['3', '4'] as $version) {
        $service = makeService(['tailwindVersion' => $version, 'typography' => true]);

        expect($service->merge('prose prose-slate prose-invert'))->toBe('prose prose-invert');
    }
});

it('keeps prose-size and prose-color orthogonal when both are present (v3 and v4)', function(): void {
    foreach (['3', '4'] as $version) {
        $service = makeService(['tailwindVersion' => $version, 'typography' => true]);

        // A size and a color are different concerns — neither should
        // evict the other. The input order is preserved.
        expect($service->merge('prose prose-lg prose-invert'))->toBe('prose prose-lg prose-invert');
    }
});

it('resolves custom typography suffixes registered via extras (v3 and v4)', function(): void {
    foreach (['3', '4'] as $version) {
        $service = makeService([
            'tailwindVersion' => $version,
            'typography' => true,
            'typographyExtraSizes' => ['huge'],
            'typographyExtraColors' => ['mybrand'],
        ]);

        // Custom suffix wins against a default — the conflict group now
        // recognizes both as size-class members.
        expect($service->merge('prose prose-lg prose-huge'))->toBe('prose prose-huge');
        expect($service->merge('prose prose-slate prose-mybrand'))->toBe('prose prose-mybrand');
    }
});

it('rebuilds the merger when the typography setting toggles (v3)', function(): void {
    $service = makeService(['tailwindVersion' => '3', 'typography' => false]);

    expect($service->merge('prose prose-sm prose-lg'))->toBe('prose prose-sm prose-lg');

    // Flip the setting; a stale merger would still passthrough.
    $service->settings = new Settings([
        'tailwindVersion' => '3',
        'typography' => true,
        'cacheSize' => 500,
    ]);
    $service->clearCache();

    expect($service->merge('prose prose-sm prose-lg'))->toBe('prose prose-lg');
});

it('rebuilds the merger when typography extras change (v4)', function(): void {
    $service = makeService([
        'tailwindVersion' => '4',
        'typography' => true,
        'typographyExtraSizes' => [],
    ]);

    // Without `huge` in the size group, `prose-huge` is unknown and passes.
    expect($service->merge('prose prose-lg prose-huge'))->toBe('prose prose-lg prose-huge');

    $service->settings = new Settings([
        'tailwindVersion' => '4',
        'typography' => true,
        'typographyExtraSizes' => ['huge'],
        'cacheSize' => 500,
    ]);
    $service->clearCache();

    expect($service->merge('prose prose-lg prose-huge'))->toBe('prose prose-huge');
});

it('exposes the active typography config via typographyConfig()', function(): void {
    // Consumed by TailwindPanel::save() — the panel branches on null vs
    // instance to render either "disabled" or the resolved size/color
    // lists, so the accessor's contract is part of the public surface.
    $off = makeService(['typography' => false]);

    expect($off->typographyConfig())->toBeNull();

    $on = makeService([
        'typography' => true,
        'typographyExtraSizes' => ['huge'],
        'typographyExtraColors' => ['mybrand'],
    ]);

    $config = $on->typographyConfig();

    expect($config)->not->toBeNull();
    expect($config->getExtraSizes())->toBe(['huge']);
    expect($config->getExtraColors())->toBe(['mybrand']);
});

it('resets cache, counters, recordings, and memoized variables on clearCache', function(): void {
    $service = makeService(['cssVariables' => ['--brand' => '#222']]);

    $service->enableRecording();
    $service->merge('bg-red-500');
    $service->merge('bg-red-500');
    $service->cssVariables(); // memoize

    expect($service->getCacheCount())->toBe(1);
    expect($service->getCacheHitCount())->toBe(1);
    expect($service->getCacheMissCount())->toBe(1);
    expect($service->getRecordedMerges())->not->toBeEmpty();

    $service->clearCache();

    expect($service->getCacheCount())->toBe(0);
    expect($service->getCacheHitCount())->toBe(0);
    expect($service->getCacheMissCount())->toBe(0);
    expect($service->getRecordedMerges())->toBe([]);
});

// =========================================================================
// = Service: CSS Variables Memoization
// =========================================================================

it('returns a memoized CssVariables instance across calls', function(): void {
    $service = makeService(['cssVariables' => ['--brand' => '#222']]);

    $first = $service->cssVariables();
    $second = $service->cssVariables();

    expect($first)->toBe($second);
});

it('reflects the injected settings in the memoized cssVariables container', function(): void {
    $service = makeService(['cssVariables' => ['--color-brand' => '#3490dc']]);

    expect($service->cssVariables()->get('--color-brand'))->toBe('#3490dc');
});

// =========================================================================
// = Service: Recording
// =========================================================================

it('does not record merges when recording is disabled', function(): void {
    $service = makeService();

    $service->merge('bg-red-500');
    $service->merge('px-4');

    expect($service->getRecordedMerges())->toBe([]);
});

it('records merges only after enableRecording is called', function(): void {
    $service = makeService();

    $service->merge('bg-red-500'); // before enable — not recorded

    $service->enableRecording();
    $service->merge('bg-blue-500');
    $service->merge('px-4');

    $records = $service->getRecordedMerges();

    expect($records)->toHaveCount(2);
});

it('deduplicates records by input string and increments count on repeat', function(): void {
    $service = makeService();
    $service->enableRecording();

    $service->merge('bg-red-500 bg-blue-500');
    $service->merge('bg-red-500 bg-blue-500');
    $service->merge('bg-red-500 bg-blue-500');

    $records = $service->getRecordedMerges();

    expect($records)->toHaveCount(1);
    expect($records[0]['count'])->toBe(3);
});

it('flags resolved conflicts in recorded merges', function(): void {
    $service = makeService();
    $service->enableRecording();

    $service->merge('bg-red-500 bg-blue-500'); // conflict — resolved
    $service->merge('px-4 mt-2');              // no conflict — passthrough

    $records = $service->getRecordedMerges();
    $byInput = array_column($records, null, 'input');

    expect($byInput['bg-red-500 bg-blue-500']['resolved'])->toBeTrue();
    expect($byInput['px-4 mt-2']['resolved'])->toBeFalse();
});
