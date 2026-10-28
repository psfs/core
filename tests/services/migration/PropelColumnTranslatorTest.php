<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Phinx\Util\Literal;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\ColumnDefaultValue;
use Propel\Generator\Model\Table;
use PSFS\services\migration\PropelColumnTranslator;

class PropelColumnTranslatorTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function integerAndTemporalTypes(): array
    {
        return [
            'tiny integers stay tiny' => ['TINYINT', 'tinyinteger'],
            'small integers stay small' => ['SMALLINT', 'smallinteger'],
            'integers' => ['INTEGER', 'integer'],
            'big integers' => ['BIGINT', 'biginteger'],
            'booleans' => ['BOOLEAN', 'boolean'],
            'an enum is stored as its position' => ['ENUM', 'tinyinteger'],
            'timestamps' => ['TIMESTAMP', 'timestamp'],
            'dates' => ['DATE', 'date'],
            'long text' => ['LONGVARCHAR', 'text'],
            'binary strings' => ['VARBINARY', 'varbinary'],
        ];
    }

    #[DataProvider('integerAndTemporalTypes')]
    public function testEveryPropelTypeKeepsItsStorageSize(string $propel, string $phinx): void
    {
        $this->assertSame($phinx, (new PropelColumnTranslator())->type(new Column('c', $propel)));
    }

    public function testUnsupportedTypesFailWithTheColumnThatUsesThem(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported Propel type "ARRAY"');

        $table = new Table('posts');
        $column = new Column('tags', 'ARRAY');
        $table->addColumn($column);

        (new PropelColumnTranslator())->type($column);
    }

    public function testBooleanDefaultsBecomeNumbers(): void
    {
        $translator = new PropelColumnTranslator();

        $this->assertSame(1, $translator->options($this->booleanWithDefault('true'))['default']);
        $this->assertSame(1, $translator->options($this->booleanWithDefault('1'))['default']);
        $this->assertSame(0, $translator->options($this->booleanWithDefault('false'))['default']);
        $this->assertSame(0, $translator->options($this->booleanWithDefault('0'))['default']);
    }

    public function testAnEnumDefaultIsThePositionOfTheValueInItsSet(): void
    {
        $column = new Column('status', 'ENUM');
        $column->setValueSet(['DRAFT', 'REVIEW', 'PUBLISHED']);
        $column->setDefaultValue(new ColumnDefaultValue('REVIEW', ColumnDefaultValue::TYPE_VALUE));

        $this->assertSame(1, (new PropelColumnTranslator())->options($column)['default']);
    }

    public function testAnEnumDefaultThatIsNotInTheSetFallsBackToItsNumericValue(): void
    {
        $column = new Column('status', 'ENUM');
        $column->setValueSet(['A', 'B']);
        $column->setDefaultValue(new ColumnDefaultValue('2', ColumnDefaultValue::TYPE_VALUE));

        $this->assertSame(2, (new PropelColumnTranslator())->options($column)['default']);
    }

    public function testExpressionDefaultsAreLiterals(): void
    {
        $column = new Column('created', 'TIMESTAMP');
        $column->setDefaultValue(new ColumnDefaultValue('CURRENT_TIMESTAMP', ColumnDefaultValue::TYPE_EXPR));

        $default = (new PropelColumnTranslator())->options($column)['default'];

        $this->assertInstanceOf(Literal::class, $default);
        $this->assertSame('CURRENT_TIMESTAMP', (string)$default);
    }

    public function testSizesPrecisionAndIdentityAreRendered(): void
    {
        $translator = new PropelColumnTranslator();
        $decimal = new Column('price', 'DECIMAL', 10);
        $decimal->setScale(2);
        $key = new Column('id', 'BIGINT');
        $key->setAutoIncrement(true);
        $key->setNotNull(true);
        $code = new Column('code', 'CHAR', 3);
        $code->setDescription('ISO code');

        $this->assertSame(['precision' => 10, 'scale' => 2, 'null' => true], $translator->options($decimal));
        $this->assertSame(['identity' => true, 'null' => false], $translator->options($key));
        $this->assertSame(['limit' => 3, 'null' => true, 'comment' => 'ISO code'], $translator->options($code));
    }

    /**
     * @return array<string, array{0: int, 1: array<string, int>}>
     */
    public static function textSizes(): array
    {
        return [
            'no size is a TEXT' => [0, []],
            'up to 255 is a TINYTEXT' => [200, ['limit' => 255]],
            'up to 65535 is a TEXT' => [2000, []],
            'up to 16 MB is a MEDIUMTEXT' => [100000, ['limit' => 16777215]],
            'more is a LONGTEXT' => [20000000, ['limit' => 4294967295]],
        ];
    }

    /**
     * @param array<string, int> $expected
     */
    #[DataProvider('textSizes')]
    public function testTheTextTypeFollowsTheSizeLikeMysqlDoes(int $size, array $expected): void
    {
        $column = new Column('body', 'LONGVARCHAR', $size ?: null);

        $options = (new PropelColumnTranslator())->options($column);

        unset($options['null']);
        $this->assertSame($expected, $options);
    }

    private function booleanWithDefault(string $value): Column
    {
        $column = new Column('active', 'BOOLEAN');
        $column->setDefaultValue(new ColumnDefaultValue($value, ColumnDefaultValue::TYPE_VALUE));

        return $column;
    }
}
