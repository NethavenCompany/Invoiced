<?php
namespace nethaven\invoiced\models;

use nethaven\invoiced\elements\Invoice as InvoiceElement;
use nethaven\invoiced\Invoiced;

use Craft;
use craft\base\Model;
use craft\elements\Entry;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;

use Twig\Error\SyntaxError;
use Twig\Error\LoaderError;

use DateTime;

class InvoiceSettings extends Model
{
    // Properties
    // =========================================================================

    // Settings - Privacy
    public bool $collectIp = false;
    public bool $collectUser = false;
    public ?string $dataRetention = null;
    public ?string $dataRetentionValue = null;


    // Private Properties
    // =========================================================================

    private ?InvoiceElement $_invoice = null;


    // Public Methods
    // =========================================================================

    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    /** @inheritDoc */
    public function init(): void
    {
        parent::init();

        /** @var Settings $settings */
        $settings = Invoiced::$plugin->getSettings();
    }

    public function getInvoice(): ?InvoiceElement
    {
        return $this->_invoice;
    }

    public function setInvoice($value): void
    {
        $this->_invoice = $value;
    }

}
