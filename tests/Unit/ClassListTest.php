<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

use craftpulse\tailwind\models\ClassList;

/**
 * A simple merge callable that deduplicates class names (last wins).
 * Used as a test double for the real Tailwind merge engine.
 */
function simpleMerge(string ...$args): string
{
    $all = implode(' ', $args);
    $parts = preg_split('/\s+/', trim($all), -1, PREG_SPLIT_NO_EMPTY);
    $seen = [];

    foreach ($parts as $part) {
        // Extract the utility prefix (e.g., 'bg' from 'bg-red-500')
        $prefix = explode('-', $part)[0];

        // For bg-*, px-*, mt-* etc., keep the last one per prefix
        $seen[$prefix . '-utility'] = $part;
    }

    return implode(' ', array_values($seen));
}

// =========================================================================
// = __toString
// =========================================================================

it('converts to string by merging all slots', function(): void {
    $classList = new ClassList(
        ['layout' => 'flex gap-4', 'spacing' => 'px-4'],
        fn(string ...$args): string => implode(' ', $args),
    );

    expect((string) $classList)->toBe('flex gap-4 px-4');
});

it('returns empty string when no slots are defined', function(): void {
    $classList = new ClassList(
        [],
        fn(string ...$args): string => implode(' ', $args),
    );

    expect((string) $classList)->toBe('');
});

// =========================================================================
// = get
// =========================================================================

it('returns the value for an existing slot', function(): void {
    $classList = new ClassList(
        ['layout' => 'flex gap-4', 'color' => 'bg-blue-500'],
        fn(string ...$args): string => implode(' ', $args),
    );

    expect($classList->get('layout'))->toBe('flex gap-4');
    expect($classList->get('color'))->toBe('bg-blue-500');
});

it('returns null for a non-existent slot', function(): void {
    $classList = new ClassList(
        ['layout' => 'flex'],
        fn(string ...$args): string => implode(' ', $args),
    );

    expect($classList->get('missing'))->toBeNull();
});

// =========================================================================
// = override
// =========================================================================

it('returns a new instance with overridden slots', function(): void {
    $original = new ClassList(
        ['layout' => 'flex', 'color' => 'bg-red-500'],
        fn(string ...$args): string => implode(' ', $args),
    );

    $overridden = $original->override(['color' => 'bg-blue-500']);

    expect($overridden->get('color'))->toBe('bg-blue-500');
    expect($overridden->get('layout'))->toBe('flex');
});

it('preserves immutability on override', function(): void {
    $original = new ClassList(
        ['color' => 'bg-red-500'],
        fn(string ...$args): string => implode(' ', $args),
    );

    $original->override(['color' => 'bg-blue-500']);

    expect($original->get('color'))->toBe('bg-red-500');
});

it('adds new slots via override', function(): void {
    $original = new ClassList(
        ['layout' => 'flex'],
        fn(string ...$args): string => implode(' ', $args),
    );

    $overridden = $original->override(['color' => 'bg-blue-500']);

    expect($overridden->get('color'))->toBe('bg-blue-500');
    expect($overridden->get('layout'))->toBe('flex');
});

// =========================================================================
// = extend
// =========================================================================

it('extends existing slots by concatenation', function(): void {
    $original = new ClassList(
        ['layout' => 'flex'],
        fn(string ...$args): string => implode(' ', $args),
    );

    $extended = $original->extend(['layout' => 'gap-4']);

    expect($extended->get('layout'))->toBe('flex gap-4');
});

it('adds new slots via extend', function(): void {
    $original = new ClassList(
        ['layout' => 'flex'],
        fn(string ...$args): string => implode(' ', $args),
    );

    $extended = $original->extend(['color' => 'bg-blue-500']);

    expect($extended->get('color'))->toBe('bg-blue-500');
    expect($extended->get('layout'))->toBe('flex');
});

it('preserves immutability on extend', function(): void {
    $original = new ClassList(
        ['layout' => 'flex'],
        fn(string ...$args): string => implode(' ', $args),
    );

    $original->extend(['layout' => 'gap-4']);

    expect($original->get('layout'))->toBe('flex');
});

// =========================================================================
// = without
// =========================================================================

it('removes specified slots', function(): void {
    $original = new ClassList(
        ['layout' => 'flex', 'color' => 'bg-red-500', 'font' => 'text-lg'],
        fn(string ...$args): string => implode(' ', $args),
    );

    $reduced = $original->without('color', 'font');

    expect($reduced->toArray())->toBe(['layout' => 'flex']);
});

it('preserves immutability on without', function(): void {
    $original = new ClassList(
        ['layout' => 'flex', 'color' => 'bg-red-500'],
        fn(string ...$args): string => implode(' ', $args),
    );

    $original->without('color');

    expect($original->get('color'))->toBe('bg-red-500');
});

it('handles removing non-existent slots gracefully', function(): void {
    $original = new ClassList(
        ['layout' => 'flex'],
        fn(string ...$args): string => implode(' ', $args),
    );

    $reduced = $original->without('missing');

    expect($reduced->toArray())->toBe(['layout' => 'flex']);
});

// =========================================================================
// = merge
// =========================================================================

it('merges additional classes with all slots', function(): void {
    $classList = new ClassList(
        ['layout' => 'flex', 'spacing' => 'px-4'],
        fn(string ...$args): string => implode(' ', $args),
    );

    $result = $classList->merge('mt-4');

    expect($result)->toBe('flex px-4 mt-4');
});

it('merges empty additional string', function(): void {
    $classList = new ClassList(
        ['layout' => 'flex'],
        fn(string ...$args): string => implode(' ', $args),
    );

    $result = $classList->merge('');

    expect($result)->toBe('flex ');
});

// =========================================================================
// = toArray
// =========================================================================

it('returns all slots as an associative array', function(): void {
    $slots = ['layout' => 'flex gap-4', 'color' => 'bg-blue-500'];

    $classList = new ClassList(
        $slots,
        fn(string ...$args): string => implode(' ', $args),
    );

    expect($classList->toArray())->toBe($slots);
});

it('returns empty array when no slots exist', function(): void {
    $classList = new ClassList(
        [],
        fn(string ...$args): string => implode(' ', $args),
    );

    expect($classList->toArray())->toBe([]);
});

// =========================================================================
// = Chaining
// =========================================================================

it('supports chaining multiple operations', function(): void {
    $original = new ClassList(
        ['layout' => 'flex', 'color' => 'bg-red-500', 'font' => 'text-sm'],
        fn(string ...$args): string => implode(' ', $args),
    );

    $result = $original
        ->override(['color' => 'bg-blue-500'])
        ->extend(['spacing' => 'px-4'])
        ->without('font');

    expect($result->toArray())->toBe([
        'layout' => 'flex',
        'color' => 'bg-blue-500',
        'spacing' => 'px-4',
    ]);

    // Original must be unchanged
    expect($original->toArray())->toBe([
        'layout' => 'flex',
        'color' => 'bg-red-500',
        'font' => 'text-sm',
    ]);
});
