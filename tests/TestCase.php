<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing')) {
            throw new \RuntimeException('Tests refuse to run outside the testing environment.');
        }
        // Container-provided $_SERVER values can outrank PHPUnit env settings.
        // Set the isolated connection before any RefreshDatabase/DatabaseMigrations hook runs.
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql.url', null);
        $app['config']->set('database.connections.pgsql.database', 'wise_owl_testing');

        return $app;
    }
}
