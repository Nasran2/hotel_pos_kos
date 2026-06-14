<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = app(App\Http\Controllers\BackOffice\ResourceController::class);
$reflection = new ReflectionClass($controller);
$recordMethod = $reflection->getMethod('record');
$recordMethod->setAccessible(true);
$record = $recordMethod->invokeArgs($controller, [config('hotelpos.modules.users'), 2]);

var_dump(isset($record->role_id));
var_dump($record->role_id ?? null);
