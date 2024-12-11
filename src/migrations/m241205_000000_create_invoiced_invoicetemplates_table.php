<?php

namespace nethaven\invoiced\migrations;

use Craft;
use craft\db\Migration;

class m241205_000000_create_invoiced_invoicetemplates_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%invoiced_invoicetemplates}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'html' => $this->text(),
            'css' => $this->text(),
            'sortOrder' => $this->integer(),
            'uid' => $this->uid(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'dateDeleted' => $this->dateTime(),
        ]);

        $this->createIndex(null, '{{%invoiced_invoicetemplates}}', 'handle', true);
        $this->createIndex(null, '{{%invoiced_invoicetemplates}}', 'uid', true);
    }

    public function safeDown()
    {
        $this->dropTable('{{%invoiced_invoicetemplates}}');
    }
}
