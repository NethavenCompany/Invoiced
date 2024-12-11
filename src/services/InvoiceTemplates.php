<?php
namespace nethaven\invoiced\services;

use nethaven\invoiced\invoiced;
use nethaven\invoiced\events\InvoiceTemplateEvent as TemplateEvent;
use nethaven\invoiced\models\InvoiceTemplate as TemplateModel;
use nethaven\invoiced\elements\InvoiceTemplate as TemplateElement;
use nethaven\invoiced\records\InvoiceTemplate as TemplateRecord;

use Craft;
use craft\base\Component;
use craft\base\MemoizableArray;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;

use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\web\ServerErrorHttpException;

use Throwable;

class InvoiceTemplates extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_INVOICE_TEMPLATE = 'beforeSaveInvoiceTemplate';
    public const EVENT_AFTER_SAVE_INVOICE_TEMPLATE = 'afterSaveInvoiceTemplate';
    public const EVENT_BEFORE_DELETE_INVOICE_TEMPLATE = 'beforeDeleteInvoiceTemplate';
    public const EVENT_BEFORE_APPLY_INVOICE_TEMPLATE_DELETE = 'beforeApplyInvoiceTemplateDelete';
    public const EVENT_AFTER_DELETE_INVOICE_TEMPLATE = 'afterDeleteInvoiceTemplate';
    public const CONFIG_TEMPLATES_KEY = 'invoiced.invoiceTemplates';


    // Private Properties
    // =========================================================================

    private ?MemoizableArray $_templates = null;


    // Public Methods
    // =========================================================================

    public function getAllTemplates(): array
    {
        return $this->_templates()->all();
    }

    public function getTemplateById(int $id): ?TemplateModel
    {
        return $this->_templates()->firstWhere('id', $id);
    }

    public function getTemplateByHandle(string $handle): ?TemplateModel
    {
        return $this->_templates()->firstWhere('handle', $handle, true);
    }

    public function getTemplateByUid(string $uid): ?TemplateModel
    {
        return $this->_templates()->firstWhere('uid', $uid, true);
    }

    public function reorderTemplates(array $ids): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $uidsByIds = Db::uidsByIds('{{%invoiced_invoicetemplates}}', $ids);

        foreach ($ids as $template => $templateId) {
            if (!empty($uidsByIds[$templateId])) {
                $templateUid = $uidsByIds[$templateId];
                $projectConfig->set(self::CONFIG_TEMPLATES_KEY . '.' . $templateUid . '.sortOrder', $template + 1);
            }
        }

        return true;
    }

    public function saveTemplate(TemplateModel $template, bool $runValidation = true): bool
    {
        $isNewTemplate = !(bool)$template->id;

        // Fire a 'beforeSaveInvoiceTemplate' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_INVOICE_TEMPLATE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_INVOICE_TEMPLATE, new TemplateEvent([
                'template' => $template,
                'isNew' => $isNewTemplate,
            ]));
        }

        if ($runValidation && !$template->validate()) {
            invoiced::error('Template not saved due to validation error.');

            return false;
        }

        if ($isNewTemplate) {
            $template->uid = StringHelper::UUID();

            $template->sortOrder = (new Query())
                ->from(['{{%invoiced_invoicetemplates}}'])
                ->max('[[sortOrder]]') + 1;
        } else if (!$template->uid) {
            $template->uid = Db::uidById('{{%invoiced_invoicetemplates}}', $template->id);
        }

        // Make sure no templates that are not archived share the handle
        $existingTemplate = $this->getTemplateByHandle($template->handle);

        if ($existingTemplate && (!$template->id || $template->id != $existingTemplate->id)) {
            $template->addError('handle', 'That handle is already in use');
            return false;
        }

        $configPath = self::CONFIG_TEMPLATES_KEY . '.' . $template->uid;
        Craft::$app->getProjectConfig()->set($configPath, $template->getConfig(), "Save the “{$template->handle}” invoice template");

        if ($isNewTemplate) {
            $template->id = Db::idByUid('{{%invoiced_invoicetemplates}}', $template->uid);
        }

        $errorJson = json_encode($template);
        $errorFilePath = $template->uid . '.json';
        file_put_contents($errorFilePath, $errorJson);

        return true;
    }


    public function handleChangedTemplate(ConfigEvent $event): void
    {
        $templateUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $templateRecord = $this->_getTemplateRecord($templateUid, true);
            $isNewTemplate = $templateRecord->getIsNewRecord();

            $templateRecord->name = $data['name'];
            $templateRecord->handle = $data['handle'];
            $templateRecord->html = $data['html'];
            $templateRecord->css = $data['css'];
            $templateRecord->sortOrder = $data['sortOrder'];
            $templateRecord->uid = $templateUid;

            if (!empty($data['fieldLayouts'])) {
                // Save the field layout
                $layout = FieldLayout::createFromConfig(reset($data['fieldLayouts']));
                $layout->id = $templateRecord->fieldLayoutId;
                $layout->type = TemplateElement::class;
                $layout->uid = key($data['fieldLayouts']);
                
                Craft::$app->getFields()->saveLayout($layout, false);
                
                $templateRecord->fieldLayoutId = $layout->id;
            } else if ($templateRecord->fieldLayoutId) {
                // Delete the main field layout
                Craft::$app->getFields()->deleteLayoutById($templateRecord->fieldLayoutId);
                $templateRecord->fieldLayoutId = null;
            }

            if ($wasTrashed = (bool)$templateRecord->dateDeleted) {
                $templateRecord->restore();
            } else {
                $templateRecord->save(false);
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Clear caches
        $this->_templates = null;

        // Fire an 'afterSaveInvoiceTemplate' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_INVOICE_TEMPLATE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_INVOICE_TEMPLATE, new TemplateEvent([
                'template' => $this->getTemplateById($templateRecord->id),
                'isNew' => $isNewTemplate,
            ]));
        }
    }


    public function deleteTemplateById(int $id): bool
    {
        $template = $this->getTemplateById($id);

        if (!$template) {
            return false;
        }

        return $this->deleteTemplate($template);
    }


    public function deleteTemplate(TemplateModel $template): bool
    {
        // Fire a 'beforeDeleteInvoiceTemplate' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_INVOICE_TEMPLATE)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_INVOICE_TEMPLATE, new TemplateEvent([
                'template' => $template,
            ]));
        }

        Craft::$app->getProjectConfig()->remove(self::CONFIG_TEMPLATES_KEY . '.' . $template->uid, "Delete invoice template “{$template->handle}”");
        return true;
    }


    public function handleDeletedTemplate(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $templateRecord = $this->_getTemplateRecord($uid);

        if ($templateRecord->getIsNewRecord()) {
            return;
        }

        $template = $this->getTemplateById($templateRecord->id);

        // Fire a 'beforeApplyInvoiceTemplateDelete' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_APPLY_INVOICE_TEMPLATE_DELETE)) {
            $this->trigger(self::EVENT_BEFORE_APPLY_INVOICE_TEMPLATE_DELETE, new TemplateEvent([
                'template' => $template,
            ]));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            Craft::$app->getDb()->createCommand()
                ->softDelete('{{%invoiced_invoicetemplates}}', ['id' => $templateRecord->id])
                ->execute();

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Fire an 'afterDeleteInvoiceTemplate' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_INVOICE_TEMPLATE)) {
            $this->trigger(self::EVENT_AFTER_DELETE_INVOICE_TEMPLATE, new TemplateEvent([
                'template' => $template,
            ]));
        }
    }


    // Private Methods
    // =========================================================================

    /**
     * Returns a memoizable array of all templates.
     *
     * @return MemoizableArray<TemplateModel>
     */
    private function _templates(): MemoizableArray
    {
        if (!isset($this->_templates)) {
            $templates = [];

            foreach ($this->_createTemplatesQuery()->all() as $result) {
                $templates[] = new TemplateModel($result);
            }

            $this->_templates = new MemoizableArray($templates);
        }

        return $this->_templates;
    }

    /**
     * Returns a Query object prepped for retrieving templates.
     *
     * @return Query
     */
    private function _createTemplatesQuery(): Query
    {
        $query = (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'html',
                'css',
                'sortOrder',
                'dateDeleted',
                'uid',
            ])
            ->from(['{{%invoiced_invoicetemplates}}'])
            ->where(['dateDeleted' => null])
            ->orderBy(['sortOrder' => SORT_ASC]);

        return $query;
    }

    private function _getTemplateRecord(string $uid, bool $withTrashed = false): TemplateRecord
    {
        $query = $withTrashed ? TemplateRecord::findWithTrashed() : TemplateRecord::find();
        $query->andWhere(['uid' => $uid]);

        return $query->one() ?? new TemplateRecord();
    }
}
