<?php
namespace nethaven\invoiced\base;

use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;

use yii\base\Event;

trait Routes
{
    // Private Methods
    // =========================================================================

    /**
     * Control Panel routes.
     *
     * @return void
     */
    public function _registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['invoiced'] = ['template' =>'invoiced/index'];
                $event->rules['invoiced/invoices'] = [ 'template' => 'invoiced/invoices'];
                $event->rules['invoiced/invoices/new'] = [ 'template' => 'invoiced/invoices/_edit'];

                $event->rules['invoiced/settings/invoice-templates/new'] = [ 'template' => 'invoiced/settings/invoice-templates/_edit'];
                $event->rules['invoiced/settings/invoice-templates/edit/<id:\d+>'] = [ 'template' => 'invoiced/settings/invoice-templates/_edit'];

                $event->rules['invoiced/invoice-template/save'] = 'invoiced/invoice-template/save';
            }
        );
    }
}