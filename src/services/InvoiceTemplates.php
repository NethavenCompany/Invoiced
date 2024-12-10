<?php
namespace nethaven\invoiced\services;

use nethaven\invoiced\invoiced;
use nethaven\invoiced\events\InvoiceTemplateEvent;
use nethaven\invoiced\models\InvoiceTemplate;
use nethaven\invoiced\records\InvoiceTemplate as TemplateRecord;

use Craft;
use craft\base\Component;
use craft\base\MemoizableArray;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;

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

    /**
     * Returns all templates.
     *
     * @return InvoiceTemplate[]
     */
    public function getAllTemplates(): array
    {
        return $this->_templates()->all();
    }

    /**
     * Returns a template identified by its ID.
     *
     * @param int $id
     * @return InvoiceTemplate|null
     */
    public function getTemplateById(int $id): ?InvoiceTemplate
    {
        return $this->_templates()->firstWhere('id', $id);
    }

    /**
     * Returns a template identified by its handle.
     *
     * @param string $handle
     * @return InvoiceTemplate|null
     */
    public function getTemplateByHandle(string $handle): ?InvoiceTemplate
    {
        return $this->_templates()->firstWhere('handle', $handle, true);
    }

    /**
     * Returns a template identified by its UID.
     *
     * @param string $uid
     * @return InvoiceTemplate|null
     */
    public function getTemplateByUid(string $uid): ?InvoiceTemplate
    {
        return $this->_templates()->firstWhere('uid', $uid, true);
    }

    /**
     * Saves templates in a new order by the list of template IDs.
     *
     * @param int[] $ids
     * @return bool
     * @throws ErrorException
     * @throws Exception
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
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

    /**
     * Saves the template.
     *
     * @param InvoiceTemplate $template
     * @param bool $runValidation
     * @return bool
     * @throws ErrorException
     * @throws Exception
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function saveTemplate(InvoiceTemplate $template, bool $runValidation = true): bool
    {
        $isNewTemplate = !(bool)$template->id;

        // Fire a 'beforeSaveInvoiceTemplate' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_INVOICE_TEMPLATE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_INVOICE_TEMPLATE, new InvoiceTemplateEvent([
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

        // $configPath = self::CONFIG_TEMPLATES_KEY . '.' . $template->uid;
        // Craft::$app->getProjectConfig()->set($configPath, $template->getConfig(), "Save the “{$template->handle}” invoice template");

        if ($isNewTemplate) {
            $template->id = Db::idByUid('{{%invoiced_invoicetemplates}}', $template->uid);
        }

        $errorJson = json_encode($template);
        $errorFilePath = $template->uid . '.json';
        file_put_contents($errorFilePath, $errorJson);

        return true;
    }

    /**
     * Handle template change.
     *
     * @param ConfigEvent $event
     * @throws Throwable
     */
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
            $templateRecord->template = $data['template'];
            $templateRecord->sortOrder = $data['sortOrder'];
            $templateRecord->uid = $templateUid;

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
            $this->trigger(self::EVENT_AFTER_SAVE_INVOICE_TEMPLATE, new InvoiceTemplateEvent([
                'template' => $this->getTemplateById($templateRecord->id),
                'isNew' => $isNewTemplate,
            ]));
        }
    }

    /**
     * Delete a template by its id.
     *
     * @param int $id
     * @return bool
     * @throws Throwable
     */
    public function deleteTemplateById(int $id): bool
    {
        $template = $this->getTemplateById($id);

        if (!$template) {
            return false;
        }

        return $this->deleteTemplate($template);
    }

    /**
     * Deletes a invoice template.
     *
     * @param InvoiceTemplate $template The invoice template
     * @return bool Whether the invoice template was deleted successfully
     */
    public function deleteTemplate(InvoiceTemplate $template): bool
    {
        // Fire a 'beforeDeleteInvoiceTemplate' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_INVOICE_TEMPLATE)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_INVOICE_TEMPLATE, new InvoiceTemplateEvent([
                'template' => $template,
            ]));
        }

        Craft::$app->getProjectConfig()->remove(self::CONFIG_TEMPLATES_KEY . '.' . $template->uid, "Delete invoice template “{$template->handle}”");
        return true;
    }

    /**
     * Handle template being deleted
     *
     * @param ConfigEvent $event
     * @throws Throwable
     */
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
            $this->trigger(self::EVENT_BEFORE_APPLY_INVOICE_TEMPLATE_DELETE, new InvoiceTemplateEvent([
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
            $this->trigger(self::EVENT_AFTER_DELETE_INVOICE_TEMPLATE, new InvoiceTemplateEvent([
                'template' => $template,
            ]));
        }
    }


    // Private Methods
    // =========================================================================

    /**
     * Returns a memoizable array of all templates.
     *
     * @return MemoizableArray<InvoiceTemplate>
     */
    private function _templates(): MemoizableArray
    {
        if (!isset($this->_templates)) {
            $templates = [];

            foreach ($this->_createTemplatesQuery()->all() as $result) {
                $templates[] = new InvoiceTemplate($result);
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
                'template',
                'sortOrder',
                'dateDeleted',
                'uid',
            ])
            ->from(['{{%invoiced_invoicetemplates}}'])
            ->where(['dateDeleted' => null])
            ->orderBy(['sortOrder' => SORT_ASC]);

        return $query;
    }

    /**
     * Gets a template's record by uid.
     *
     * @param string $uid
     * @param bool $withTrashed Whether to include trashed templates in search
     * @return TemplateRecord
     */
    private function _getTemplateRecord(string $uid, bool $withTrashed = false): TemplateRecord
    {
        $query = $withTrashed ? TemplateRecord::findWithTrashed() : TemplateRecord::find();
        $query->andWhere(['uid' => $uid]);

        return $query->one() ?? new TemplateRecord();
    }
}
