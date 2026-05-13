<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\Application;

use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;

use craftpulse\tailwind\debug\TailwindPanel;
use craftpulse\tailwind\models\Settings;
use craftpulse\tailwind\services\TailwindService;
use craftpulse\tailwind\services\VersionDetector;
use craftpulse\tailwind\variables\TailwindVariable;

use yii\base\Application as BaseApplication;
use yii\base\Event;
use yii\base\InvalidRouteException;
use yii\debug\Module as DebugModule;

/**
 * Tailwind plugin for Craft CMS 5.
 *
 * Provides Tailwind CSS class merging, named-slot class builders,
 * and automatic version detection for Craft CMS templates.
 *
 * @property-read TailwindService $tailwind The main Tailwind service.
 * @property-read VersionDetector $versionDetector The version detector service.
 * @property-read Settings $settings The plugin settings model.
 *
 * @method Settings getSettings()
 *
 * @author CraftPulse
 * @since 5.0.0
 */
class Plugin extends BasePlugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static reference to the plugin instance.
     *
     * @var ?Plugin
     */
    public static ?Plugin $plugin = null;

    // Public Properties
    // =========================================================================

    /**
     * @var string The schema version for this plugin.
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool Whether the plugin has a settings page in the control panel.
     */
    public bool $hasCpSettings = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        Craft::setAlias('@craftpulse/tailwind', __DIR__);

        $this->_registerServices();
        $this->_registerVariables();
        $this->_registerAutoInject();
        $this->_registerDebugPanel();
        $this->_registerCpUrlRules();
    }

    /**
     * @inheritdoc
     *
     * Routes plugin-settings link clicks (from Settings › Plugins) to our
     * own settings controller so the page renders into `_layouts/cp` and
     * Craft's page-tabs strip can read the `tabs` variable directly. The
     * default `settingsHtml()` path embeds returned HTML in a fixed wrapper
     * that has no slot for the tab strip.
     *
     * @return mixed
     *
     * @throws InvalidRouteException
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(
            UrlHelper::cpUrl('tailwind/settings'),
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    // Private Methods
    // =========================================================================

    /**
     * Registers the plugin's service components.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _registerServices(): void
    {
        $this->setComponents([
            'tailwind' => TailwindService::class,
            'versionDetector' => VersionDetector::class,
        ]);
    }

    /**
     * Registers template variables under `craft.tailwind`.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _registerVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event): void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('tailwind', TailwindVariable::class);
            },
        );
    }

    /**
     * Auto-injects CSS variables into the page `<head>` when enabled in settings.
     *
     * Hooks into the view's render-page event so the CSS is registered
     * for every site template render. Console requests and CP requests
     * are skipped.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _registerAutoInject(): void
    {
        $settings = $this->getSettings();

        if (!$settings instanceof Settings || !$settings->autoInject) {
            return;
        }

        if (!Craft::$app instanceof Application) {
            return;
        }

        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest() || $request->getIsCpRequest()) {
            return;
        }

        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function() use ($settings): void {
                $variables = self::$plugin?->tailwind->cssVariables();

                if ($variables === null || $variables->isEmpty()) {
                    return;
                }

                /** @var View $view */
                $view = Craft::$app->getView();
                $view->registerCss(
                    $variables->asCss(),
                    $settings->autoInjectAttributes,
                    'craftpulse-tailwind-css-variables',
                );
            },
        );
    }

    /**
     * Registers the Tailwind debug toolbar panel.
     *
     * The panel surfaces every merge operation from the current request,
     * including the originating template. Registration happens on
     * `Application::EVENT_BEFORE_REQUEST` so the debug module is available.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _registerDebugPanel(): void
    {
        Event::on(
            Application::class,
            BaseApplication::EVENT_BEFORE_REQUEST,
            static function(): void {
                /** @var ?DebugModule $debugModule */
                $debugModule = Craft::$app->getModule('debug');

                if (!$debugModule instanceof DebugModule) {
                    return;
                }

                $debugModule->panels['tailwind'] = new TailwindPanel([
                    'id' => 'tailwind',
                    'module' => $debugModule,
                ]);

                self::$plugin?->tailwind->enableRecording();
            },
        );
    }

    /**
     * Registers CP URL rules so the plugin-settings link routes to our
     * SettingsController rather than the default Craft plugin-settings
     * page wrapper. Three forms point at the same `edit` action so a CP
     * nav click, a typed-in `/tailwind/settings` URL, and Craft's own
     * `settings/plugins/<handle>` redirect all land in the same place.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _registerCpUrlRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            static function(RegisterUrlRulesEvent $event): void {
                $event->rules = array_merge(
                    [
                        'tailwind' => 'tailwind/settings/edit',
                        'tailwind/settings' => 'tailwind/settings/edit',
                    ],
                    $event->rules,
                );
            },
        );
    }
}
