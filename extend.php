<?php

namespace Flamarkt\TestData;

use Flarum\Extend;

return [
    (new Extend\Console())
        ->command(Console\ResetCommand::class)
        ->command(Console\SeedCommand::class),
];
