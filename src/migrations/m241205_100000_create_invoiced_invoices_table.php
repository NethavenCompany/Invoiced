<?php

namespace nethaven\invoiced\migrations;

use Craft;
use craft\db\Migration;

class m241205_100000_create_invoiced_invoices_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%invoiced_invoices}}', [
            'id' => $this->primaryKey(),
            'invoiceNumber' => $this->string()->notNull(),
            'invoiceDate' => $this->date()->notNull(),
            'expirationDate' => $this->date()->notNull(),
            'qty' => $this->integer()->notNull(),
            'description' => $this->string()->notNull(),
            'unitPrice' => $this->decimal(10, 2)->notNull(),
            'subtotal' => $this->decimal(10, 2)->notNull(),
            'vat' => $this->decimal(10, 2)->notNull(),
            'total' => $this->decimal(10, 2)->notNull(),
            'contact' => $this->string(),
            'address' => $this->text(),
            'bankDetails' => $this->text(),
            'uid' => $this->uid(),
            'templateId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'dateDeleted' => $this->dateTime(),
        ]);

        $this->createIndex(null, '{{%invoiced_invoices}}', 'invoiceNumber', true);
        $this->createIndex(null, '{{%invoiced_invoices}}', 'uid', true);

        // Add foreign key for templateId
        $this->addForeignKey(
            null,
            '{{%invoiced_invoices}}',
            'templateId',
            '{{%invoiced_invoicetemplates}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropTable('{{%invoiced_invoices}}');
    }
}
