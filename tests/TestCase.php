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

    /**
     * Hard safety guard (added 2026-07-27): RefreshDatabase unconditionally wipes
     * every table on the FIRST test of any run, regardless of the database's
     * current state — it does not check whether data already exists. An incident
     * where a shell DB_DATABASE override pointed a test run at the real dev
     * database (bypassing phpunit.xml's intended value) wiped it completely.
     * This check runs BEFORE parent::setUp() — i.e. before RefreshDatabase's own
     * setUp logic can execute — so a misdirected DB_DATABASE aborts immediately
     * instead of wiping whatever it's pointed at.
     */
    protected function setUp(): void
    {
        $db = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? null);
        if (!$db || !str_contains(strtolower($db), 'test')) {
            throw new \RuntimeException(
                'Refusing to run tests against database "'.$db.'": its name does not contain "test". '.
                'RefreshDatabase wipes all tables unconditionally on the first test of every run — this guard exists '.
                'after an incident where a misdirected DB_DATABASE env var wiped a real database. '.
                'Point DB_DATABASE at an actual test database rather than removing this check.'
            );
        }

        parent::setUp();
    }
}
