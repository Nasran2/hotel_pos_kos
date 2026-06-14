<?php
$_SERVER['REQUEST_URI'] = '/manage/users/2';
$_SERVER['REQUEST_METHOD'] = 'PUT';

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = App\Models\User::find(1);

$request = Illuminate\Http\Request::create('/manage/users/2', 'PUT', [
    'name' => 'test_save',
    'username' => 'test_save',
    'role_id' => 3,
    'email' => 'test@test.com'
]);
$request->setUserResolver(function() use ($user) { return $user; });

$app->instance('request', $request);

try {
    $response = $kernel->handle($request);
    echo "Status: " . $response->getStatusCode() . "\n";
    if ($response->isRedirection()) {
        echo "Redirect: " . $response->headers->get('Location') . "\n";
    } else {
        echo $response->getContent();
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($e instanceof Illuminate\Validation\ValidationException) {
        print_r($e->errors());
    }
}
