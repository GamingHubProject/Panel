<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gaminghub_panel_connections', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('panel_type', 32)->index();
            $table->string('base_url', 2048);
            $table->text('encrypted_application_token')->nullable();
            $table->text('encrypted_default_client_token')->nullable();
            $table->unsignedSmallInteger('timeout');
            $table->unsignedSmallInteger('cache_ttl');
            $table->boolean('verify_tls')->default(true);
            $table->boolean('enabled')->default(true)->index();
            $table->string('last_test_status', 32)->nullable();
            $table->string('last_test_code', 64)->nullable();
            $table->unsignedInteger('last_test_latency_ms')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_successful_test_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gaminghub_panel_connections');
    }
};
