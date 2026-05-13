<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\UrlManager;

use craftpulse\tailwind\Plugin;

use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Settings controller — renders the plugin settings page and persists posts.
 *
 * Routed in `Plugin::_registerCpUrlRules()` so that `tailwind/settings` and
 * the plugin's CP entry point both land here. We render into `_layouts/cp`
 * via the settings template so Craft's page-tabs strip can pick up the
 * tab declaration directly (the layout reads `tabs` from the rendering
 * context and emits the strip + tab-pane JS itself).
 *
 * @author CraftPulse
 * @since 5.0.0
 */
class SettingsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * Requires an admin user for every action; the `allowAdminChanges`
     * gate is enforced per-action so the view can render read-only in
     * production while saves remain blocked.
     *
     * @throws ForbiddenHttpException
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function beforeAction($action): bool
    {
        $this->requireAdmin(false);

        return parent::beforeAction($action);
    }

    /**
     * Renders the plugin settings form. Displays read-only when
     * `allowAdminChanges` is disabled so editors can still inspect
     * the configured values in production.
     *
     * The `$plugin` parameter is the route-param fallback used after a
     * failed save in `actionSave()` — passing the in-memory plugin with
     * populated validation errors so the form can re-render them.
     *
     * @param ?Plugin $plugin The plugin instance forwarded from a failed
     *                        save, or `null` to fall back to the registered
     *                        singleton.
     *
     * @return ?Response
     *
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \yii\base\Exception
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function actionEdit(?Plugin $plugin = null): ?Response
    {
        $plugin ??= Plugin::$plugin;

        if ($plugin === null) {
            throw new NotFoundHttpException('Tailwind plugin not loaded.');
        }

        $settings = $plugin->getSettings();
        $detector = $plugin->versionDetector;

        // Pre-compute the auto-detect result so the settings template can
        // surface "v3 detected via tailwind.config.js" to the editor.
        $autoDetectVersion = $detector->detect(
            'auto',
            $settings->buildchainPath,
            $settings->cssPath,
        );

        $readOnly = !Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
        $pluginName = 'Tailwind';
        $title = Craft::t('tailwind', 'Plugin Settings');

        return $this->renderTemplate('tailwind/settings', [
            'pluginName' => $pluginName,
            'title' => $title,
            'docTitle' => sprintf('%s - %s', $pluginName, $title),
            'crumbs' => [
                [
                    'label' => Craft::t('app', 'Settings'),
                    'url' => UrlHelper::cpUrl('settings'),
                ],
                [
                    'label' => Craft::t('app', 'Plugins'),
                    'url' => UrlHelper::cpUrl('settings/plugins'),
                ],
            ],
            'settings' => $settings,
            'overrides' => Craft::$app->getConfig()->getConfigFromFile('tailwind'),
            'autoDetectVersion' => $autoDetectVersion,
            'autoDetectReason' => $detector->getLastReason(),
            'readOnly' => $readOnly,
        ]);
    }

    /**
     * Persists the plugin settings posted by the edit form.
     *
     * @return ?Response
     *
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function actionSave(): ?Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $pluginHandle = $request->getRequiredBodyParam('pluginHandle');
        $plugin = Craft::$app->getPlugins()->getPlugin($pluginHandle);

        if ($plugin === null) {
            throw new NotFoundHttpException('Plugin not found.');
        }

        /** @var array<string, mixed> $settings */
        $settings = $request->getBodyParam('settings', []);

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings)) {
            Craft::$app->getSession()->setError(
                Craft::t('app', "Couldn't save plugin settings."),
            );

            // Send the route params back so the form re-renders with errors.
            /** @var UrlManager $urlManager */
            $urlManager = Craft::$app->getUrlManager();
            $urlManager->setRouteParams([
                'plugin' => $plugin,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(
            Craft::t('app', 'Plugin settings saved.'),
        );

        return $this->redirectToPostedUrl();
    }
}
