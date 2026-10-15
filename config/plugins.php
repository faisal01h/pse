<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Registered plugins
    |--------------------------------------------------------------------------
    |
    | Each entry is a Plugin class. enabled_setting optionally gates boot via
    | the settings store (e.g. autovet.enabled). When null, the plugin boots
    | whenever config enabled is true.
    |
    */
    'registry' => [
        [
            'class' => App\Plugins\AutoVet\AutoVetPlugin::class,
            'enabled' => true,
            'enabled_setting' => 'autovet.enabled',
        ],
    ],
];
