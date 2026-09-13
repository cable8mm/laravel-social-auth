<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Browser;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\Dusk\TestCase as TestbenchDuskTestCase;

abstract class DuskTestCase extends TestbenchDuskTestCase
{
    use WithWorkbench;

    protected static $baseServePort = 18001;

    protected function user(): mixed
    {
        return auth()->user();
    }
}
