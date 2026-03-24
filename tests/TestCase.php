<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Env;
use ReflectionProperty;

abstract class TestCase extends BaseTestCase
{
    /**
     * Re-create the application for each test with a clean, test-aware
     * environment.
     *
     * WHY: `Env::$repository` is a static singleton that gets populated during
     * the initial artisan bootstrap (APP_ENV=local from .env). PHPUnit's
     * <env name="APP_ENV" value="testing" force="true"/> calls putenv() but
     * the immutable Dotenv repository still holds 'local', so
     * app()->environment() and runningUnitTests() return the wrong values.
     *
     * Clearing the repository before every createApplication() forces a fresh
     * Dotenv load that sees the process-level APP_ENV=testing set by PHPUnit.
     */
    public function createApplication()
    {
        // Reset the static Dotenv repository so the next bootstrap picks up
        // APP_ENV=testing from the process environment (set by PHPUnit).
        $prop = new ReflectionProperty(Env::class, 'repository');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent "Vite manifest not found" exceptions in tests.
        // Assets are not compiled during the test run; Vite tags are stubbed out.
        $this->withoutVite();

        // Disable CSRF verification for all tests.
        // runningUnitTests() is only reliable once the Env repository is fixed
        // (see createApplication above), but skipping CSRF in tests is the
        // standard Laravel convention regardless.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }
}
