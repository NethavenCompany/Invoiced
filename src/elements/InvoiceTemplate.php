<?php
namespace nethaven\invoiced\elements;

use nethaven\invoiced\elements\db\InvoiceTemplateQuery;
use nethaven\invoiced\Invoiced;
use nethaven\invoiced\records\InvoiceTemplate as InoiceTemplateRecord;
use nethaven\invoiced\models\InvoiceSettings;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\behaviors\FieldLayoutBehavior;
use craft\models\FieldLayout as CraftFieldLayout;

class InvoiceTemplate extends Element
{
    // Constants
    // =========================================================================

    public const EVENT_MODIFY_HTML_TAG = 'modifyHtmlTag';


    // Static Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return 'Invoice';
    }

    /**
     * @inheritDoc
     */
    public static function refHandle(): ?string
    {
        return 'invoice';
    }

    /**
     * @inheritDoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public static function find(): InvoiceTemplateQuery
    {
        return new InvoiceTemplateQuery(static::class);
    }

    /**
     * @inheritDoc
     */
    public static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => 'All invoices',
                'defaultSort' => ['name', 'desc'],
            ],
        ];

        return $sources;
    }

    /**
     * @inheritDoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'name' => ['label' => Craft::t('app', 'Name')],
            'id' => ['label' => Craft::t('app', 'ID')],
            'handle' => ['label' => Craft::t('app', 'Handle')],
            'templateHtml' => ['label' => Craft::t('app', 'Template')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
        ];
    }

    /**
     * @inheritDoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];
        $attributes[] = 'name';
        $attributes[] = 'handle';
        $attributes[] = 'template';
        $attributes[] = 'uid';
        $attributes[] = 'sortOrder';
        $attributes[] = 'dateCreated';
        $attributes[] = 'dateUpdated';
        $attributes[] = 'dateDeleted';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['name', 'handle'];
    }

    /**
     * @inheritDoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'name' => 'app', 'Name',
            'handle' => 'app', 'Handle',
            [
                'label' => 'app', 'Date Created',
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
            ],
            [
                'label' => 'app', 'Date Updated',
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
            ],
            [
                'label' => 'app', 'ID',
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
        ];
    }


    //  Properties
    // =========================================================================

    public ?string $handle = null;
    public ?string $name = '';
    public ?string $html = '';
    public ?string $css = '';
    public ?string $fieldContentTable = null;
    public ?int $defaultStatusId = null;
    public string $dataRetention = 'forever';
    public ?string $dataRetentionValue = null;
    public string $userDeletedAction = 'retain';
    public string $fileUploadsAction = 'retain';
    public ?InvoiceSettings $settings = null;

    private ?InvoiceTemplate $_template = null;
    private ?Entry $_submitActionEntry = null;
    private ?string $_invoiceId = null;
    private ?string $_redirectUrl = null;


    // Public Methods
    // =========================================================================

    public function __construct($config = [])
    {
        // Config normalization
        if (array_key_exists('settings', $config)) {
            if (is_string($config['settings'])) {
                $config['settings'] = new InvoiceSettings(Json::decodeIfJson($config['settings']));
            }

            if (!($config['settings'] instanceof InvoiceSettings)) {
                $config['settings'] = new InvoiceSettings();
            }
        } else {
            $config['settings'] = new InvoiceSettings();
        }

        parent::__construct($config);
    }

    /** @inheritDoc */
    public function init(): void
    {
        parent::init();
    }

    /** @inheritDoc */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['fieldLayout'] = [
            'class' => FieldLayoutBehavior::class,
            'elementType' => static::class,
        ];

        return $behaviors;
    }

    /** @inheritdoc */
    public function canView(User $user): bool
    {
        return true;
    }

    /** @inheritdoc */
    public function canDelete(User $user): bool
    {
        if (parent::canDelete($user)) {
            return true;
        }

        return true;
    }

    /** @inheritdoc */
    public function canDuplicate(User $user): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("invoiced/invoices/edit/{$this->id}");
    }

    public function getInvoiceId(): string
    {
        if ($this->_invoiceId) {
            return $this->_invoiceId;
        }

        // Provide a unique ID for this field, used as a namespace for IDs of elements in the invoice
        return $this->_invoiceId = 'iui-' . $this->handle . '-' . StringHelper::randomString(6);
    }

    public function setInvoiceId($value): void
    {
        $this->_invoiceId = $value;
    }


    // Protected methods
    // =========================================================================

    /** * @inheritDoc */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['name'], 'string', 'max' => 255];
        $rules[] = [['handle', 'handle'], 'required'];

        return $rules;
    }

    // Private methods
    // =========================================================================

}