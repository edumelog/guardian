<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use SolutionForest\FilamentAccessManagement\Support\Utils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(Utils::getMenuTableName(), function (Blueprint $table) {
            $table->boolean('is_filament_panel')->after('uri')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (empty(Utils::getMenuTableName())) {
            throw new \Exception('Error: config/filament-access-management.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');
        }

        Schema::table(Utils::getMenuTableName(), function (Blueprint $table) {
            $table->dropColumn('is_filament_panel');
        });
    }
};
