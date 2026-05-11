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

// `save()` carries a defensive null-plugin branch even though normal
// Plugin::init() ordering means `Plugin::$plugin` is set before the
// panel registers itself — so the branch is functionally unreachable
// today. The test locks the returned shape so a future refactor that
// changes panel registration timing (or a third-party module that pokes
// at TailwindPanel directly) can't silently drop a key the toolbar
// templates rely on.

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
