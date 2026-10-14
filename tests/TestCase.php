<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\SeedsAuthorization;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
