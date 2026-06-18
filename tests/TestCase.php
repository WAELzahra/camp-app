<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Seed roles once, right after migrate:fresh and OUTSIDE the per-test
     * transaction, so this foundational reference data persists for the whole
     * run. The users.role_id foreign key depends on it. RefreshDatabase reads
     * these two properties; non-DB tests simply ignore them.
     */
    protected bool $seed = true;

    protected string $seeder = RoleSeeder::class;
}
