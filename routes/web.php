<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Temporary Database Fix Route
Route::get('/fix-database', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
        $output = \Illuminate\Support\Facades\Artisan::output();
        return "Migrations status: <br><pre>$output</pre><br>Current Tables: <br><pre>" . json_encode($tables, JSON_PRETTY_PRINT) . "</pre>";
    } catch (\Exception $e) {
        return 'Error running migrations: ' . $e->getMessage();
    }
});
