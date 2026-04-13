<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\web\twig\variables\CraftVariable;
use craftpulse\tailwind\models\Settings;
use craftpulse\tailwind\services\TailwindService;
use craftpulse\tailwind\services\VersionDetector;
use craftpulse\tailwind\twig\TailwindTwigExtension;
use craftpulse\tailwind\variables\TailwindVariable;
use yii\base\Event;

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
 * @since 1.0.0
 */
class Plugin extends BasePlugin
{
    // =========================================================================
    // = Static Properties
    // =========================================================================

    /**
     * Static reference to the plugin instance.
     *
     * @var ?Plugin
     */
    public static ?Plugin $plugin = null;

    // =========================================================================
    // = Public Properties
    // =========================================================================

    /**
     * @var string The schema version for this plugin.
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool Whether the plugin has a settings page in the control panel.
     */
    public bool $hasCpSettings = true;

    // =========================================================================
    // = Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @return void
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->_registerServices();
        $this->_registerTwigExtension();
        $this->_registerVariables();
    }

    // =========================================================================
    // = Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @return ?Model The plugin settings model.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * Returns the rendered HTML for the plugin settings page.
     *
     * @return ?string The settings HTML.
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    protected function settingsHtml(): ?string
    {
        /** @var \craft\web\View $view */
        $view = Craft::$app->getView();

        /** @var \craft\web\Application $app */
        $app = Craft::$app;

        return $view->renderTemplate(
            'tailwind/settings',
            [
                'settings' => $this->getSettings(),
                'overrides' => $app->getConfig()->getConfigFromFile('tailwind'),
            ],
        );
    }

    // =========================================================================
    // = Private Methods
    // =========================================================================

    /**
     * Registers the plugin's service components.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _registerServices(): void
    {
        $this->setComponents([
            'tailwind' => TailwindService::class,
            'versionDetector' => VersionDetector::class,
        ]);
    }

    /**
     * Registers the Twig extension for template use.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _registerTwigExtension(): void
    {
        /** @var \craft\web\View $view */
        $view = Craft::$app->getView();
        $view->registerTwigExtension(
            new TailwindTwigExtension(),
        );
    }

    /**
     * Registers template variables under `craft.tailwind`.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 1.0.0
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
}
