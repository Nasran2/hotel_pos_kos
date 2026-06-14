<?php

$compiledPath = env('VIEW_COMPILED_PATH');

if (! is_string($compiledPath) || $compiledPath === '') {
    $defaultCompiledPath = storage_path('framework/views');

    if (! is_dir($defaultCompiledPath)) {
        @mkdir($defaultCompiledPath, 0755, true);
    }

    $compiledPath = is_writable($defaultCompiledPath)
        ? $defaultCompiledPath
        : sys_get_temp_dir().DIRECTORY_SEPARATOR.'hotel-pos-views-'.md5(base_path());
}

if (! is_dir($compiledPath)) {
    @mkdir($compiledPath, 0755, true);
}

return [
    'paths' => [
        resource_path('views'),
    ],

    'compiled' => $compiledPath,
];
