<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 *
 * @var \craftpulse\tailwind\debug\TailwindPanel $panel
 */

use yii\helpers\Html;

$data = $panel->data;
$merges = is_array($data['merges'] ?? null) ? $data['merges'] : [];
$totalCalls = (int) ($data['totalCalls'] ?? 0);
$resolved = (int) ($data['resolved'] ?? 0);
$cacheHits = (int) ($data['cacheHits'] ?? 0);
$cacheSize = (int) ($data['cacheSize'] ?? 0);
$version = (string) ($data['version'] ?? 'unknown');
$typography = is_array($data['typography'] ?? null) ? $data['typography'] : null;
$unique = count($merges);
$hitRate = $totalCalls > 0 ? round(($cacheHits / $totalCalls) * 100, 1) : 0.0;
?>

<style>
    .tailwind-panel p {
        margin: 0 0 6px;
    }

    /* Force monospace on inline code regardless of how the Yii toolbar's
       cascade resolves <code> defaults — some toolbar themes flatten it
       to the body sans-serif. */
    .tailwind-panel code {
        font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
        font-size: 0.95em;
    }

    /* Vertically center cell contents so the status indicator sits with
       the input/output text when long class lists wrap the row. */
    .tailwind-panel table td,
    .tailwind-panel table th {
        vertical-align: middle;
    }

    /* Status indicators: text + color, sized to read at a glance without
       relying on color alone. Contrast on white meets WCAG AA. */
    .tailwind-panel .status {
        display: inline-block;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75em;
        letter-spacing: 0.04em;
    }

    .tailwind-panel .status-resolved {
        color: #b25000;
    }

    .tailwind-panel .status-passthrough {
        color: #666;
    }

    .tailwind-panel .output-resolved {
        color: #2a7e2a;
    }

    .tailwind-panel .muted {
        color: #666;
    }
</style>

<div class="tailwind-panel">
    <h1>Tailwind Merges</h1>

    <p>Detected version: <strong>Tailwind v<?= Html::encode($version) ?></strong></p>
    <p>Total calls: <strong><?= $totalCalls ?></strong> (<?= $unique ?> unique, <?= $resolved ?> resolved a conflict).</p>
    <p>Cache: <strong><?= $cacheHits ?></strong> hits (<?= $hitRate ?>% hit rate), <?= $cacheSize ?> entries held.</p>
    <p>
        Typography:
        <?php if ($typography === null): ?>
            <span class="muted">disabled</span>
        <?php else:
            $sizeCount = count($typography['sizes'] ?? []);
            $colorCount = count($typography['colors'] ?? []);
            $customSuffixes = array_map(
                static fn(string $suffix): string => 'prose-' . $suffix,
                [...($typography['extraSizes'] ?? []), ...($typography['extraColors'] ?? [])],
            );
            ?>
            <strong>enabled</strong> (<?= $sizeCount ?> sizes, <?= $colorCount ?> colors)<?php if ($customSuffixes !== []): ?> <span class="muted">&middot; custom: <?= Html::encode(implode(', ', $customSuffixes)) ?></span><?php endif; ?>
        <?php endif; ?>
    </p>

    <?php if ($merges === []): ?>
        <p><em>No merge operations recorded for this request.</em></p>
    <?php else: ?>
        <table class="table table-condensed table-bordered table-striped table-hover">
            <thead>
                <tr>
                    <th style="width: 8%;">Calls</th>
                    <th style="width: 12%;">Status</th>
                    <th style="width: 28%;">Input</th>
                    <th style="width: 28%;">Output</th>
                    <th style="width: 24%;">Template</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($merges as $merge): ?>
                    <tr>
                        <td><?= (int) $merge['count'] ?></td>
                        <td>
                            <?php if ($merge['resolved']): ?>
                                <span class="status status-resolved">resolved</span>
                            <?php else: ?>
                                <span class="status status-passthrough">passthrough</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= Html::encode($merge['input']) ?></code></td>
                        <td>
                            <?php if ($merge['resolved']): ?>
                                <code class="output-resolved"><?= Html::encode($merge['output']) ?></code>
                            <?php else: ?>
                                <code class="muted"><?= Html::encode($merge['output']) ?></code>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($merge['template'] !== null): ?>
                                <code><?= Html::encode($merge['template']) ?></code><?php if (!empty($merge['line'])): ?><code class="muted">:<?= (int) $merge['line'] ?></code><?php endif; ?>
                            <?php else: ?>
                                <em class="muted">outside template</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
