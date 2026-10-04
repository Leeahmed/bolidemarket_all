<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']['database.default'] !== 'mysql'
            || $app['config']['database.connections.mysql.database'] !== 'bolidemarket_test'
            || $app['config']['database.connections.mysql.host'] !== '127.0.0.1') {
            throw new \RuntimeException('Tests autorisés uniquement sur MySQL local / bolidemarket_test.');
        }

        return $app;
    }
}
