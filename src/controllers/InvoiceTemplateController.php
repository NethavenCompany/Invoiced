<?php
namespace nethaven\invoiced\controllers;

use Craft;
use nethaven\invoiced\Invoiced;
use craft\web\Controller;
use nethaven\invoiced\models\InvoiceTemplate;
use nethaven\invoiced\elements\InvoiceTemplate as TemplateElement;

class InvoiceTemplateController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    public function actionSave(): void
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $template = new InvoiceTemplate();
        $template->id = $request->getBodyParam('id');
        $template->name = $request->getBodyParam('name');
        $template->handle = $request->getBodyParam('handle');
        $template->template = preg_replace('/\/index(?:\.html|\.twig)?$/', '', $request->getBodyParam('templateHtml'));

        if(Invoiced::$plugin->getInvoiceTemplates()->saveTemplate($template)) {
            Craft::$app->getSession()->setSuccess('Template saved');
        }   else {
            Craft::$app->getSession()->setError('Could not save the template.');
        }
        
        $this->redirect('invoiced/settings/invoice-templates');

    }
}
