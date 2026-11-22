<?php

use App\Repositories\FileHerdRepository;
use App\Services\HerdService;
use Illuminate\Contracts\Console\Kernel;

// Separate PHP process used to exercise the actual inter-process file lock.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$repository = new FileHerdRepository($argv[1]);
(new HerdService($repository))->createEvent([
    'type' => 'tag_change', 'animal_id' => 'animal-020',
    'occurred_on' => '2026-11-20', 'new_tag' => 'Tag '.$argv[2],
]);
