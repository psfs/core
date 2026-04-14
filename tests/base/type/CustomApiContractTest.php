<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\CustomApi;

class CustomApiContractProbe extends CustomApi
{
    public function init()
    {
        // Prevent Singleton bootstrap side-effects in unit tests.
    }
}

class CustomApiContractTest extends TestCase
{
    public function testCustomApiDefaultActionsReturnNullByContract(): void
    {
        $probe = $this->newProbe();

        $this->assertNull($probe->getModelTableMap());
        $this->assertNull($probe->get(1));
        $this->assertNull($probe->delete(1));
        $this->assertNull($probe->post());
        $this->assertNull($probe->put(1));
        $this->assertNull($probe->admin());
        $this->assertNull($probe->bulk());
    }

    private function newProbe(): CustomApiContractProbe
    {
        $reflection = new \ReflectionClass(CustomApiContractProbe::class);
        /** @var CustomApiContractProbe $instance */
        $instance = $reflection->newInstanceWithoutConstructor();
        return $instance;
    }
}
