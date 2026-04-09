<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Collection\ObjectCollection;
use PSFS\base\config\Config;
use PSFS\base\types\traits\Form\FormModelTrait;

class FormModelTraitTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
        Config::save(['default.language' => 'es_ES'], []);
        Config::getInstance()->loadConfigData(true);
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
    }

    public function testGetHydratedModelSetsLocaleAndHydratesScalarAndCollectionFields(): void
    {
        $model = new FormModelTraitModelDouble();
        $model->setTags(new ObjectCollection());

        $form = new FormModelTraitHarness();
        $form->setModel($model);
        $form->setRawFields([
            'name' => ['value' => 'Neo'],
            'tags' => ['value' => 'alpha'],
        ]);

        $hydrated = $form->getHydratedModel();

        $this->assertSame($model, $hydrated);
        $this->assertSame('es_ES', $model->locale);
        $this->assertSame('Neo', $model->name);
        $this->assertInstanceOf(\Propel\Runtime\Collection\Collection::class, $model->tags);
        $this->assertSame(['alpha'], $model->tags->getData());
    }

    public function testHydrateFromModelComputesFieldValuesForObjectsCollectionsAndScalars(): void
    {
        $model = new FormModelTraitModelDouble();

        $relatedCollection = new ObjectCollection();
        $relatedCollection->append(new FormModelTraitRelatedRow(new FormModelTraitChild(9)));
        $model->related = $relatedCollection;

        $checkboxCollection = new ObjectCollection();
        $checkboxCollection->append('manual');
        $model->labels = $checkboxCollection;

        $model->createdAt = new \DateTime('2024-01-02 03:04:05');
        $model->owner = new FormModelTraitEntityWithoutToString(42);
        $model->printable = new FormModelTraitPrintable('pretty');
        $model->title = 'hello';

        $form = new FormModelTraitHarness();
        $form->setModel($model);
        $form->setRawFields([
            'related' => ['type' => 'select', 'class_data' => 'Child', 'class_id' => 'Id'],
            'labels' => ['type' => 'checkbox'],
            'createdAt' => ['type' => 'text'],
            'owner' => ['type' => 'text'],
            'printable' => ['type' => 'text'],
            'title' => ['type' => 'text'],
        ]);

        $form->hydrateFromModel();

        $fields = $form->getRawFields();
        $this->assertSame([9], $fields['related']['value'] ?? null);
        $this->assertSame(['manual'], $fields['labels']['value'] ?? null);
        $this->assertSame('2024-01-02 03:04:05', $fields['createdAt']['value'] ?? null);
        $this->assertSame(42, $fields['owner']['value'] ?? null);
        $this->assertSame('pretty', (string)($fields['printable']['value'] ?? ''));
        $this->assertSame('hello', $fields['title']['value'] ?? null);
    }
}

class FormModelTraitHarness
{
    use FormModelTrait;

    public const SEPARATOR = '__sep__';

    public function setModel(object $model): void
    {
        $this->model = $model;
    }

    public function setRawFields(array $fields): void
    {
        $this->fields = $fields;
    }

    public function getRawFields(): array
    {
        return $this->fields;
    }

    public function getName(): string
    {
        return 'form_model_trait_harness';
    }
}

class FormModelTraitModelDouble
{
    public string $locale = '';
    public mixed $name = null;
    public mixed $tags = null;
    public mixed $related = null;
    public mixed $labels = null;
    public mixed $createdAt = null;
    public mixed $owner = null;
    public mixed $printable = null;
    public mixed $title = null;

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function setName(mixed $value): void
    {
        $this->name = $value;
    }

    public function getName(): mixed
    {
        return $this->name;
    }

    public function setTags(mixed $value): void
    {
        $this->tags = $value;
    }

    public function getTags(): mixed
    {
        return $this->tags;
    }

    public function getRelated(): mixed
    {
        return $this->related;
    }

    public function getLabels(): mixed
    {
        return $this->labels;
    }

    public function getCreatedAt(): mixed
    {
        return $this->createdAt;
    }

    public function getOwner(): mixed
    {
        return $this->owner;
    }

    public function getPrintable(): mixed
    {
        return $this->printable;
    }

    public function getTitle(): mixed
    {
        return $this->title;
    }
}

class FormModelTraitRelatedRow
{
    public function __construct(private FormModelTraitChild $child)
    {
    }

    public function getChild(): FormModelTraitChild
    {
        return $this->child;
    }
}

class FormModelTraitChild
{
    public function __construct(private int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPrimaryKey(): int
    {
        return $this->id;
    }
}

class FormModelTraitEntityWithoutToString
{
    public function __construct(private int $id)
    {
    }

    public function getPrimaryKey(): int
    {
        return $this->id;
    }
}

class FormModelTraitPrintable
{
    public function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
