<?php

namespace Inertia\Tests;

use Illuminate\Support\Facades\DB;

trait InteractsWithUserModels
{
    protected function setUpInteractsWithUserModels(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::statement('DROP TABLE IF EXISTS users');
        DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT)');
        DB::table('users')->insert(array_fill(0, 40, ['id' => null]));
    }

    protected function tearDownInteractsWithUserModels(): void
    {
        DB::statement('DROP TABLE users');
    }
}
