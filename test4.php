<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::find(2);
$payload = ['role_id' => 1, 'name' => 'test2'];
$user->update(Illuminate\Support\Arr::except($payload, ['role_id']));
$user->roles()->sync([$payload['role_id']]);

echo "Role ID after sync: " . \Illuminate\Support\Facades\DB::table('user_roles')->where('user_id', 2)->value('role_id') . "\n";
