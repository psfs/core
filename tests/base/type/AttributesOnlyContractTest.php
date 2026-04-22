<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\dto\DeleteUserRequestDto;
use PSFS\base\dto\ValidationContext;
use PSFS\base\types\Api;
use PSFS\base\types\helpers\AnnotationHelper;
use PSFS\base\types\helpers\MetadataReader;
use PSFS\controller\ConfigController;

class AttributesOnlyContractTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
        $override = $this->configBackup;
        $override['metadata.attributes.enabled'] = true;
        $override['metadata.annotations.fallback.enabled'] = false;
        Config::save($override, []);
        Config::getInstance()->loadConfigData(true);
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
    }

    public function testRoutingMetadataWorksWithAttributesOnly(): void
    {
        $method = new \ReflectionMethod(ConfigController::class, 'config');
        $doc = (string)$method->getDocComment();

        $this->assertSame('/admin/config', AnnotationHelper::extractRoute($doc, $method));
        $this->assertSame('GET', AnnotationHelper::extractReflectionHttpMethod($doc, $method));
    }

    public function testDocumentorPayloadAndReturnWorkWithAttributesOnly(): void
    {
        $postMethod = new \ReflectionMethod(Api::class, 'post');
        $doc = (string)$postMethod->getDocComment();

        $this->assertSame('{__API__}', MetadataReader::extractPayload('FallbackModel', $postMethod, $doc));
        $this->assertSame('\PSFS\base\dto\JsonResponse(data={__API__})', MetadataReader::extractReturnSpec($postMethod, $doc));
    }

    public function testDtoValidationWorksWithAttributesOnly(): void
    {
        $dto = new DeleteUserRequestDto(false);
        $dto->fromArray([]);
        $result = $dto->checkValidations(new ValidationContext([], [], false, false));

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }
}
