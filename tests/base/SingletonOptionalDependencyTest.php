<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\Singleton;

class SingletonOptionalDependencyTest extends TestCase
{
    private function subject(): Singleton
    {
        return new class extends Singleton {
        };
    }

    public function testAnOptionalDependencyThatCannotBeBuiltIsSkipped(): void
    {
        $subject = $this->subject();

        $result = $subject->load('missing', true, 'Psfs\\Tests\\DoesNotExist\\Service', false);

        self::assertSame($subject, $result, 'The object is still usable without the optional dependency');
    }

    public function testARequiredDependencyThatCannotBeBuiltFailsLoudly(): void
    {
        $this->expectException(\Throwable::class);

        $this->subject()->load('missing', true, 'Psfs\\Tests\\DoesNotExist\\Service', true);
    }
}
