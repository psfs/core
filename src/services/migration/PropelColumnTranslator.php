<?php

namespace PSFS\services\migration;

use Phinx\Util\Literal;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\ColumnDefaultValue;

/**
 * Describes a Propel column with the Phinx column API: type and options. It follows what Propel itself creates in
 * MySQL (its generated SQL), because that is what the model classes expect to find in the database: ENUM is a
 * TINYINT holding the position of the value, BOOLEAN is TINYINT(1), SMALLINT stays SMALLINT, and a LONGVARCHAR
 * becomes the TEXT type that fits its size.
 */
final class PropelColumnTranslator
{
    private const TYPES = [
        'BIT' => 'integer',
        'TINYINT' => 'tinyinteger',
        'SMALLINT' => 'smallinteger',
        'INTEGER' => 'integer',
        'INT' => 'integer',
        'BIGINT' => 'biginteger',
        'BOOLEAN' => 'boolean',
        'BOOL' => 'boolean',
        'CHAR' => 'char',
        'VARCHAR' => 'string',
        'VARCHAR_IGNORECASE' => 'string',
        'LONGVARCHAR' => 'text',
        'CLOB' => 'text',
        'TINYTEXT' => 'text',
        'TEXT' => 'text',
        'BINARY' => 'binary',
        'VARBINARY' => 'varbinary',
        'BLOB' => 'blob',
        'TINYBLOB' => 'blob',
        'DECIMAL' => 'decimal',
        'NUMERIC' => 'decimal',
        'FLOAT' => 'float',
        'DOUBLE' => 'double',
        'REAL' => 'float',
        'DATE' => 'date',
        'TIME' => 'time',
        'DATETIME' => 'datetime',
        'TIMESTAMP' => 'timestamp',
        'UUID' => 'uuid',
        'JSON' => 'json',
        'ENUM' => 'tinyinteger',
        'SET' => 'integer',
    ];
    /** MySQL picks TINYTEXT, TEXT, MEDIUMTEXT or LONGTEXT from the size given to TEXT(n) */
    private const TEXT_LIMITS = [255 => 255, 65535 => null, 16777215 => 16777215];
    private const TEXT_LONG = 4294967295;

    public function type(Column $column): string
    {
        $type = strtoupper($column->getType());
        if (!isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported Propel type "%s" for column "%s"',
                $type,
                $column->getFullyQualifiedName()
            ));
        }

        return self::TYPES[$type];
    }

    /**
     * @return array<string, mixed>
     */
    public function options(Column $column): array
    {
        $type = $this->type($column);
        $options = [];
        if ($column->hasDefaultValue()) {
            $options['default'] = $this->defaultValue($column);
        }
        if ($column->isAutoIncrement()) {
            $options['identity'] = true;
        }
        $options += $this->sizeOptions($column, $type);
        $options['null'] = !$column->isNotNull();
        if ('' !== (string)$column->getDescription()) {
            $options['comment'] = $column->getDescription();
        }

        return $options;
    }

    /**
     * @return array<string, int>
     */
    private function sizeOptions(Column $column, string $type): array
    {
        $size = $column->getSize();
        if (in_array($type, ['char', 'string', 'binary', 'varbinary'], true) && null !== $size) {
            return ['limit' => $size];
        }
        if ('decimal' === $type && null !== $size) {
            return ['precision' => $size, 'scale' => (int)$column->getScale()];
        }
        if ('text' === $type && null !== $size && null !== ($limit = $this->textLimit((int)$size))) {
            return ['limit' => $limit];
        }

        return [];
    }

    private function textLimit(int $size): ?int
    {
        foreach (self::TEXT_LIMITS as $maximum => $limit) {
            if ($size <= $maximum) {
                return $limit;
            }
        }

        return self::TEXT_LONG;
    }

    private function defaultValue(Column $column): mixed
    {
        $default = $column->getDefaultValue();
        if (!$default instanceof ColumnDefaultValue) {
            return $default;
        }
        if ($default->isExpression()) {
            return new Literal((string)$default->getValue());
        }
        $value = $default->getValue();

        return match (strtoupper($column->getType())) {
            'BOOLEAN', 'BOOL' => $this->booleanDefault($value),
            'ENUM' => $this->enumOrdinal($column, $value),
            default => $value,
        };
    }

    private function booleanDefault(mixed $value): int
    {
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }

    /**
     * Propel stores an ENUM value as its position in the value set
     */
    private function enumOrdinal(Column $column, mixed $value): int
    {
        $position = array_search((string)$value, array_map('strval', $column->getValueSet()), true);

        return false === $position ? (int)$value : (int)$position;
    }
}
