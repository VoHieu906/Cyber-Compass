<?php

use Illuminate\Support\Facades\Route;

/*
| Versioned API (docs/API_SPECIFICATION.md). Each business module owns its routes in
| app/Modules/<Module>/routes.php; they are mounted here under /api/v1.
| Modules are added to this list as they are implemented.
*/

Route::prefix('v1')->name('v1.')->group(function () {
    foreach ([
        'System',
        'Auth',
        'Profile',
        'Catalog',
        'Careers',
        'Learning',
        'Admin',
    ] as $module) {
        require app_path("Modules/{$module}/routes.php");
    }
});
