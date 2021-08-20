<?php

namespace Flamarkt\TestData\Event;

use Flamarkt\TestData\Console\SeedCommand;

class BeforeReset
{
    public $command;

    public function __construct(SeedCommand $command)
    {
        $this->command = $command;
    }
}
