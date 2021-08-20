<?php

namespace Flamarkt\TestData\Event;

use Faker\Generator;
use Flamarkt\TestData\Console\SeedCommand;

class AfterSeed
{
    public $command;
    public $faker;

    public function __construct(SeedCommand $command, Generator $faker)
    {
        $this->command = $command;
        $this->faker = $faker;
    }
}
