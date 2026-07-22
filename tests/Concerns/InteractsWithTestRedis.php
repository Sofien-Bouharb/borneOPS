<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Redis;

trait InteractsWithTestRedis
{
    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->flushdb();
    }
}
