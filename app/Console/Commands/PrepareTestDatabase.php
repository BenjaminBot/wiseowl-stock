<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareTestDatabase extends Command
{
    protected $signature = 'stock:prepare-test-database';

    protected $description = 'Create the isolated local PostgreSQL test database if missing';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('This setup command is restricted to the local development environment.');

            return self::FAILURE;
        }
        $exists = DB::connection('pgsql')->selectOne('SELECT 1 FROM pg_database WHERE datname = ?', ['wise_owl_testing']);
        if (! $exists) {
            DB::connection('pgsql')->statement('CREATE DATABASE wise_owl_testing');
        }
        $this->info('Isolated database wise_owl_testing is ready.');

        return self::SUCCESS;
    }
}
