<?php

return [
    'enabled' => filter_var(env('demo', env('DEMO', false)), FILTER_VALIDATE_BOOLEAN),
    'reset_days' => 15,
];
