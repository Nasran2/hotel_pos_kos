<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = app(App\Http\Controllers\BackOffice\ResourceController::class);
$reflection = new ReflectionClass($controller);
$recordMethod = $reflection->getMethod('record');
$recordMethod->setAccessible(true);
$record = $recordMethod->invokeArgs($controller, [config('hotelpos.modules.users'), 2]);

$lookupsMethod = $reflection->getMethod('lookups');
$lookupsMethod->setAccessible(true);
$lookups = $lookupsMethod->invokeArgs($controller, [config('hotelpos.modules.users')]);

$value = $record->role_id ?? null;
$field = config('hotelpos.modules.users.fields.role_id');

if (($field['type'] ?? null) === 'select' && isset($field['source'])) {
    $sourceRows = $lookups[$field['source']] ?? collect();
    $match = $sourceRows->firstWhere('id', $value);
    $value = $match->name ?? $match->number ?? $value;
}

echo "Display value: " . (filled($value) ? $value : '-') . "\n";
