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
        $table->string('account_status')->default('active');
        $table->unsignedInteger('failed_login_attempts')->default(0);
        $table->timestamp('locked_until')->nullable();
        $table->timestamp('last_login_at')->nullable();
        $table->timestamp('password_changed_at')->nullable();
        $table->boolean('two_factor_enabled')->default(false);
        $table->text('two_factor_secret')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn([
            'account_status',
            'failed_login_attempts',
            'locked_until',
            'last_login_at',
            'password_changed_at',
            'two_factor_enabled',
            'two_factor_secret',
        ]);
    });
}
};
