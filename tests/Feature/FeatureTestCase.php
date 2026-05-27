<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * Thin base class for Phase 8 feature tests. Provides RefreshDatabase
 * + WithFaker out of the box so individual tests don't have to wire them up.
 */
abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;
    use WithFaker;
}
