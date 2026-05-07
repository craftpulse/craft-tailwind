<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

// Yii's `\Yii` and Craft's `\Craft` are global classes outside their
// packages' PSR-4 maps — Craft's web/console bootstrap loads them via
// explicit require. Mirror that here so unit tests can reference
// `Craft::$app` (which is null pre-bootstrap) without a class-not-found.
require_once dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
require_once dirname(__DIR__) . '/vendor/craftcms/cms/src/Craft.php';
