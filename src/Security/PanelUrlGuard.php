<?php
namespace Azuriom\Plugin\GamingHubPanel\Security;

use Azuriom\Plugin\GamingHubPanel\Contracts\HostResolver;
use Azuriom\Plugin\GamingHubPanel\Data\ValidatedPanelUrl;
use Azuriom\Plugin\GamingHubPanel\Exceptions\UnsafePanelUrl;
use Azuriom\Plugin\GamingHubPanel\Settings\PanelSettings;

final class PanelUrlGuard
{
    public function __construct(private HostResolver $resolver, private PanelSettings $settings) {}

    public function validate(string $url, ?bool $allowPrivate = null): ValidatedPanelUrl
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (! is_array($parts)) {
            throw new UnsafePanelUrl('Malformed panel URL.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $settings = $this->settings->all();
        if (! in_array($scheme, ['https', 'http'], true)) {
            throw new UnsafePanelUrl('Only HTTPS and explicitly trusted HTTP URLs are supported.');
        }
        if ($scheme !== 'https' && ! $settings['allow_insecure_http']) {
            throw new UnsafePanelUrl('HTTPS is required unless insecure HTTP is explicitly enabled.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafePanelUrl('Embedded URL credentials are forbidden.');
        }
        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new UnsafePanelUrl('Panel URL must not include a query or fragment.');
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }
        if ($host === '' || strlen($host) > 253 || ! $this->validHost($host)) {
            throw new UnsafePanelUrl('Panel host is invalid.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($port < 1 || $port > 65535) {
            throw new UnsafePanelUrl('Panel port is invalid.');
        }

        $addresses = $this->resolver->resolve($host);
        if ($addresses === []) {
            throw new UnsafePanelUrl('Panel host could not be resolved.');
        }

        $permitPrivate = $allowPrivate ?? (bool) $settings['allow_private_hosts'];
        foreach ($addresses as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new UnsafePanelUrl('Panel host returned an invalid network address.');
            }
            if (! $this->isPublic($ip) && ! $permitPrivate) {
                throw new UnsafePanelUrl('Private or reserved panel hosts require explicit administrator trust.');
            }
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $authority = (str_contains($host, ':') ? '['.$host.']' : $host)
            .((($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) ? '' : ':'.$port);

        return new ValidatedPanelUrl($scheme.'://'.$authority.$path, $scheme, $host, $port, $addresses);
    }

    public function validateRedirect(ValidatedPanelUrl $origin, string $location, ?string $currentUrl = null): ValidatedPanelUrl
    {
        $target = $this->absolute($currentUrl ?? $origin->url, $location);
        $validated = $this->validate($target);

        if (strcasecmp($validated->host, $origin->host) !== 0
            || $validated->port !== $origin->port
            || $validated->scheme !== $origin->scheme) {
            throw new UnsafePanelUrl('Cross-host redirects are rejected so credentials are never forwarded.');
        }

        return $validated;
    }

    private function absolute(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new UnsafePanelUrl('Redirect base URL is invalid.');
        }

        $host = trim((string) $parts['host'], '[]');
        $originHost = str_contains($host, ':') ? '['.$host.']' : $host;
        $origin = $parts['scheme'].'://'.$originHost.(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($location, '/')) {
            return $origin.$this->normalizePath($location);
        }

        $directory = rtrim(str_replace('\\', '/', dirname($parts['path'] ?? '/')), '/');

        return $origin.$this->normalizePath(($directory === '' ? '' : $directory).'/'.$location);
    }

    private function normalizePath(string $path): string
    {
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return '/'.implode('/', $segments);
    }

    private function validHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        return preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/D',
            $host,
        ) === 1;
    }

    private function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
