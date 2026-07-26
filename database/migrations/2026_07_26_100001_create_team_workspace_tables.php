<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_users', function (Blueprint $table) {
            $table->id();
            $table->string('google_subject')->unique();
            $table->string('email')->index();
            $table->string('name');
            $table->string('avatar_url')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('last_authenticated_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('organization_type')->default('team');
            $table->string('status')->default('active')->index();
            $table->string('timezone')->default('Asia/Tokyo');
            $table->foreignId('created_by_team_user_id')->constrained('team_users');
            $table->timestamps();
        });

        Schema::create('team_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->string('member_type');
            $table->unsignedBigInteger('member_id');
            $table->string('role');
            $table->string('status')->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->foreignId('invited_by_team_user_id')->nullable()->constrained('team_users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['team_id', 'member_type', 'member_id', 'role'], 'team_member_role_unique');
            $table->index(['team_id', 'member_type', 'status']);
        });

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('role');
            $table->string('invitee_type');
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by_team_user_id')->constrained('team_users');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_member_type')->nullable();
            $table->unsignedBigInteger('accepted_member_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('team_memberships');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('team_users');
    }
};
