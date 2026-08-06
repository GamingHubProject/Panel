<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gaminghub_panel_discovered_servers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('connection_id');
            $table->string('stable_identifier', 64);
            $table->string('uuid', 64)->nullable();
            $table->string('short_identifier', 64)->nullable();
            $table->string('name');
            $table->string('node_name')->nullable();
            $table->boolean('suspended')->nullable();
            $table->string('primary_allocation')->nullable();
            $table->boolean('available')->default(true)->index();
            $table->timestamp('discovered_at');
            $table->timestamp('missing_since')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'stable_identifier'], 'gh_panel_discovered_connection_identifier_unique');
            $table->index(['connection_id', 'name'], 'gh_panel_discovered_connection_name_index');
            $table->foreign('connection_id')
                ->references('id')
                ->on('gaminghub_panel_connections')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gaminghub_panel_discovered_servers');
    }
};
