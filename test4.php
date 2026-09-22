<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::find(2);
$payload = ['role_id' => 1, 'name' => 'test2'];
$user->update(Arr::except($payload, ['role_id']));
$user->roles()->sync([$payload['role_id']]);

echo 'Role ID after sync: '.DB::table('user_roles')->where('user_id', 2)->value('role_id')."\n";
