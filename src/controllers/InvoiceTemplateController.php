<?php
namespace nethaven\invoiced\controllers;

use Craft;
use yii\web\Response;
use nethaven\invoiced\Invoiced;
use craft\web\Controller;
use nethaven\invoiced\models\InvoiceTemplate;
use nethaven\invoiced\elements\InvoiceTemplate as TemplateElement;

class InvoiceTemplateController extends Controller
{
    public function actionIndex(): Response
    {
        $invoiceTemplates = Invoiced::$plugin->getInvoiceTemplates()->getAllTemplates();

        return $this->renderTemplate('invoiced/settings/invoice-templates', compact('invoiceTemplates'));
    }

    public function actionSave(): void
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $template = new InvoiceTemplate();
        $template->id = $request->getBodyParam('id');
        $template->name = $request->getBodyParam('name');
        $template->handle = $request->getBodyParam('handle');
        $template->html = preg_replace('/\/index(?:\.html|\.twig)?$/', '', $request->getBodyParam('templateHtml'));
        $template->css = $request->getBodyParam('templateCss');

        if(Invoiced::$plugin->getInvoiceTemplates()->saveTemplate($template)) {
            Craft::$app->getSession()->setSuccess('Template saved');
        }   else {
            Craft::$app->getSession()->setError('Could not save the template.');
        }
        
        $this->redirect('invoiced/settings/invoice-templates');
    }
}
