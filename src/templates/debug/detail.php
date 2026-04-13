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
$unique = count($merges);
$hitRate = $totalCalls > 0 ? round(($cacheHits / $totalCalls) * 100, 1) : 0.0;
?>

<h1>Tailwind Merges</h1>

<div class="summary">
    <div class="row">
        <strong>Detected version:</strong> Tailwind v<?= Html::encode($version) ?>
    </div>
    <div class="row">
        <strong>Total calls:</strong> <?= $totalCalls ?>
        (<?= $unique ?> unique,
        <?= $resolved ?> resolved a conflict)
    </div>
    <div class="row">
        <strong>Cache:</strong> <?= $cacheHits ?> hits
        (<?= $hitRate ?>% hit rate),
        <?= $cacheSize ?> entries held
    </div>
</div>

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
                            <span class="label label-warning" style="background: #f0ad4e; color: #fff; padding: 2px 6px; border-radius: 3px;">resolved</span>
                        <?php else: ?>
                            <span class="label label-default" style="background: #999; color: #fff; padding: 2px 6px; border-radius: 3px;">passthrough</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= Html::encode($merge['input']) ?></code></td>
                    <td>
                        <?php if ($merge['resolved']): ?>
                            <code style="color: #5cb85c;"><?= Html::encode($merge['output']) ?></code>
                        <?php else: ?>
                            <code style="color: #999;"><?= Html::encode($merge['output']) ?></code>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($merge['template'] !== null): ?>
                            <code><?= Html::encode($merge['template']) ?></code>
                        <?php else: ?>
                            <em style="color: #999;">outside template</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
