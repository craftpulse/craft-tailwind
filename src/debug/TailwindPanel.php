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
 * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @return array{merges: array<int, array<string, mixed>>, totalCalls: int, resolved: int, cacheHits: int, cacheSize: int, version: string}
     *
     * @author CraftPulse
     * @since 1.0.0
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
                'cacheSize' => 0,
                'version' => 'unknown',
            ];
        }

        $merges = $plugin->tailwind->getRecordedMerges();

        $totalCalls = 0;
        $resolved = 0;
        $cacheHits = 0;

        foreach ($merges as $merge) {
            $totalCalls += $merge['count'];

            if ($merge['resolved']) {
                $resolved++;
            }

            if ($merge['cacheHit']) {
                $cacheHits += $merge['count'];
            } else {
                // First call was a miss, every subsequent was a hit.
                $cacheHits += $merge['count'] - 1;
            }
        }

        return [
            'merges' => $merges,
            'totalCalls' => $totalCalls,
            'resolved' => $resolved,
            'cacheHits' => $cacheHits,
            'cacheSize' => $plugin->tailwind->getCacheCount(),
            'version' => $plugin->tailwind->getVersion(),
        ];
    }
}
