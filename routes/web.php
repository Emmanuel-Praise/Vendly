<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Temporary Database Force Repair Route
Route::get('/fix-database', function () {
    $results = [];
    try {
        // 1. Fix Users Table (api_token & google_id)
        if (!\Illuminate\Support\Facades\Schema::hasColumn('users', 'api_token')) {
            \Illuminate\Support\Facades\Schema::table('users', function ($table) {
                if (!\Illuminate\Support\Facades\Schema::hasColumn('users', 'api_token')) {
                    $table->string('api_token', 80)->after('password')->nullable()->unique();
                    $results[] = "Added api_token to users table.";
                }
            });
        }

        if (!\Illuminate\Support\Facades\Schema::hasColumn('users', 'google_id')) {
             \Illuminate\Support\Facades\Schema::table('users', function ($table) {
                if (!\Illuminate\Support\Facades\Schema::hasColumn('users', 'google_id')) {
                    $table->string('google_id')->nullable()->after('email');
                    $results[] = "Added google_id to users table.";
                }
            });
        }

        // 2. Fix Likes Table
        if (!\Illuminate\Support\Facades\Schema::hasTable('likes')) {
            \Illuminate\Support\Facades\Schema::create('likes', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('post_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'post_id']);
            });
            $results[] = "Created likes table.";
        }

        // 3. Fix Follows Table
        if (!\Illuminate\Support\Facades\Schema::hasTable('follows')) {
            \Illuminate\Support\Facades\Schema::create('follows', function ($table) {
                $table->id();
                $table->foreignId('follower_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('followed_id')->constrained('users')->onDelete('cascade');
                $table->timestamps();
                $table->unique(['follower_id', 'followed_id']);
            });
            $results[] = "Created follows table.";
        }

        // 4. Fix Comments Table
        if (!\Illuminate\Support\Facades\Schema::hasTable('comments')) {
            \Illuminate\Support\Facades\Schema::create('comments', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('post_id')->constrained()->cascadeOnDelete();
                $table->text('comment_text');
                $table->timestamps();
            });
            $results[] = "Created comments table.";
        }

        if (empty($results)) {
            $results[] = "Status OK: All critical tables and columns are already present!";
        }

        $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
        return "Repair Status: <br><ul><li>" . implode("</li><li>", $results) . "</li></ul><br>Current Tables: <br><pre>" . json_encode($tables, JSON_PRETTY_PRINT) . "</pre>";

    } catch (\Exception $e) {
        return 'Error during force repair: ' . $e->getMessage();
    }
});
