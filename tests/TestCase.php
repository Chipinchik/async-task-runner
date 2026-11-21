<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)
            && ($app['config']->get('database.default') !== 'sqlite'
                || $app['config']->get('database.connections.sqlite.database') !== ':memory:')) {
            throw new \RuntimeException('RefreshDatabase tests require SQLite :memory:. Refusing to modify a persistent database.');
        }

        return $app;
    }
}
