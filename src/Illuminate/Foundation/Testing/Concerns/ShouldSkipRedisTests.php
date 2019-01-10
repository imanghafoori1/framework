<?php

namespace Illuminate\Foundation\Testing\Concerns;

class ShouldSkipRedisTests
{
    /**
     * Indicate connection failed if redis is not available.
     *
     * @var bool
     */
    public static $connectionFailedOnceWithDefaultsSkip = false;
}