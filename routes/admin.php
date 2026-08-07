<?php

use Azuriom\Plugin\GamingHubPanel\Controllers\Admin\{
    ConnectionController,
    DiagnosticsController,
    PanelConnectionActionsController,
    PanelConnectionCredentialsController,
    PanelConnectionsController,
    PanelProviderController,
    SettingsController
};
use Illuminate\Support\Facades\Route;

Route::middleware('can:gaminghub-panel.connections.view')->group(function (): void {
    Route::get('/connections', [PanelConnectionsController::class, 'index'])->name('connections.index');
    Route::get('/connections/{connection}/edit', [PanelConnectionsController::class, 'edit'])->name('connections.edit');
});

Route::middleware('can:gaminghub-panel.connections.manage')->group(function (): void {
    Route::get('/connections/create', [PanelConnectionsController::class, 'create'])->name('connections.create');
    Route::post('/connections', [PanelConnectionsController::class, 'store'])->name('connections.store');
    Route::put('/connections/{connection}', [PanelConnectionsController::class, 'update'])->name('connections.update');
    Route::delete('/connections/{connection}', [PanelConnectionsController::class, 'destroy'])->name('connections.destroy');
    Route::put('/connections/{connection}/credentials/{slot}', [PanelConnectionCredentialsController::class, 'replace'])
        ->whereIn('slot', ['application', 'default-client'])
        ->name('connections.credentials.replace');
    Route::delete('/connections/{connection}/credentials/{slot}', [PanelConnectionCredentialsController::class, 'remove'])
        ->whereIn('slot', ['application', 'default-client'])
        ->name('connections.credentials.remove');
});

Route::middleware(['can:gaminghub-panel.connections.view', 'can:gaminghub-panel.connections.test'])
    ->post('/connections/{connection}/test', [PanelConnectionActionsController::class, 'test'])
    ->name('connections.test');
Route::middleware(['can:gaminghub-panel.connections.view', 'can:gaminghub-panel.servers.discover'])
    ->post('/connections/{connection}/discover', [PanelConnectionActionsController::class, 'discover'])
    ->name('connections.discover');
Route::middleware(['can:gaminghub-panel.connections.view', 'can:gaminghub-panel.providers.configure'])
    ->get('/connections/{connection}/servers', [PanelConnectionActionsController::class, 'servers'])
    ->name('connections.servers');

Route::middleware('can:gaminghub.providers.manage')->group(function (): void {
    Route::post('/games/{game}/servers/{server}/providers', [PanelProviderController::class, 'store'])->name('providers.store');
    Route::put('/games/{game}/servers/{server}/providers/{provider}', [PanelProviderController::class, 'update'])->name('providers.update');
});

Route::middleware(['can:gaminghub.providers.manage', 'can:gaminghub-panel.providers.configure'])->group(function (): void {
    Route::put('/games/{game}/servers/{server}/providers/{provider}/credentials/{slot}', [ConnectionController::class, 'replaceToken'])
        ->whereIn('slot', ['api', 'runtime'])
        ->name('providers.credentials.replace');
    Route::delete('/games/{game}/servers/{server}/providers/{provider}/credentials/{slot}', [ConnectionController::class, 'removeToken'])
        ->whereIn('slot', ['api', 'runtime'])
        ->name('providers.credentials.remove');
});

Route::middleware(['can:gaminghub.providers.view', 'can:gaminghub-panel.connections.test'])
    ->post('/games/{game}/servers/{server}/providers/{provider}/test', [ConnectionController::class, 'test'])
    ->name('providers.test');
Route::middleware(['can:gaminghub.providers.manage', 'can:gaminghub-panel.servers.discover'])
    ->post('/games/{game}/servers/{server}/providers/{provider}/discover', [ConnectionController::class, 'discover'])
    ->name('providers.discover');
Route::middleware(['can:gaminghub.providers.view', 'can:gaminghub-panel.diagnostics.view'])
    ->get('/games/{game}/servers/{server}/providers/{provider}/diagnostics', [DiagnosticsController::class, 'show'])
    ->name('providers.diagnostics');

Route::middleware('can:gaminghub-panel.settings.manage')->group(function (): void {
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
});
