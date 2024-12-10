<?php
namespace nethaven\invoiced;

use Craft;
use craft\base\Plugin;
use craft\base\Event as Event;
use craft\web\twig\variables\CraftVariable;

use nethaven\invoiced\base\PluginTrait;
use nethaven\invoiced\base\Routes;
use nethaven\invoiced\models\Settings;


class Invoiced extends Plugin
{
    // Properties
    // =========================================================================

    public static Invoiced $plugin;
    public bool $hasCpSection = true;
    public string $schemaVersion = '1.0.0';
    public string $pluginName = "Invoiced";


    // Traits
    // =========================================================================

    use PluginTrait;
    use Routes;


    // Initialize
    // =========================================================================

    public static function config(): array
    {
        return [
            'components' => [

            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        Craft::$app->onInit(function () {
            $this->_registerVariables();
            $this->_registerComponents();
            $this->_registerCpRoutes();
        });
        
    }


    // Protected / Private Methods
    // =========================================================================

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    // Public Methods
    // =========================================================================

    public function getCpNavItem(): ?array
    {
        $nav = parent::getCpNavItem();

        $nav['label'] = $this->getPluginName();

        $nav['subnav']['invoices'] = [
            'label' => 'Invoices',
            'url' => 'invoiced/invoices',
        ];

        $nav['subnav']['settings'] = [
            'label' => 'Settings',
            'url' => 'invoiced/settings',
        ];

        return $nav;
    }

    public function getPluginName(): string
    {
        return Invoiced::$plugin->getSettings()->pluginName;
    }
}