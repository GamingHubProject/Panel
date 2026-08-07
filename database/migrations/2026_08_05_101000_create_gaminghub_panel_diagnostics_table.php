<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $hasProviderTable = Schema::hasTable('gaminghub_provider_instances');

        Schema::create('gaminghub_panel_diagnostics', function (Blueprint $table) use ($hasProviderTable): void {
            $table->increments('id');
            $table->unsignedInteger('provider_id')->unique();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('last_successful_at')->nullable();
            $table->string('last_error_category', 64)->nullable();
            $table->string('detected_version', 100)->nullable();
            $table->string('last_state', 32)->nullable();
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->timestamps();
            if ($hasProviderTable) {
                $table->foreign('provider_id')
                    ->references('id')
                    ->on('gaminghub_provider_instances')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gaminghub_panel_diagnostics');
    }
};
