<?php

use App\Http\Controllers\BackOffice\ResourceController;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

// Boot app and set user
$app->boot();
$user = User::find(1);
auth()->login($user);

$request = Request::create('/manage/users/2', 'PUT', [
    'name' => 'test_edit',
    'username' => 'test_edit',
    'role_id' => 3,
    'is_active' => 1,
]);
$request->setRouteResolver(function () use ($request) {
    return (new Route('PUT', '/manage/users/{id}', []))->bind($request);
});

try {
    $controller = app(ResourceController::class);
    $response = $controller->update($request, 'users', 2);
    echo 'SUCCESS';
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    if ($e instanceof ValidationException) {
        print_r($e->errors());
    }
}
