<?php

namespace nethaven\invoiced\elements;

use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\web\CpScreenResponseBehavior;
use Dompdf\Dompdf;
use Exception;
use nethaven\invoiced\base\Table;
use nethaven\invoiced\elements\conditions\InvoiceCondition;
use nethaven\invoiced\elements\db\InvoiceQuery;
use nethaven\invoiced\Invoiced;
use nethaven\invoiced\records\Invoice as InvoiceRecord;
use yii\web\Response;

/**
 * Invoice element type
 */
class Invoice extends Element
{   
    // Properties
    // =========================================================================
    
    public ?int $templateId = null;
    public ?int $invoiceNumber = null;
    public ?string $invoiceDate = '';
    public ?string $expirationDate = '';

    public mixed $items = [];

    public ?int $subTotal = 0;
    public ?int $vat = 0;
    public ?int $total = 0;
    public ?string $phone = '';
    public ?string $email = '';

    public ?string $pdf = '';


    public static function displayName(): string
    {
        return Craft::t('invoiced', 'Invoice');
    }

    public static function refHandle(): ?string
    {
        return 'invoice';
    }

    public static function trackChanges(): bool
    {
        return true;
    }

    public static function hasContent(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return false;
    }

    public static function hasUris(): bool
    {
        return false;
    }

    public static function isLocalized(): bool
    {
        return false;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(InvoiceQuery::class, [static::class]);
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('invoiced', 'All invoices'),
            ]
        ];
    }

    protected static function defineActions(string $source): array
    {
        // List any bulk element actions here
        return [];
    }

    protected static function includeSetStatusAction(): bool
    {
        return true;
    }

    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => Craft::t('app', 'Invoice Number'),
                'orderBy' => 'invoiced_invoices.invoiceNumber',
                'attribute' => 'invoiceNumber',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Updated'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
        ];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        if ($attribute === 'pdf') {
            return '<a href="' . $this->getPdfUrl() . '" target="_blank">' . 'See PDF' . '</a>';
        }

        return parent::tableAttributeHtml($attribute);
    }


    protected static function defineTableAttributes(): array
    {
        return [
            'invoiceNumber' => ['label' => Craft::t('app', 'Invoice Number')],
            'invoiceDate' => ['label' => Craft::t('app', 'Invoice Date')],
            'expirationDate' => ['label' => Craft::t('app', 'Expiration Date')],
            'subTotal' => ['label' => Craft::t('app', '€/Subtotal')],
            'vat' => ['label' => Craft::t('app', 'VAT (%)')],
            'total' => ['label' => Craft::t('app', '€/Total')],
            'phone' => ['label' => Craft::t('app', 'Phone')],
            'email' => ['label' => Craft::t('app', 'Email')],
            'pdf' => ['label' => Craft::t('app', 'PDF'), 'type' => 'url'],
            'id' => ['label' => Craft::t('app', 'ID')],
            'uid' => ['label' => Craft::t('app', 'UID')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'pdf',
            'invoiceNumber',
            'invoiceDate',
            'expirationDate',
            'subTotal',
            'total',
        ];
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }

    public function getUriFormat(): ?string
    {
        // If invoices should have URLs, define their URI format here
        return null;
    }

    protected function previewTargets(): array
    {
        $previewTargets = [];
        $url = $this->getUrl();
        if ($url) {
            $previewTargets[] = [
                'label' => Craft::t('app', 'Primary {type} page', [
                    'type' => self::displayName(),
                ]),
                'url' => $url,
            ];
        }
        return $previewTargets;
    }

    protected function route(): array|string|null
    {
        // Define how invoices should be routed when their URLs are requested
        return [
            'templates/render',
            [
                'template' => 'site/template/path',
                'variables' => ['invoice' => $this],
            ]
        ];
    }

    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('viewInvoices');
    }

    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('saveInvoices');
    }

    public function canDelete(User $user): bool
    {

        if (parent::canSave($user)) {
            return true;
        }

        return $user->can('deleteInvoices');
    }

    public function canCreateDrafts(User $user): bool
    {
        return true;
    }

    protected function cpEditUrl(): ?string
    {
        return sprintf('invoices/edit/%s', $this->getCanonicalId());
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('invoices');
    }

    public function reapplyTemplate()
    {
        return $this->_createPdf();
    }

    public function getPdfUrl()
    {
        return Craft::getAlias('@web/invoiced/invoices/' . $this->invoiceNumber . '.pdf');
    }

    public function getPdfPath()
    {
        return Craft::getAlias('@webroot/invoiced/invoices/' . $this->invoiceNumber . '.pdf');
    }

    public function getPdfHtml($invoice = null)
    {
        $invoice = $invoice ?? $this;
        $template = Invoiced::$plugin->getInvoiceTemplates()->getTemplateById($invoice->templateId);
    
        $html = Craft::$app->getView()->renderTemplate($template->twigPath, [
            'invoice' => $invoice,
            'template' => $template
        ]);
        
        $cleanHtml = preg_replace('/<!\[CDATA\[(YII-BLOCK-HEAD|YII-BLOCK-BODY-BEGIN|YII-BLOCK-BODY-END)\]\]>/', '', $html);
        $cleanHtml = $cleanHtml . '<style>' . $template->css . '</style>';

        return $cleanHtml;
    }

    private function _createPdf()
    {
        $html = $this->getPdfHtml();

        $pdf = new Dompdf();
        $options = $pdf->getOptions(); 
        $options->setDefaultFont('sans-serif');
        $options->setIsRemoteEnabled(true);
        $pdf->setBasePath(Craft::getAlias('@webroot/'));

        $pdf->loadHtml($html);
        $pdf->render();

        $pdfPath = $this->getPdfPath();
        $directory = dirname($pdfPath);
        
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        if(file_put_contents($pdfPath, $pdf->output())) {
            return true;
        }
        
        return false;
    }

    private function _removePdf()
    {
        if(unlink($this->getPdfPath())) {
            return true;
        }
        
        return false;
    }

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            Db::upsert(Table::INVOICES, [
                'id' => $this->id,
                'templateId' => $this->templateId,
            ], [
                'invoiceNumber' => $this->invoiceNumber,
                'invoiceDate' => date('Y-m-d', strtotime($this->invoiceDate)),
                'expirationDate' => date('Y-m-d', strtotime($this->expirationDate)),
                'items' => json_encode($this->items) ?? '[]',
                'subTotal' => $this->subTotal,
                'vat' => $this->vat,
                'total' => $this->total,
                'phone' => $this->phone,
                'email' => $this->email,
                'pdf' => $this->getPdfUrl(),
            ]);
        }

        $this->_createPdf();

        parent::afterSave($isNew);
    }

    public function afterDelete(): void
    {
        if ($this->hardDelete) {
            $this->_removePdf();
        }
    }
}
