<?php
namespace nethaven\invoiced\models;

use craft\behaviors\FieldLayoutBehavior;
use craft\models\FieldLayout;
use craft\helpers\UrlHelper;

use nethaven\invoiced\records\InvoiceTemplate as InvoiceTemplateRecord;
use nethaven\invoiced\elements\InvoiceTemplate as InvoiceTemplateElement;

class InvoiceTemplate extends BaseTemplate
{
    // Properties
    // =========================================================================

    public bool $hasSingleTemplate = true;

    private ?FieldLayout $_fieldLayout = null;

    // Public Methods
    // =========================================================================
    /**
     * @inheritDoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = ['template', 'required'];

        return $rules;
    }

    /**
     * Returns the CP URL for editing the template.
     *
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('invoiced/settings/invoice-templates/edit/' . $this->id);
    }

    public function getFieldLayout(): FieldLayout
    {
        if ($this->_fieldLayout !== null) {
            return $this->_fieldLayout;
        }

        /** @var FieldLayoutBehavior $behavior */
        $behavior = $this->getBehavior('fieldLayout');

        return $this->_fieldLayout = $behavior->getFieldLayout();
    }

    public function setFieldLayout(FieldLayout $fieldLayout): void
    {
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $this->getBehavior('fieldLayout');
        $behavior->setFieldLayout($fieldLayout);

        $this->_fieldLayout = $fieldLayout;
    }

    /**
     * Returns the template’s config.
     *
     * @return array
     */
    public function getConfig(): array
    {
        $config = [
            'name' => $this->name,
            'handle' => $this->handle,
            'template' => $this->template,
            'sortOrder' => $this->sortOrder,
        ];

        if (($fieldLayout = $this->getFieldLayout()) && ($fieldLayoutConfig = $fieldLayout->getConfig())) {
            $config['fieldLayouts'] = [
                $fieldLayout->uid => $fieldLayoutConfig,
            ];
        }

        return $config;
    }

    // Protected Methods
    // =========================================================================

    protected function getRecordClass(): string
    {
        return InvoiceTemplateRecord::class;
    }

    protected function defineBehaviors(): array
    {
        $behaviors = parent::defineBehaviors();

        $behaviors['fieldLayout'] = [
            'class' => FieldLayoutBehavior::class,
            'elementType' => InvoiceTemplateElement::class,
        ];

        return $behaviors;
    }
}
