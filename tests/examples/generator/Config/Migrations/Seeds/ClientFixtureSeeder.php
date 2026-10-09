<?php

use Phinx\Seed\AbstractSeed;

final class ClientFixtureSeeder extends AbstractSeed
{
    public function run(): void
    {
        $this->table('CLIENT_RELATED')
            ->insert(['TITLE' => 'Phinx seeder fixture'])
            ->saveData();
    }
}
