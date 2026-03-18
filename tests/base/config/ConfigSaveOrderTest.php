<?php

namespace PSFS\tests\base\config;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;

class ConfigSaveOrderTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configBackup = Config::getInstance()->dumpConfig();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
        parent::tearDown();
    }

    public function testSaveOrdersConfigKeysAlphabetically(): void
    {
        $data = $this->configBackup;
        unset($data['alpha.test'], $data['zeta.test']);
        $data['zeta.test'] = '1';
        $data['alpha.test'] = '1';

        $this->assertTrue(Config::save($data, []));
        $raw = json_decode((string)file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . Config::CONFIG_FILE), true);
        $this->assertIsArray($raw);

        $keys = array_keys($raw);
        $alphaPos = array_search('alpha.test', $keys, true);
        $zetaPos = array_search('zeta.test', $keys, true);
        $this->assertIsInt($alphaPos);
        $this->assertIsInt($zetaPos);
        $this->assertLessThan($zetaPos, $alphaPos);
    }
}

