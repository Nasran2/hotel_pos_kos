<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Boot app and set user
$app->boot();
$user = App\Models\User::find(1);
auth()->login($user);

$request = Illuminate\Http\Request::create('/manage/users/2', 'PUT', [
    'name' => 'test_edit',
    'username' => 'test_edit',
    'role_id' => 3,
    'is_active' => 1
]);
$request->setRouteResolver(function () use ($request) {
    return (new Illuminate\Routing\Route('PUT', '/manage/users/{id}', []))->bind($request);
});

try {
    $controller = app(App\Http\Controllers\BackOffice\ResourceController::class);
    $response = $controller->update($request, 'users', 2);
    echo "SUCCESS";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if ($e instanceof Illuminate\Validation\ValidationException) {
        print_r($e->errors());
    }
}
