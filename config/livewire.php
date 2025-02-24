<?php

return [
    'class_namespace' => 'App\\Http\\Livewire',

    'view_path' => resource_path('views/livewire'),

    'layout' => 'layouts.app',

    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => null,
        'directory' => 'livewire-tmp',
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
            'txt', 'pdf', 'pem'
        ],
        'max_upload_time' => 5,
    ],

    'manifest_path' => null,

    'back_button_cache' => false,

    'render_on_redirect' => false,
]; 