<?php

use App\Actions\Inventory\ReceiveStock;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

// Invoked as a separate PHP process by the PostgreSQL concurrency test.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.pgsql.database') !== 'wise_owl_testing') {
    throw new RuntimeException('Concurrency worker refuses to use a non-test database.');
}
$actor = User::findOrFail((int) $argv[1]);
$input = json_decode(base64_decode($argv[2]), true, flags: JSON_THROW_ON_ERROR);
$receipt = app(ReceiveStock::class)->execute($actor, $input);
echo $receipt->id;
