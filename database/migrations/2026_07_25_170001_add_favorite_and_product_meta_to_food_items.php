<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            $table->boolean('is_favorite')->default(false)->after('menu_name');
            $table->string('brand', 100)->nullable()->after('is_favorite');
            $table->string('nutrition_basis', 16)->nullable()->after('brand');
            $table->decimal('basis_amount', 8, 2)->nullable()->after('nutrition_basis');
            $table->string('basis_unit', 16)->nullable()->after('basis_amount');
            $table->decimal('package_amount', 8, 2)->nullable()->after('basis_unit');
            $table->string('package_unit', 16)->nullable()->after('package_amount');
            $table->string('confirmation_status', 32)->nullable()->after('package_unit');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_status');
            $table->string('image_url', 500)->nullable()->after('confirmed_at');
            $table->string('external_id', 100)->nullable()->after('image_url');
            $table->string('external_url', 500)->nullable()->after('external_id');

            $table->index(['user_id', 'is_favorite']);
        });
    }

    public function down(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_favorite']);
            $table->dropColumn([
                'is_favorite',
                'brand',
                'nutrition_basis',
                'basis_amount',
                'basis_unit',
                'package_amount',
                'package_unit',
                'confirmation_status',
                'confirmed_at',
                'image_url',
                'external_id',
                'external_url',
            ]);
        });
    }
};
