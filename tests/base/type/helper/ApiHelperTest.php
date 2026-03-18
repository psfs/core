<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use Propel\Generator\Model\PropelTypes;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use PSFS\base\config\Config;
use PSFS\base\dto\Field;
use PSFS\base\types\helpers\ApiHelper;

class ApiHelperTestProxy extends ApiHelper
{
    public static function parseFormFieldPublic(string $domain, TableMap $tableMap, string $field, array $behaviors = []): ?Field
    {
        return parent::parseFormField($domain, $tableMap, $field, $behaviors);
    }

    public static function parseEnumFieldPublic(string $field, bool $required, ColumnMap $mappedColumn): Field
    {
        return parent::parseEnumField($field, $required, $mappedColumn);
    }

    public static function generateTimestampFieldPublic(string $field, array $behaviors, ColumnMap $mappedColumn, bool $required): Field
    {
        return parent::generateTimestampField($field, $behaviors, $mappedColumn, $required);
    }

    public static function parseFieldTypePublic(string $domain, string $field, array $behaviors, ColumnMap $mappedColumn, bool $required): ?Field
    {
        return parent::parseFieldType($domain, $field, $behaviors, $mappedColumn, $required);
    }
}

final class ApiHelperTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
    }

    public function testBuildFieldDtoCreatesPrimaryKeyFieldAndAppliesCamelCase(): void
    {
        Config::save(array_merge($this->configBackup, ['api.field.case' => TableMap::TYPE_CAMELNAME]), []);
        Config::getInstance()->loadConfigData(true);

        $column = $this->createMock(ColumnMap::class);
        $column->method('isNotNull')->willReturn(true);
        $column->method('getDefaultValue')->willReturn(null);
        $column->method('isForeignKey')->willReturn(false);
        $column->method('isPrimaryKey')->willReturn(true);
        $column->method('isNumeric')->willReturn(false);
        $column->method('isText')->willReturn(false);
        $column->method('getType')->willReturn(PropelTypes::INTEGER);
        $column->method('getSize')->willReturn(11);
        $column->method('getPhpName')->willReturn('UserId');
        $column->method('getFullyQualifiedName')->willReturn('demo.user_id');

        $table = $this->createMock(TableMap::class);
        $table->method('getColumnByPhpName')->with('UserId')->willReturn($column);

        $field = ApiHelper::buildFieldDto('demo', $table, 'UserId');

        $this->assertInstanceOf(Field::class, $field);
        $this->assertTrue($field->pk);
        $this->assertSame(Field::HIDDEN_TYPE, $field->type);
        $this->assertFalse($field->required);
        $this->assertSame('userId', $field->name);
        $this->assertSame(11, $field->size);
    }

    public function testParseEnumFieldUsesConfiguredColNameCase(): void
    {
        Config::save(array_merge($this->configBackup, ['api.field.case' => TableMap::TYPE_COLNAME]), []);
        Config::getInstance()->loadConfigData(true);

        $column = $this->createMock(ColumnMap::class);
        $column->method('getValueSet')->willReturn(['A', 'B']);
        $column->method('getPhpName')->willReturn('Status');
        $column->method('getFullyQualifiedName')->willReturn('demo.status');

        $field = ApiHelperTestProxy::parseEnumFieldPublic('Status', true, $column);

        $this->assertSame(Field::COMBO_TYPE, $field->type);
        $this->assertCount(2, $field->data);
        $this->assertArrayHasKey('demo.status', $field->data[0]);
        $this->assertSame('A', $field->data[0]['demo.status']);
    }

    public function testGenerateTimestampFieldMarksTimestampableAsOptionalTimestamp(): void
    {
        $column = $this->createMock(ColumnMap::class);
        $column->method('getType')->willReturn(PropelTypes::TIMESTAMP);
        $column->method('getName')->willReturn('created_at');

        $field = ApiHelperTestProxy::generateTimestampFieldPublic(
            'CreatedAt',
            ['timestampable' => ['created_at']],
            $column,
            true
        );

        $this->assertSame(Field::TIMESTAMP, $field->type);
        $this->assertFalse($field->required);
    }

    public function testParseFieldTypeResolvesBinaryAsPasswordField(): void
    {
        $column = $this->createMock(ColumnMap::class);
        $column->method('isForeignKey')->willReturn(false);
        $column->method('isPrimaryKey')->willReturn(false);
        $column->method('isNumeric')->willReturn(false);
        $column->method('isText')->willReturn(false);
        $column->method('getType')->willReturn(PropelTypes::VARBINARY);

        $field = ApiHelperTestProxy::parseFieldTypePublic('demo', 'Secret', [], $column, true);

        $this->assertInstanceOf(Field::class, $field);
        $this->assertSame(Field::PASSWORD_FIELD, $field->type);
    }

    public function testParseFieldTypeResolvesBooleanNumericTextAndEnum(): void
    {
        $numericColumn = $this->createMock(ColumnMap::class);
        $numericColumn->method('isForeignKey')->willReturn(false);
        $numericColumn->method('isPrimaryKey')->willReturn(false);
        $numericColumn->method('isNumeric')->willReturn(true);
        $numericColumn->method('isText')->willReturn(false);
        $numericColumn->method('getType')->willReturn(PropelTypes::INTEGER);
        $numericField = ApiHelperTestProxy::parseFieldTypePublic('demo', 'Amount', [], $numericColumn, true);
        $this->assertSame(Field::NUMBER_TYPE, $numericField?->type);

        $booleanColumn = $this->createMock(ColumnMap::class);
        $booleanColumn->method('isForeignKey')->willReturn(false);
        $booleanColumn->method('isPrimaryKey')->willReturn(false);
        $booleanColumn->method('isNumeric')->willReturn(false);
        $booleanColumn->method('isText')->willReturn(false);
        $booleanColumn->method('getType')->willReturn(PropelTypes::BOOLEAN);
        $booleanField = ApiHelperTestProxy::parseFieldTypePublic('demo', 'Enabled', [], $booleanColumn, true);
        $this->assertSame(Field::SWITCH_TYPE, $booleanField?->type);

        $textColumn = $this->createMock(ColumnMap::class);
        $textColumn->method('isForeignKey')->willReturn(false);
        $textColumn->method('isPrimaryKey')->willReturn(false);
        $textColumn->method('isNumeric')->willReturn(false);
        $textColumn->method('isText')->willReturn(true);
        $textColumn->method('getType')->willReturn(PropelTypes::LONGVARCHAR);
        $textColumn->method('getSize')->willReturn(150);
        $textField = ApiHelperTestProxy::parseFieldTypePublic('demo', 'Description', [], $textColumn, true);
        $this->assertSame(Field::TEXTAREA_TYPE, $textField?->type);

        $enumColumn = $this->createMock(ColumnMap::class);
        $enumColumn->method('isForeignKey')->willReturn(false);
        $enumColumn->method('isPrimaryKey')->willReturn(false);
        $enumColumn->method('isNumeric')->willReturn(false);
        $enumColumn->method('isText')->willReturn(false);
        $enumColumn->method('getType')->willReturn(PropelTypes::ENUM);
        $enumColumn->method('getValueSet')->willReturn(['ONE', 'TWO']);
        $enumColumn->method('getPhpName')->willReturn('Type');
        $enumColumn->method('getFullyQualifiedName')->willReturn('demo.type');
        $enumField = ApiHelperTestProxy::parseFieldTypePublic('demo', 'Type', [], $enumColumn, true);
        $this->assertSame(Field::COMBO_TYPE, $enumField?->type);
        $this->assertCount(2, $enumField?->data ?? []);
    }

    public function testParseFormFieldAppliesColNameCaseAndLabel(): void
    {
        Config::save(array_merge($this->configBackup, ['api.field.case' => TableMap::TYPE_COLNAME]), []);
        Config::getInstance()->loadConfigData(true);

        $column = $this->createMock(ColumnMap::class);
        $column->method('isNotNull')->willReturn(false);
        $column->method('getDefaultValue')->willReturn(null);
        $column->method('isForeignKey')->willReturn(false);
        $column->method('isPrimaryKey')->willReturn(false);
        $column->method('isNumeric')->willReturn(false);
        $column->method('isText')->willReturn(true);
        $column->method('getType')->willReturn(PropelTypes::LONGVARCHAR);
        $column->method('getSize')->willReturn(50);
        $column->method('getPhpName')->willReturn('Title');
        $column->method('getFullyQualifiedName')->willReturn('demo.title');

        $table = $this->createMock(TableMap::class);
        $table->method('getColumnByPhpName')->with('Title')->willReturn($column);

        $field = ApiHelperTestProxy::parseFormFieldPublic('demo', $table, 'Title');

        $this->assertSame('demo.title', $field?->name);
        $this->assertSame('demo.title', $field?->label);
    }
}
