<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->boot();

$controller = app(App\Http\Controllers\BackOffice\ResourceController::class);

$reflection = new ReflectionClass($controller);
$recordMethod = $reflection->getMethod('record');
$recordMethod->setAccessible(true);
$record = $recordMethod->invokeArgs($controller, [config('hotelpos.modules.users'), 2]);

$lookupsMethod = $reflection->getMethod('lookups');
$lookupsMethod->setAccessible(true);
$lookups = $lookupsMethod->invokeArgs($controller, [config('hotelpos.modules.users')]);

$config = config('hotelpos.modules.users');

$field = $config['fields']['role_id'];
$value = $record->role_id ?? null;
echo "Value before: " . json_encode($value) . "\n";

if (($field['type'] ?? null) === 'select' && isset($field['source'])) {
    $sourceRows = $lookups[$field['source']] ?? collect();
    $match = $sourceRows->firstWhere('id', $value);
    $value = $match->name ?? $match->number ?? $value;
}

echo "Value after: " . json_encode($value) . "\n";
echo "Display: " . (filled($value) ? $value : '-') . "\n";
