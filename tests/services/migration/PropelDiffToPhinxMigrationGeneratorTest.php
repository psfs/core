<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use Propel\Generator\Model\Column as PropelColumn;
use Propel\Generator\Model\ColumnDefaultValue;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\Diff\DatabaseDiff;
use Propel\Generator\Model\Diff\ColumnDiff;
use Propel\Generator\Model\Diff\TableDiff;
use Propel\Generator\Model\Index;
use Propel\Generator\Model\Table as PropelTable;
use Propel\Generator\Model\Unique;
use PSFS\services\migration\PropelDiffToPhinxMigrationGenerator;

class PropelDiffToPhinxMigrationGeneratorTest extends TestCase
{
    public function testNonDatabaseDiffIsRejectedWithExpectedType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(DatabaseDiff::class);

        (new PropelDiffToPhinxMigrationGenerator())->translate(new \stdClass());
    }

    public function testAmbiguousTableRenameMustBeResolvedBeforeTranslation(): void
    {
        $diff = new DatabaseDiff();
        $diff->addPossibleRenamedTable('old_users', 'users');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('old_users');

        (new PropelDiffToPhinxMigrationGenerator())->translate($diff);
    }

    public function testAddedPropelTableBecomesDeclarativePhinxUpAndDown(): void
    {
        $table = new PropelTable('users');

        $id = new PropelColumn('id', 'INTEGER');
        $id->setPrimaryKey(true);
        $id->setAutoIncrement(true);
        $id->setNotNull(true);
        $table->addColumn($id);

        $displayName = new PropelColumn('display_name', 'VARCHAR', 120);
        $displayName->setNotNull(true);
        $displayName->setDefaultValue("Fran's");
        $table->addColumn($displayName);

        $amount = new PropelColumn('amount', 'DECIMAL', 10);
        $amount->setScale(2);
        $table->addColumn($amount);

        $diff = new DatabaseDiff();
        $diff->addAddedTable('users', $table);

        $migration = (new PropelDiffToPhinxMigrationGenerator())->translate($diff);
        $up = implode("\n", $migration['up']);
        $down = implode("\n", $migration['down']);

        $this->assertStringContainsString("\$this->table('users', ['id' => false, 'primary_key' => ['id']])", $up);
        $this->assertStringContainsString("->addColumn('id', 'integer', ['identity' => true, 'null' => false])", $up);
        $this->assertStringContainsString("->addColumn('display_name', 'string', ['default' => 'Fran\\'s', 'limit' => 120, 'null' => false])", $up);
        $this->assertStringContainsString("->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])", $up);
        $this->assertStringContainsString('->create()', $up);
        $this->assertStringNotContainsString('$this->execute(', $up);
        $this->assertStringContainsString("\$this->table('users')->drop()->update()", $down);
        $this->assertStringNotContainsString('$this->execute(', $down);
    }

    public function testCreatedTablePreservesCommentsAndNamedIndexes(): void
    {
        $table = new PropelTable('users');
        $table->setDescription('Application users');
        $email = new PropelColumn('email', 'VARCHAR', 255);
        $email->setDescription('Primary contact address');
        $table->addColumn($email);

        $index = new Index();
        $index->setName('users_email_idx');
        $index->addColumn($email);
        $table->addIndex($index);

        $unique = new Unique();
        $unique->setName('users_email_unique');
        $unique->addColumn($email);
        $table->addUnique($unique);

        $diff = new DatabaseDiff();
        $diff->addAddedTable('users', $table);
        $migration = (new PropelDiffToPhinxMigrationGenerator())->translate($diff);
        $up = implode("\n", $migration['up']);

        $this->assertStringContainsString("'comment' => 'Application users'", $up);
        $this->assertStringContainsString("'comment' => 'Primary contact address'", $up);
        $this->assertStringContainsString("->addIndex(['email'], ['name' => 'users_email_idx'])", $up);
        $this->assertStringContainsString("->addIndex(['email'], ['name' => 'users_email_unique', 'unique' => true])", $up);
    }

    public function testUnsupportedPropelColumnTypeFailsWithColumnContext(): void
    {
        $table = new PropelTable('user_settings');
        $value = new PropelColumn('value', 'OBJECT');
        $table->addColumn($value);

        $diff = new DatabaseDiff();
        $diff->addAddedTable('user_settings', $table);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('VALUE');

        (new PropelDiffToPhinxMigrationGenerator())->translate($diff);
    }

    public function testExpressionDefaultIsRenderedAsPhinxLiteral(): void
    {
        $table = new PropelTable('events');
        $createdAt = new PropelColumn('created_at', 'TIMESTAMP');
        $createdAt->setDefaultValue('CURRENT_TIMESTAMP');
        $createdAt->getDefaultValue()->setType(ColumnDefaultValue::TYPE_EXPR);
        $table->addColumn($createdAt);

        $diff = new DatabaseDiff();
        $diff->addAddedTable('events', $table);

        $migration = (new PropelDiffToPhinxMigrationGenerator())->translate($diff);
        $up = implode("\n", $migration['up']);

        $this->assertStringContainsString("['default' => new \\Phinx\\Util\\Literal('CURRENT_TIMESTAMP')", $up);
    }

    public function testModifiedTableDiffMapsColumnOperationsAndIndexesAndReverse(): void
    {
        $old = new PropelTable('users');
        $old->addColumn(new PropelColumn('legacy', 'VARCHAR', 40));
        $old->addColumn(new PropelColumn('nickname', 'VARCHAR', 40));
        $old->addColumn(new PropelColumn('status', 'VARCHAR', 20));

        $new = new PropelTable('users');
        $new->addColumn(new PropelColumn('nickname', 'VARCHAR', 80));
        $new->addColumn(new PropelColumn('state', 'VARCHAR', 20));
        $new->addColumn(new PropelColumn('email', 'VARCHAR', 255));

        $diffTable = new TableDiff($old, $new);
        $diffTable->addRemovedColumn('legacy', $old->getColumn('legacy'));
        $diffTable->addAddedColumn('email', $new->getColumn('email'));
        $diffTable->addRenamedColumn($old->getColumn('status'), $new->getColumn('state'));
        $diffTable->addModifiedColumn('nickname', new ColumnDiff($old->getColumn('nickname'), $new->getColumn('nickname')));
        $oldIndex = new Index();
        $oldIndex->setName('users_nickname_idx');
        $oldIndex->addColumn($old->getColumn('nickname'));
        $newIndex = new Unique();
        $newIndex->setName('users_nickname_idx');
        $newIndex->addColumn($new->getColumn('nickname'));
        $diffTable->addModifiedIndex('users_nickname_idx', $oldIndex, $newIndex);
        $index = new Index();
        $index->setName('users_email_idx');
        $index->addColumn($new->getColumn('email'));
        $index->setTable($new);
        $diffTable->addAddedIndex('users_email_idx', $index);

        $diff = new DatabaseDiff();
        $diff->addModifiedTable('users', $diffTable);
        $migration = (new PropelDiffToPhinxMigrationGenerator())->translate($diff);
        $up = implode("\n", $migration['up']);
        $down = implode("\n", $migration['down']);

        $this->assertStringContainsString("->removeColumn('legacy')->update()", $up);
        $this->assertStringContainsString("->renameColumn('status', 'state')->update()", $up);
        $this->assertStringContainsString("->addColumn('email', 'string', ['limit' => 255, 'null' => true])->update()", $up);
        $this->assertStringContainsString("->changeColumn('nickname', 'string', ['limit' => 80, 'null' => true])->update()", $up);
        $this->assertStringContainsString("->addIndex(['email'], ['name' => 'users_email_idx'])->update()", $up);
        $this->assertStringContainsString(
            "->addIndex(['nickname'], ['name' => 'users_nickname_idx', 'unique' => true])->update()",
            $up
        );
        $this->assertStringContainsString("->addColumn('legacy', 'string', ['limit' => 40, 'null' => true])->update()", $down);
        $this->assertStringContainsString("->renameColumn('state', 'status')->update()", $down);
        $this->assertStringContainsString("->changeColumn('nickname', 'string', ['limit' => 40, 'null' => true])->update()", $down);
        $this->assertStringContainsString("->removeColumn('email')->update()", $down);
        $this->assertStringContainsString("->removeIndexByName('users_email_idx')->update()", $down);
        $this->assertStringContainsString(
            "->addIndex(['nickname'], ['name' => 'users_nickname_idx'])->update()",
            $down
        );
    }

    public function testCreateTableForeignKeysAreAddedAfterTablesExist(): void
    {
        $users = new PropelTable('users');
        $users->addColumn(new PropelColumn('id', 'INTEGER'));
        $users->getColumn('id')->setPrimaryKey(true);
        $posts = new PropelTable('posts');
        $posts->addColumn(new PropelColumn('id', 'INTEGER'));
        $posts->getColumn('id')->setPrimaryKey(true);
        $posts->addColumn(new PropelColumn('user_id', 'INTEGER'));

        $foreignKey = new ForeignKey('posts_user_fk');
        $foreignKey->setForeignTableCommonName('users');
        $foreignKey->addReference('user_id', 'id');
        $foreignKey->setOnDelete('CASCADE');
        $posts->addForeignKey($foreignKey);

        $diff = new DatabaseDiff();
        $diff->addAddedTable('posts', $posts);
        $diff->addAddedTable('users', $users);

        $migration = (new PropelDiffToPhinxMigrationGenerator())->translate($diff);
        $up = implode("\n", $migration['up']);

        $createPosts = strpos($up, "\$this->table('posts'");
        $createUsers = strpos($up, "\$this->table('users'");
        $addForeignKey = strpos($up, "\$this->table('posts')->addForeignKey(['user_id'], 'users', ['id'], ['constraint' => 'posts_user_fk', 'delete' => 'CASCADE'])->update()");
        $this->assertNotFalse($createPosts);
        $this->assertNotFalse($createUsers);
        $this->assertNotFalse($addForeignKey);
        $this->assertGreaterThan($createPosts, $addForeignKey);
        $this->assertGreaterThan($createUsers, $addForeignKey);
    }

    public function testForeignKeyUpdateActionIsRenderedDeclaratively(): void
    {
        $orders = new PropelTable('orders');
        $orders->addColumn(new PropelColumn('customer_id', 'INTEGER'));
        $foreignKey = new ForeignKey('orders_customer_fk');
        $foreignKey->setForeignTableCommonName('customers');
        $foreignKey->addReference('customer_id', 'id');
        $foreignKey->setOnUpdate('CASCADE');
        $orders->addForeignKey($foreignKey);
        $diff = new DatabaseDiff();
        $diff->addAddedTable('orders', $orders);

        $migration = (new PropelDiffToPhinxMigrationGenerator())->translate($diff);

        $this->assertStringContainsString(
            "->addForeignKey(['customer_id'], 'customers', ['id'], ['constraint' => 'orders_customer_fk', 'update' => 'CASCADE'])->update()",
            implode("\n", $migration['up'])
        );
    }

    public function testExpressionLikeStringDefaultRemainsAQuotedValueAndIdentifiersAreEscaped(): void
    {
        $table = new PropelTable("user's settings");
        $literal = new PropelColumn("current'time", 'VARCHAR', 32);
        $literal->setDefaultValue('CURRENT_TIMESTAMP');
        $table->addColumn($literal);

        $diff = new DatabaseDiff();
        $diff->addAddedTable($table->getName(), $table);
        $migration = (new PropelDiffToPhinxMigrationGenerator())->translate($diff);
        $up = implode("\n", $migration['up']);

        $this->assertStringContainsString("\$this->table('user\\'s settings'", $up);
        $this->assertStringContainsString("->addColumn('current\\'time', 'string', ['default' => 'CURRENT_TIMESTAMP', 'limit' => 32, 'null' => true])", $up);
    }

    public function testModifiedForeignKeyIsDroppedAndRecreatedDeclaratively(): void
    {
        $old = new PropelTable('posts');
        $old->addColumn(new PropelColumn('user_id', 'INTEGER'));
        $new = new PropelTable('posts');
        $new->addColumn(new PropelColumn('user_id', 'INTEGER'));
        $diffTable = new TableDiff($old, $new);

        $from = new ForeignKey('posts_user_fk');
        $from->setForeignTableCommonName('users');
        $from->addReference('user_id', 'id');
        $old->addForeignKey($from);
        $to = new ForeignKey('posts_user_fk');
        $to->setForeignTableCommonName('accounts');
        $to->addReference('user_id', 'id');
        $new->addForeignKey($to);
        $diffTable->addModifiedFk('posts_user_fk', $from, $to);

        $diff = new DatabaseDiff();
        $diff->addModifiedTable('posts', $diffTable);
        $migration = (new PropelDiffToPhinxMigrationGenerator())->translate($diff);
        $up = implode("\n", $migration['up']);

        $this->assertStringContainsString("->dropForeignKey(['user_id'], 'posts_user_fk')->update()", $up);
        $this->assertStringContainsString("->addForeignKey(['user_id'], 'accounts', ['id'], ['constraint' => 'posts_user_fk'])->update()", $up);
        $this->assertStringNotContainsString('$this->execute(', $up);
    }

    public function testPrimaryKeyChangeUsesPhinxTableOperationAndReverses(): void
    {
        $old = new PropelTable('records');
        $oldId = new PropelColumn('id', 'INTEGER');
        $oldId->setPrimaryKey(true);
        $old->addColumn($oldId);
        $new = new PropelTable('records');
        $newId = new PropelColumn('record_id', 'INTEGER');
        $newId->setPrimaryKey(true);
        $new->addColumn($newId);

        $tableDiff = new TableDiff($old, $new);
        $tableDiff->addRemovedColumn('id', $oldId);
        $tableDiff->addAddedColumn('record_id', $newId);
        $tableDiff->addRemovedPkColumn('id', $oldId);
        $tableDiff->addAddedPkColumn('record_id', $newId);
        $diff = new DatabaseDiff();
        $diff->addModifiedTable('records', $tableDiff);

        $migration = (new PropelDiffToPhinxMigrationGenerator())->translate($diff);
        $up = implode("\n", $migration['up']);
        $down = implode("\n", $migration['down']);
        $this->assertStringContainsString("->changePrimaryKey(['record_id'])->update()", $up);
        $this->assertStringContainsString("->changePrimaryKey(['id'])->update()", $down);
        $this->assertLessThan(
            strpos($up, "->removeColumn('id')->update()"),
            strpos($up, "->changePrimaryKey(['record_id'])->update()")
        );
        $this->assertLessThan(
            strpos($down, "->removeColumn('record_id')->update()"),
            strpos($down, "->changePrimaryKey(['id'])->update()")
        );
    }

    public function testTableRenameIsDeclarativeAndReversible(): void
    {
        $diff = new DatabaseDiff();
        $diff->addRenamedTable('old table', 'new table');

        $migration = (new PropelDiffToPhinxMigrationGenerator())->translate($diff);

        $this->assertSame(["\$this->table('old table')->rename('new table')->update();"], $migration['up']);
        $this->assertSame(["\$this->table('new table')->rename('old table')->update();"], $migration['down']);
    }

    public function testForeignKeysAreDroppedBeforeReferencedTables(): void
    {
        $sourceBefore = new PropelTable('posts');
        $sourceBefore->addColumn(new PropelColumn('user_id', 'INTEGER'));
        $sourceAfter = new PropelTable('posts');
        $sourceAfter->addColumn(new PropelColumn('user_id', 'INTEGER'));
        $fk = new ForeignKey('posts_user_fk');
        $fk->setForeignTableCommonName('users');
        $fk->addReference('user_id', 'id');
        $sourceBefore->addForeignKey($fk);

        $sourceDiff = new TableDiff($sourceBefore, $sourceAfter);
        $sourceDiff->addRemovedFk('posts_user_fk', $fk);
        $removedUsers = new PropelTable('users');
        $removedUsers->addColumn(new PropelColumn('id', 'INTEGER'));

        $diff = new DatabaseDiff();
        $diff->addModifiedTable('posts', $sourceDiff);
        $diff->addRemovedTable('users', $removedUsers);
        $migration = (new PropelDiffToPhinxMigrationGenerator())->translate($diff);
        $up = implode("\n", $migration['up']);

        $dropForeignKey = strpos($up, "\$this->table('posts')->dropForeignKey(['user_id'], 'posts_user_fk')->update()");
        $dropReferencedTable = strpos($up, "\$this->table('users')->drop()->update()");
        $this->assertNotFalse($dropForeignKey);
        $this->assertNotFalse($dropReferencedTable);
        $this->assertLessThan($dropReferencedTable, $dropForeignKey);
        $this->assertSame(1, substr_count($up, "dropForeignKey(['user_id'], 'posts_user_fk')"));
    }

    public function testTableOperationsAreDeterministicRegardlessOfDiffInsertionOrder(): void
    {
        $users = new PropelTable('users');
        $users->addColumn(new PropelColumn('id', 'INTEGER'));
        $posts = new PropelTable('posts');
        $posts->addColumn(new PropelColumn('id', 'INTEGER'));

        $forward = new DatabaseDiff();
        $forward->addAddedTable('users', $users);
        $forward->addAddedTable('posts', $posts);
        $reverse = new DatabaseDiff();
        $reverse->addAddedTable('posts', $posts);
        $reverse->addAddedTable('users', $users);

        $generator = new PropelDiffToPhinxMigrationGenerator();
        $this->assertSame($generator->translate($forward), $generator->translate($reverse));
    }

    public function testUnknownTableDiffNodeIsRejectedWithTableContext(): void
    {
        $diff = new DatabaseDiff();
        $diff->setModifiedTables(['users' => new \stdClass()]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('users');
        (new PropelDiffToPhinxMigrationGenerator())->translate($diff);
    }

    public function testUnsupportedModifiedColumnNodeIsRejectedWithTableAndColumnContext(): void
    {
        $old = new PropelTable('users');
        $new = new PropelTable('users');
        $tableDiff = new TableDiff($old, $new);
        $tableDiff->setModifiedColumns([
            'email' => new ColumnDiff(new PropelColumn('email', 'VARCHAR'), null),
        ]);
        $diff = new DatabaseDiff();
        $diff->addModifiedTable('users', $tableDiff);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('users.email');

        (new PropelDiffToPhinxMigrationGenerator())->translate($diff);
    }

    public function testPropelEnumMapsToNumericStorageExpectedByPropel(): void
    {
        $table = new PropelTable('tests');
        $type = new PropelColumn('type', 'ENUM');
        $type->setValueSet(['DEV', 'TEST', 'QA']);
        $table->addColumn($type);
        $flags = new PropelColumn('flags', 'SET');
        $flags->setValueSet(['READ', 'WRITE']);
        $table->addColumn($flags);
        $diff = new DatabaseDiff();
        $diff->addAddedTable('tests', $table);

        $migration = (new PropelDiffToPhinxMigrationGenerator())->translate($diff);

        $this->assertStringContainsString(
            "->addColumn('type', 'tinyinteger', ['null' => true])",
            implode("\n", $migration['up'])
        );
        $this->assertStringContainsString(
            "->addColumn('flags', 'integer', ['null' => true])",
            implode("\n", $migration['up'])
        );
        $this->assertStringNotContainsString("'values' =>", implode("\n", $migration['up']));
    }
}
