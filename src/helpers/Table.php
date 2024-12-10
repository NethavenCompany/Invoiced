<?php
namespace nethaven\invoiced\helpers;

use craft\db\Table as CraftTable;

abstract class Table extends CraftTable
{
    // Constants
    // =========================================================================

    public const INVOICED_INVOICES = '{{%invoiced_invoices}}';
    public const INVOICED_INVOICE_TEMPLATES = '{{%invoiced_invoicetemplates}}';
}