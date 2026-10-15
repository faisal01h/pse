<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Registered plugins
    |--------------------------------------------------------------------------
    |
    | Each entry is a Plugin class. enabled_setting optionally gates runtime
    | use via the settings store. requires lists plugin ids that must also
    | be enabled (and configured, for llm).
    |
    */
    'registry' => [
        [
            'class' => App\Plugins\Llm\LlmPlugin::class,
            'enabled' => true,
            'enabled_setting' => 'llm.enabled',
        ],
        [
            'class' => App\Plugins\AutoVet\AutoVetPlugin::class,
            'enabled' => true,
            'enabled_setting' => 'autovet.enabled',
        ],
        [
            'class' => App\Plugins\NewsRadar\NewsRadarPlugin::class,
            'enabled' => true,
            'enabled_setting' => 'newsradar.enabled',
            'requires' => ['llm'],
        ],
    ],
];
