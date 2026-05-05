<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\debug;

use Craft;
use craftpulse\tailwind\Plugin;
use yii\debug\Panel;

/**
 * Debug toolbar panel for Tailwind merge operations.
 *
 * Surfaces every `craft.tailwind.merge()` and `|twmerge` call made during
 * the current request, including the input, the resolved output, whether
 * the call served from the LRU cache, the originating template, and the
 * number of repeated calls with identical input.
 *
 * @author CraftPulse
 * @since 5.0.0
 */
class TailwindPanel extends Panel
{
    // =========================================================================
    // = Const Properties
    // =========================================================================

    /**
     * Path alias for the panel view templates.
     */
    private const VIEW_PATH = '@craftpulse/tailwind/templates/debug/';

    // =========================================================================
    // = Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @return string The panel name shown in the debug toolbar.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getName(): string
    {
        return 'Tailwind';
    }

    /**
     * @inheritdoc
     *
     * @return string The rendered summary HTML for the toolbar pill.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getSummary(): string
    {
        return Craft::$app->getView()->render(
            self::VIEW_PATH . 'summary',
            ['panel' => $this],
        );
    }

    /**
     * @inheritdoc
     *
     * @return string The rendered detail HTML for the expanded panel.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getDetail(): string
    {
        return Craft::$app->getView()->render(
            self::VIEW_PATH . 'detail',
            ['panel' => $this],
        );
    }

    /**
     * @inheritdoc
     *
     * Returns aggregated merge data for persistence. Each merge entry
     * includes: input, output, resolved (bool), template (?string),
     * line (?int), count (int).
     *
     * Cache metrics: `totalCalls = cacheHits + cacheMisses` and counts
     * every `merge()` call regardless of recording state. The per-record
     * `count` field tracks dedupe-by-input only when recording is on, so
     * `sum(count)` is generally lower than `totalCalls`.
     *
     * @return array{merges: array<int, array<string, mixed>>, totalCalls: int, resolved: int, cacheHits: int, cacheMisses: int, cacheSize: int, version: string}
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function save(): array
    {
        $plugin = Plugin::$plugin;

        if ($plugin === null) {
            return [
                'merges' => [],
                'totalCalls' => 0,
                'resolved' => 0,
                'cacheHits' => 0,
                'cacheMisses' => 0,
                'cacheSize' => 0,
                'version' => 'unknown',
            ];
        }

        $service = $plugin->tailwind;
        $merges = $service->getRecordedMerges();

        $resolved = 0;

        foreach ($merges as $merge) {
            if ($merge['resolved']) {
                $resolved++;
            }
        }

        $cacheHits = $service->getCacheHitCount();
        $cacheMisses = $service->getCacheMissCount();

        return [
            'merges' => $merges,
            'totalCalls' => $cacheHits + $cacheMisses,
            'resolved' => $resolved,
            'cacheHits' => $cacheHits,
            'cacheMisses' => $cacheMisses,
            'cacheSize' => $service->getCacheCount(),
            'version' => $service->getVersion(),
        ];
    }
}
