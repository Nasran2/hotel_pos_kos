<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);
$sourceRows = \Illuminate\Support\Facades\DB::table('roles')->whereNull('deleted_at')->get();
$match = $sourceRows->firstWhere('id', 1);
echo $match->name;
