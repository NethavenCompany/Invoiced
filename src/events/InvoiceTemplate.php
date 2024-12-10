<?php
namespace nethaven\invoiced\events;

use nethaven\invoiced\models\InvoiceTemplate as InvoiceTemplateModel;

use yii\base\Event;

class InvoiceTemplateEvent extends Event
{
    // Properties
    // =========================================================================

    public InvoiceTemplateModel|null $template = null;
    public bool $isNew = false;
    
}