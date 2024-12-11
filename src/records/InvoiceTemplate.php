<?php
namespace nethaven\invoiced\records;

use nethaven\invoiced\helpers\Table;

use craft\db\ActiveRecord;
use craft\db\SoftDeleteTrait;
use craft\records\FieldLayout;

use yii\db\ActiveQueryInterface;

class InvoiceTemplate extends ActiveRecord
{
    // Traits
    // =========================================================================

    use SoftDeleteTrait;


    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return Table::INVOICED_INVOICE_TEMPLATES;
    }
}