<?php

namespace Flamarkt\TestData\Event;

use Faker\Generator;
use Flamarkt\TestData\Console\SeedCommand;
use Illuminate\Database\Eloquent\Model;

class ModelSeed
{
    public $command;
    public $faker;
    public $model;

    public function __construct(SeedCommand $command, Generator $faker, Model $model)
    {
        $this->command = $command;
        $this->faker = $faker;
        $this->model = $model;
    }
}
