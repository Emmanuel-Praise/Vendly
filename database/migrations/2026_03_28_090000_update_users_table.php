<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('shop_latitude', 10, 8)->nullable();
            $table->decimal('shop_longitude', 11, 8)->nullable();
            $table->boolean('has_shop')->default(false);
            $table->boolean('is_private')->default(false);
            $table->boolean('show_location')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['shop_latitude', 'shop_longitude', 'has_shop', 'is_private', 'show_location']);
        });
    }
};
