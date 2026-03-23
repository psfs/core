<?php

namespace PSFS\tests\base\type\trait;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Map\RelationMap;
use Propel\Runtime\Map\TableMap;
use PSFS\base\config\Config;
use PSFS\base\dto\Field;
use PSFS\base\types\traits\Helper\FieldHelperTrait;

final class FieldHelperTraitTest extends TestCase
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
        FieldHelperTraitTestDouble::$fieldNames = [];
        FieldHelperTraitTestDouble::$tableMap = null;
    }

    public function testSimpleFieldGeneratorsRespectExpectedTypes(): void
    {
        $pk = FieldHelperTraitTestDouble::generatePrimaryKeyField('Id', true);
        $this->assertSame(Field::HIDDEN_TYPE, $pk->type);
        $this->assertTrue($pk->pk);
        $this->assertFalse($pk->required);

        $numeric = FieldHelperTraitTestDouble::generateNumericField('Amount', true);
        $this->assertSame(Field::NUMBER_TYPE, $numeric->type);
        $this->assertTrue($numeric->required);

        $string = FieldHelperTraitTestDouble::generateStringField('Name');
        $this->assertSame(Field::TEXT_TYPE, $string->type);

        $boolean = FieldHelperTraitTestDouble::generateBooleanField('Enabled');
        $this->assertSame(Field::SWITCH_TYPE, $boolean->type);

        $password = FieldHelperTraitTestDouble::generatePasswordField('Secret');
        $this->assertSame(Field::PASSWORD_FIELD, $password->type);

        $date = FieldHelperTraitTestDouble::generateDateField('CreatedAt');
        $this->assertSame(Field::DATE, $date->type);

        $enum = FieldHelperTraitTestDouble::generateEnumField('Status');
        $this->assertSame(Field::COMBO_TYPE, $enum->type);
    }

    public function testGetFieldTypesMapsAllSupportedConfigurationAliases(): void
    {
        $cases = [
            'UpperCamelCase' => TableMap::TYPE_PHPNAME,
            TableMap::TYPE_PHPNAME => TableMap::TYPE_PHPNAME,
            'camelCase' => TableMap::TYPE_CAMELNAME,
            'lowerCamelCase' => TableMap::TYPE_CAMELNAME,
            TableMap::TYPE_CAMELNAME => TableMap::TYPE_CAMELNAME,
            'dbColumn' => TableMap::TYPE_COLNAME,
            TableMap::TYPE_COLNAME => TableMap::TYPE_COLNAME,
            TableMap::TYPE_FIELDNAME => TableMap::TYPE_FIELDNAME,
            TableMap::TYPE_NUM => TableMap::TYPE_NUM,
            'not-supported' => TableMap::TYPE_PHPNAME,
        ];

        foreach ($cases as $value => $expected) {
            Config::save(array_merge($this->configBackup, ['api.field.case' => $value]), []);
            Config::getInstance()->loadConfigData(true);
            $this->assertSame($expected, FieldHelperTraitTestDouble::getFieldTypes());
        }
    }

    public function testCheckFieldExistsUsesCaseInsensitiveMatching(): void
    {
        $matchingColumn = new FieldHelperTraitColumnMapStub('UserName');
        $tableMap = $this->createMock(TableMap::class);
        $tableMap->method('getColumns')->willReturn([$matchingColumn]);

        $found = FieldHelperTraitTestDouble::checkFieldExists($tableMap, 'username');

        $this->assertSame($matchingColumn, $found);
    }

    public function testCheckFieldExistsReturnsNullWhenTableMapThrows(): void
    {
        $tableMap = $this->createMock(TableMap::class);
        $tableMap->method('getColumns')->willThrowException(new \Exception('forced-failure'));

        $this->assertNull(FieldHelperTraitTestDouble::checkFieldExists($tableMap, 'any'));
    }

    public function testGenerateFormFieldsAddsI18nLocaleAsComboAndAvoidsDuplicateFields(): void
    {
        Config::save(array_merge($this->configBackup, ['i18n.locales' => 'es_ES,en_US']), []);
        Config::getInstance()->loadConfigData(true);

        $i18nColumns = [
            new FieldHelperTraitColumnMapStub('Locale'),
            new FieldHelperTraitColumnMapStub('Title'),
            new FieldHelperTraitColumnMapStub('Id'),
        ];

        $i18nTableMap = $this->createMock(TableMap::class);
        $i18nTableMap->method('getColumns')->willReturn($i18nColumns);
        $i18nTableMap->method('getBehaviors')->willReturn([]);

        $relation = $this->createMock(RelationMap::class);
        $relation->method('getLocalTable')->willReturn($i18nTableMap);

        $tableMap = $this->createMock(TableMap::class);
        $tableMap->method('getBehaviors')->willReturn(['i18n' => ['enabled' => true]]);
        $tableMap->method('getPhpName')->willReturn('Article');
        $tableMap->method('getRelation')->with('ArticleI18n')->willReturn($relation);

        FieldHelperTraitTestDouble::$tableMap = $tableMap;
        FieldHelperTraitTestDouble::$fieldNames = ['Id'];

        $form = FieldHelperTraitTestDouble::generateFormFields(FieldHelperTraitMapStub::class, 'demo');
        $fields = $form->__toArray()['fields'];

        $this->assertCount(3, $fields);

        $localeField = $this->findFieldByName($fields, 'Locale');
        $this->assertNotNull($localeField);
        $this->assertSame(Field::COMBO_TYPE, $localeField['type']);
        $this->assertTrue((bool)$localeField['required']);
        $this->assertFalse((bool)$localeField['pk']);
        $this->assertCount(2, $localeField['data']);

        $titleField = $this->findFieldByName($fields, 'Title');
        $this->assertNotNull($titleField);
        $this->assertTrue((bool)$titleField['required']);

        $idFields = array_values(array_filter($fields, static fn(array $field): bool => $field['name'] === 'Id'));
        $this->assertCount(1, $idFields, 'Id field must not be duplicated when i18n columns contain Id');
    }

    private function findFieldByName(array $fields, string $name): ?array
    {
        foreach ($fields as $field) {
            if ($field['name'] === $name) {
                return $field;
            }
        }
        return null;
    }
}

final class FieldHelperTraitTestDouble
{
    use FieldHelperTrait;

    public static ?TableMap $tableMap = null;

    /** @var array<int, string> */
    public static array $fieldNames = [];

    public static function parseFormField($domain, $tableMap, $field, $behaviors): ?Field
    {
        if ('IgnoredField' === $field) {
            return null;
        }
        return new Field($field, $field, Field::TEXT_TYPE, null, [], null, false);
    }

    public static function getColumnMapName($columnMap): string
    {
        return $columnMap->getPhpName();
    }
}

final class FieldHelperTraitMapStub
{
    public static function getTableMap(): TableMap
    {
        return FieldHelperTraitTestDouble::$tableMap;
    }

    /**
     * @return array<int, string>
     */
    public static function getFieldNames(): array
    {
        return FieldHelperTraitTestDouble::$fieldNames;
    }
}

final class FieldHelperTraitColumnMapStub
{
    public function __construct(private string $phpName)
    {
    }

    public function getPhpName(): string
    {
        return $this->phpName;
    }
}
