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

<h1>Tailwind Merges</h1>

<p>Detected version: <strong>Tailwind v<?= Html::encode($version) ?></strong></p>
<p>Total calls: <strong><?= $totalCalls ?></strong> (<?= $unique ?> unique, <?= $resolved ?> resolved a conflict).</p>
<p>Cache: <strong><?= $cacheHits ?></strong> hits (<?= $hitRate ?>% hit rate), <?= $cacheSize ?> entries held.</p>
<p>
    Typography:
    <?php if ($typography === null): ?>
        <span class="text-muted">disabled</span>
    <?php else:
        $sizeCount = count($typography['sizes'] ?? []);
        $colorCount = count($typography['colors'] ?? []);
        $customSuffixes = array_map(
            static fn(string $suffix): string => 'prose-' . $suffix,
            [...($typography['extraSizes'] ?? []), ...($typography['extraColors'] ?? [])],
        );
        ?>
        <strong>enabled</strong> (<?= $sizeCount ?> sizes, <?= $colorCount ?> colors)<?php if ($customSuffixes !== []): ?> <span class="text-muted">&middot; custom: <?= Html::encode(implode(', ', $customSuffixes)) ?></span><?php endif; ?>
    <?php endif; ?>
</p>

<?php if ($merges === []): ?>
    <p><em>No merge operations recorded for this request.</em></p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-condensed table-bordered table-striped table-hover" style="table-layout: fixed;">
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
                    <?php $resolved = $merge['resolved']; ?>
                    <?php $outputClass = $resolved ? 'text-success' : 'text-muted'; ?>
                    <tr>
                        <td class="align-middle"><?= (int) $merge['count'] ?></td>
                        <td class="align-middle"><span class="<?= $outputClass ?>"><?= $resolved ? 'resolved' : 'passthrough' ?></span></td>
                        <td class="align-middle"><code><?= Html::encode($merge['input']) ?></code></td>
                        <td class="align-middle"><code class="<?= $outputClass ?>"><?= Html::encode($merge['output']) ?></code></td>
                        <td class="align-middle"><?php if ($merge['template'] !== null): ?><code><?= Html::encode($merge['template']) ?></code><?php if (!empty($merge['line'])): ?><code class="text-muted">:<?= (int) $merge['line'] ?></code><?php endif; ?><?php else: ?><em class="text-muted">outside template</em><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
