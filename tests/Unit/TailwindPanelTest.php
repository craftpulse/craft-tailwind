<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

use craftpulse\tailwind\debug\TailwindPanel;
use craftpulse\tailwind\Plugin;

// =========================================================================
// save() — null-plugin shape
// =========================================================================

// When the panel's `save()` runs without the plugin instance available
// (e.g. before `init()` completes, or a registration ordering edge),
// it must return a stable shape so the toolbar templates can render
// safely. Locking the contract here means a future refactor of the
// `save()` array shape can't silently drop a key the templates rely on.

beforeEach(function(): void {
    Plugin::$plugin = null;
});

it('returns a complete zero-valued shape when the plugin is unset', function(): void {
    $panel = new TailwindPanel(['id' => 'tailwind', 'module' => null]);

    expect($panel->save())->toBe([
        'merges' => [],
        'totalCalls' => 0,
        'resolved' => 0,
        'cacheHits' => 0,
        'cacheMisses' => 0,
        'cacheSize' => 0,
        'version' => 'unknown',
        'typography' => null,
    ]);
});
