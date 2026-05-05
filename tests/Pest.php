<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

// Yii2's built-in validators (`in`, `integer`, `string`, etc.) call
// `Yii::createObject()`, which requires the global `Yii` class. Yii's
// composer autoload doesn't include `Yii.php` itself — Craft's web/console
// bootstrap normally loads it. For unit tests that exercise validation
// without booting Craft, we load it here.
require_once dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
