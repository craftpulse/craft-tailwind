<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

use TailwindMerge\TailwindMerge as TailwindMergeV3;

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
