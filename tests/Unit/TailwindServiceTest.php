<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

use craftpulse\tailwind\models\Settings;
use craftpulse\tailwind\services\TailwindService;
use TailwindMerge\TailwindMerge as TailwindMergeV3;

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
// = V3 Merge Engine (Direct)
// =========================================================================

it('merges conflicting background classes using v3 engine', function(): void {
    $merger = TailwindMergeV3::instance();

    $result = $merger->merge('bg-red-500', 'bg-blue-500');

    expect($result)->toBe('bg-blue-500');
});

it('preserves non-conflicting classes using v3 engine', function(): void {
    $merger = TailwindMergeV3::instance();

    $result = $merger->merge('px-4 bg-red-500', 'mt-4');

    expect($result)->toBe('px-4 bg-red-500 mt-4');
});

it('resolves conflicting padding classes using v3 engine', function(): void {
    $merger = TailwindMergeV3::instance();

    $result = $merger->merge('px-4 py-2', 'px-8');

    expect($result)->toBe('py-2 px-8');
});

it('handles empty strings using v3 engine', function(): void {
    $merger = TailwindMergeV3::instance();

    $result = $merger->merge('', '');

    expect($result)->toBe('');
});

it('handles single class string using v3 engine', function(): void {
    $merger = TailwindMergeV3::instance();

    $result = $merger->merge('flex');

    expect($result)->toBe('flex');
});

it('resolves conflicting text color classes using v3 engine', function(): void {
    $merger = TailwindMergeV3::instance();

    $result = $merger->merge('text-red-500 text-lg', 'text-blue-500');

    expect($result)->toBe('text-lg text-blue-500');
});

it('merges multiple arguments using v3 engine', function(): void {
    $merger = TailwindMergeV3::instance();

    $result = $merger->merge('px-4', 'bg-red-500', 'bg-blue-500 mt-4');

    expect($result)->toBe('px-4 bg-blue-500 mt-4');
});

// =========================================================================
// = V4 Merge Engine (Direct)
// =========================================================================

it('merges conflicting background classes using v4 engine', function(): void {
    $merger = new TalesFromADev\TailwindMerge\TailwindMerge();

    $result = $merger->merge('bg-red-500', 'bg-blue-500');

    expect($result)->toBe('bg-blue-500');
});

it('preserves non-conflicting classes using v4 engine', function(): void {
    $merger = new TalesFromADev\TailwindMerge\TailwindMerge();

    $result = $merger->merge('px-4 bg-red-500', 'mt-4');

    expect($result)->toBe('px-4 bg-red-500 mt-4');
});

it('resolves conflicting padding classes using v4 engine', function(): void {
    $merger = new TalesFromADev\TailwindMerge\TailwindMerge();

    $result = $merger->merge('px-4 py-2', 'px-8');

    expect($result)->toBe('py-2 px-8');
});

it('handles empty strings using v4 engine', function(): void {
    $merger = new TalesFromADev\TailwindMerge\TailwindMerge();

    $result = $merger->merge('', '');

    expect($result)->toBe('');
});

it('merges multiple arguments using v4 engine', function(): void {
    $merger = new TalesFromADev\TailwindMerge\TailwindMerge();

    $result = $merger->merge('px-4', 'bg-red-500', 'bg-blue-500 mt-4');

    expect($result)->toBe('px-4 bg-blue-500 mt-4');
});

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
    // Use v3 explicitly because v4 doesn't have a configurable prefix.
    $service = makeService(['tailwindVersion' => '3', 'prefix' => '']);

    $first = $service->merge('px-4 px-6');

    expect($first)->toBe('px-6');

    // Swap to a prefixed configuration. A stale merger would still
    // recognize unprefixed `px-*` as conflicts; the rebuilt merger
    // should treat them as opaque strings under the `tw-` prefix.
    $service->settings = new Settings([
        'tailwindVersion' => '3',
        'prefix' => 'tw-',
        'cacheSize' => 500,
    ]);
    $service->clearCache();

    $second = $service->merge('tw-px-4 tw-px-6');

    expect($second)->toBe('tw-px-6');
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
