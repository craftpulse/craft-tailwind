<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 *
 * @var \craftpulse\tailwind\debug\TailwindPanel $panel
 */

$data = $panel->data;
$unique = is_array($data['merges'] ?? null) ? count($data['merges']) : 0;
$total = (int) ($data['totalCalls'] ?? 0);
?>

<div class="yii-debug-toolbar__block">
    <a href="<?= $panel->getUrl() ?>">
        Tailwind
        <span class="yii-debug-toolbar__label"><?= $total ?> calls</span>
        <span class="yii-debug-toolbar__label"><?= $unique ?> unique</span>
    </a>
</div>
