<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $hasProviderTable = Schema::hasTable('gaminghub_provider_instances');

        Schema::create('gaminghub_panel_credentials', function (Blueprint $table) use ($hasProviderTable): void {
            $table->increments('id');
            $table->unsignedInteger('provider_id')->unique();
            $table->text('encrypted_api_token')->nullable();
            $table->text('encrypted_runtime_token')->nullable();
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
        Schema::dropIfExists('gaminghub_panel_credentials');
    }
};
