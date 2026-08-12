<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0 data disposal (Panel Connector foundation roadmap).
 *
 * Deletes existing Pelican/Pterodactyl provider and connection data as a
 * deliberate, one-time exception to the platform's normal
 * non-destructive-disable rule. See docs/CONNECTOR_MIGRATION_AUDIT.md for
 * why: once GamingHubPanelServiceProvider stops registering these provider
 * types, editing a pre-existing pelican/pterodactyl ProviderInstance
 * through Core's generic admin form (no longer intercepted by Panel's
 * own override) risks silently reassigning its type on save, since Core's
 * generic <select> won't list an unregistered type and its generic
 * validator has never been exercised against these types' configuration
 * shape. Deleting the data removes the hazard instead of guarding it.
 *
 * This does not delete anything unrelated: only ProviderInstance rows
 * whose provider_type is pelican/pterodactyl, their linked
 * PanelCredential/ProviderDiagnostic rows, and Panel Connections whose
 * panel_type is pelican/pterodactyl (plus their discovered-server cache).
 * The Client/Reader/Normalizer/Controller/Request/View code that produced
 * this data is untouched — this migration only disposes of data, not code.
 */
return new class extends Migration
{
    private const PANEL_TYPES = ['pelican', 'pterodactyl'];

    public function up(): void
    {
        $providerIds = $this->disposeProviderInstances();
        $connectionIds = $this->disposePanelConnections();

        unset($providerIds, $connectionIds);
    }

    public function down(): void
    {
        // Intentionally irreversible: the deleted rows (credentials,
        // discovered-server cache, provider/connection configuration) are
        // not retained anywhere for this migration to restore from.
    }

    /** @return list<int> disposed provider ids, for readability at the call site */
    private function disposeProviderInstances(): array
    {
        if (! Schema::hasTable('gaminghub_provider_instances')) {
            return [];
        }

        $providerIds = DB::table('gaminghub_provider_instances')
            ->whereIn('provider_type', self::PANEL_TYPES)
            ->pluck('id')
            ->all();

        if ($providerIds === []) {
            return [];
        }

        if (Schema::hasTable('gaminghub_panel_credentials')) {
            DB::table('gaminghub_panel_credentials')->whereIn('provider_id', $providerIds)->delete();
        }

        if (Schema::hasTable('gaminghub_panel_diagnostics')) {
            DB::table('gaminghub_panel_diagnostics')->whereIn('provider_id', $providerIds)->delete();
        }

        DB::table('gaminghub_provider_instances')->whereIn('id', $providerIds)->delete();

        return $providerIds;
    }

    /** @return list<int> disposed connection ids, for readability at the call site */
    private function disposePanelConnections(): array
    {
        if (! Schema::hasTable('gaminghub_panel_connections')) {
            return [];
        }

        $connectionIds = DB::table('gaminghub_panel_connections')
            ->whereIn('panel_type', self::PANEL_TYPES)
            ->pluck('id')
            ->all();

        if ($connectionIds === []) {
            return [];
        }

        if (Schema::hasTable('gaminghub_panel_discovered_servers')) {
            DB::table('gaminghub_panel_discovered_servers')->whereIn('connection_id', $connectionIds)->delete();
        }

        DB::table('gaminghub_panel_connections')->whereIn('id', $connectionIds)->delete();

        return $connectionIds;
    }
};
