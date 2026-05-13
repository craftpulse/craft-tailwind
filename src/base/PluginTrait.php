<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\base;

use Craft;
use craft\base\Model;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\Application;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;

use craftpulse\tailwind\debug\TailwindPanel;
use craftpulse\tailwind\models\Settings;
use craftpulse\tailwind\Tailwind;
use craftpulse\tailwind\variables\TailwindVariable;

use yii\base\Application as BaseApplication;
use yii\base\Event;
use yii\base\InvalidRouteException;
use yii\debug\Module as DebugModule;

/**
 * Plugin-class extensions for the Tailwind plugin.
 *
 * Holds the register* private methods invoked from `Tailwind::init()`,
 * the settings-response overrides that redirect to the dedicated
 * `SettingsController` route, and the settings model factory.
 *
 * @author CraftPulse
 * @since 5.0.0
 */
trait PluginTrait
{
    // Public Methods
    // =========================================================================

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

    /**
     * @inheritdoc
     *
     * Mirrors `getSettingsResponse()` for the read-only flow Craft enters
     * when `allowAdminChanges` is disabled. The `SettingsController` derives
     * read-only state from the same config flag, so both entry points land
     * on the same URL and the template handles the disabled-fields render.
     *
     * @return mixed
     *
     * @throws InvalidRouteException
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getReadOnlySettingsResponse(): mixed
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
     * @return ?Model
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
                $variables = Tailwind::$plugin?->tailwind->cssVariables();

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

                Tailwind::$plugin?->tailwind->enableRecording();
            },
        );
    }

    /**
     * Registers CP URL rules so the plugin-settings link routes to our
     * SettingsController rather than the default Craft plugin-settings
     * page wrapper. Both forms point at the same `edit` action so a CP
     * nav click and a typed-in `/tailwind/settings` URL land in the same
     * place.
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
