<?php
namespace nethaven\invoiced\helpers;

use nethaven\invoiced\Invoiced;

use Craft;
use craft\db\Query;

class ProjectConfigHelper
{
    // Static Methods
    // =========================================================================

    public static function rebuildProjectConfig(): array
    {
        $configData = [];

        $configData['templates'] = self::_getInvoiceTemplatesData();

        return array_filter($configData);
    }

    
    // Private Methods
    // =========================================================================

    private static function _getInvoiceTemplatesData(): array
    {
        $data = [];

        foreach (Invoiced::$plugin->getInvoiceTemplates()->getAllTemplates() as $template) {
            $data[$template->uid] = $template->getConfig();
        }

        return $data;
    }


}