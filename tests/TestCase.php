<?php

namespace Funseco\Looks\Tests;

use Funseco\Admin\Tests\Support\AbilitySnapshot;
use Funseco\Core\Testing\TestCase as CoreTestCase;
use Funseco\Records\RecordsServiceProvider;
use Funseco\Donate\DonateServiceProvider;
use Funseco\Admin\AdminServiceProvider;
use Funseco\Votes\VotesServiceProvider;
use Funseco\Looks\LooksServiceProvider;

abstract class TestCase extends CoreTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AbilitySnapshot::apply();
    }

    /** Dependencies first, then this plugin (isolated runs load only these). */
    protected function plugins(): array
    {
        return [
            AdminServiceProvider::class,
            VotesServiceProvider::class,
            DonateServiceProvider::class,
            RecordsServiceProvider::class,
            LooksServiceProvider::class,
        ];
    }
}
