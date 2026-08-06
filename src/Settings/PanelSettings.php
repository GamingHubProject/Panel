<?php
namespace Azuriom\Plugin\GamingHubPanel\Settings;
use Azuriom\Models\Setting;
use Illuminate\Support\Facades\Cache;
class PanelSettings
{
    private const PREFIX = 'gaming-hub-panel.';
    public function all(): array { return [
        'default_timeout' => $this->boundedInt('default_timeout', (int) config('gaming-hub-panel.default_timeout', 8), 2, 30),
        'default_ttl' => $this->boundedInt('default_ttl', (int) config('gaming-hub-panel.default_ttl', 15), 5, 300),
        'default_tls_verify' => $this->bool('default_tls_verify', true),
        'allow_private_hosts' => $this->bool('allow_private_hosts', false),
        'allow_insecure_http' => $this->bool('allow_insecure_http', false),
        'prerelease_warnings' => $this->bool('prerelease_warnings', true),
    ]; }
    public function save(array $values): void
    {
        Setting::updateSettings([
            self::PREFIX.'default_timeout' => (int) $values['default_timeout'],
            self::PREFIX.'default_ttl' => (int) $values['default_ttl'],
            self::PREFIX.'default_tls_verify' => (bool) ($values['default_tls_verify'] ?? true),
            self::PREFIX.'allow_private_hosts' => (bool) ($values['allow_private_hosts'] ?? false),
            self::PREFIX.'allow_insecure_http' => (bool) ($values['allow_insecure_http'] ?? false),
            self::PREFIX.'prerelease_warnings' => (bool) ($values['prerelease_warnings'] ?? false),
        ]);
        Cache::forget('settings'); Cache::forget('azuriom.settings');
    }
    private function boundedInt(string $key, int $default, int $min, int $max): int { $v=(int) setting(self::PREFIX.$key,$default); return max($min,min($max,$v)); }
    private function bool(string $key,bool $default): bool { return filter_var(setting(self::PREFIX.$key,$default), FILTER_VALIDATE_BOOL); }
}
