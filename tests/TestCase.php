<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Prevent "Vite manifest not found" exceptions in tests.
        // Assets are not compiled during the test run; Vite tags are stubbed out.
        $this->withoutVite();
    }
}
