<?php
namespace nethaven\invoiced\events;

use nethaven\invoiced\models\InvoiceTemplate as TemplateModel;

use yii\base\Event;

class InvoiceTemplateEvent extends Event
{
    // Properties
    // =========================================================================

    public TemplateModel|null $template = null;
    public bool $isNew = false;
    
}