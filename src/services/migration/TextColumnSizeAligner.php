<?php

namespace PSFS\services\migration;

use Propel\Generator\Model\Column;
use Propel\Generator\Model\Database;

/**
 * MySQL reports a TEXT column without a size, while schema.xml may declare one (LONGVARCHAR with size="2000" is
 * TEXT(2000) in Propel's own SQL). That size is the same TEXT in the database, so it must not be reported as a
 * change on every comparison: the size of the schema is copied to the column read from the database.
 */
final class TextColumnSizeAligner
{
    private const TEXT_TYPES = ['LONGVARCHAR', 'CLOB'];

    /**
     * @param Database $fromDatabase the database as it is, read from MySQL
     * @param Database $toDatabase the database as schema.xml defines it
     */
    public static function align(Database $fromDatabase, Database $toDatabase): void
    {
        foreach ($fromDatabase->getTables() as $table) {
            if (!$toDatabase->hasTable($table->getName(), true)) {
                continue;
            }
            $wanted = $toDatabase->getTable($table->getName(), true);
            foreach ($table->getColumns() as $column) {
                self::alignColumn($column, $wanted->getColumn($column->getName(), true));
            }
        }
    }

    private static function alignColumn(Column $current, ?Column $wanted): void
    {
        if (null === $wanted || !in_array(strtoupper($current->getType()), self::TEXT_TYPES, true)) {
            return;
        }
        if (null === $current->getSize() && null !== $wanted->getSize()) {
            $current->setSize($wanted->getSize());
        }
    }
}
