<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Time math is in whole seconds (R-5). Starting every test frozen on a whole second makes
        // "start → travel 1 h → stop = 3600 s" exact instead of 3601 when a real second ticks in between.
        $this->freezeSecond();
    }
}
