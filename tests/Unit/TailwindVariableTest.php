<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

use craftpulse\tailwind\models\ClassList;
use craftpulse\tailwind\models\CssVariables;
use craftpulse\tailwind\Plugin;
use craftpulse\tailwind\variables\TailwindVariable;

// =========================================================================
// Null-plugin fallbacks
// =========================================================================

// Each `craft.tailwind.*` accessor short-circuits to a safe default when
// `Plugin::$plugin` is unset. The Pest bootstrap doesn't bring up Craft,
// so these tests run against that branch directly. `include()` is not
// exercised here — its fallback path goes through `Template::raw()`,
// which requires `Craft::$app` to be initialized; in real Twig usage the
// app is always up so the branch is unreachable.

beforeEach(function(): void {
    Plugin::$plugin = null;
});

it('returns an empty string from merge() when the plugin is unset', function(): void {
    $variable = new TailwindVariable();

    expect($variable->merge('px-4', 'bg-red-500'))->toBe('');
});

it('returns an empty ClassList from classes() when the plugin is unset', function(): void {
    $variable = new TailwindVariable();

    $list = $variable->classes(['layout' => 'flex', 'color' => 'bg-red-500']);

    expect($list)->toBeInstanceOf(ClassList::class);
    // The fallback merger returns '' on any input, so __toString reflects that.
    expect((string) $list)->toBe('');
});

it('falls back to v4 from getVersion() when the plugin is unset', function(): void {
    // The fallback matches VersionDetector's own fallback — v4 is the
    // forward path so a misdetect leans toward the modern engine.
    $variable = new TailwindVariable();

    expect($variable->getVersion())->toBe('4');
});

it('returns an empty CssVariables from getCssVariables() when the plugin is unset', function(): void {
    $variable = new TailwindVariable();

    $vars = $variable->getCssVariables();

    expect($vars)->toBeInstanceOf(CssVariables::class);
    expect($vars->isEmpty())->toBeTrue();
});
