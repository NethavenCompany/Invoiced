<?php
namespace nethaven\invoiced\elements\db;

use nethaven\invoiced\models\InvoiceTemplate;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class InvoiceTemplateQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    public mixed $handle = null;
    public mixed $templateId = null;

    protected array $defaultOrderBy = ['elements.dateCreated' => SORT_DESC];


    // Public Methods
    // =========================================================================


    // Protected Methods
    // =========================================================================

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('invoiced_invoicetemplates');

        $this->query->select([
            'invoiced_invoicetemplates.id',
            'invoiced_invoicetemplates.handle',
            'invoiced_invoicetemplates.name',
        ]);

        if ($this->handle) {
            $this->subQuery->andWhere(Db::parseParam('invoiced_invoices.handle', $this->handle));
        }

        if ($this->templateId) {
            $this->subQuery->andWhere(Db::parseParam('invoiced_invoices.templateId', $this->templateId));
        }

        return parent::beforePrepare();
    }
}
