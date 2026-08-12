<?php
namespace Azuriom\Plugin\GamingHubPanel\Http;

use Azuriom\Plugin\GamingHubPanel\Data\{PanelConnection, PanelHttpResponse};
use Azuriom\Plugin\GamingHubPanel\Exceptions\{PanelApiException, UnsafePanelUrl};
use Azuriom\Plugin\GamingHubPanel\Security\PanelUrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\{Crypt, Http};

class SafePanelHttpClient
{
    public function __construct(private PanelUrlGuard $guard) {}

    public function get(
        PanelConnection $connection,
        string $path,
        array $query = [],
        string $credential = 'client',
    ): PanelHttpResponse {
        if (! in_array($credential, ['application', 'client'], true)) {
            throw new PanelApiException('configuration_invalid', 'Unknown credential slot.');
        }

        try {
            $base = $this->guard->validate($connection->baseUrl);
        } catch (UnsafePanelUrl $e) {
            $category = str_contains(strtolower($e->getMessage()), 'resolved')
                ? 'connection_failed'
                : 'configuration_invalid';
            throw new PanelApiException($category, $e->getMessage());
        }

        $token = $this->decrypt(
            $credential === 'application'
                ? $connection->encryptedApplicationToken
                : $connection->encryptedClientToken,
        );
        if ($token === null) {
            throw new PanelApiException('configuration_invalid', 'The required API token is not configured.');
        }

        $url = $base->url.'/'.ltrim($path, '/');
        if ($query !== []) {
            $url .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $redirects = 0;
        $attempt = 0;
        $maxRetries = max(0, min(2, (int) config('gaming-hub-panel.max_retries', 1)));
        $maxRedirects = max(0, min(5, (int) config('gaming-hub-panel.max_redirects', 2)));
        $maxResponseBytes = max(1024, min(4 * 1024 * 1024, (int) config('gaming-hub-panel.max_response_bytes', 2 * 1024 * 1024)));

        while (true) {
            $started = microtime(true);

            try {
                $options = [
                    'connect_timeout' => min(5, $connection->timeout),
                    'timeout' => $connection->timeout,
                    'allow_redirects' => false,
                    'verify' => $connection->verifySsl,
                ];

                if (defined('CURLOPT_RESOLVE') && ! filter_var($base->host, FILTER_VALIDATE_IP)) {
                    $resolved = implode(',', array_map(
                        fn (string $ip): string => str_contains($ip, ':') ? '['.$ip.']' : $ip,
                        $base->addresses,
                    ));
                    $options['curl'] = [
                        CURLOPT_RESOLVE => [$base->host.':'.$base->port.':'.$resolved],
                    ];
                }

                $response = Http::withOptions($options)
                    ->acceptJson()
                    ->withToken($token)
                    ->withHeaders(['User-Agent' => 'Gaming-Hub-Panel/0.2.2'])
                    ->get($url);
            } catch (ConnectionException $e) {
                if ($attempt++ < $maxRetries) {
                    continue;
                }

                $message = strtolower($e->getMessage());
                $timeout = str_contains($message, 'timed out') || str_contains($message, 'timeout');
                throw new PanelApiException(
                    $timeout ? 'timeout' : 'connection_failed',
                    $timeout ? 'Panel request timed out.' : 'Panel connection failed.',
                );
            }

            $latency = (int) round((microtime(true) - $started) * 1000);
            $status = $response->status();

            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                if (++$redirects > $maxRedirects) {
                    throw new PanelApiException('connection_failed', 'Panel redirect limit exceeded.', $status);
                }

                $location = $response->header('Location');
                if (! is_string($location) || $location === '') {
                    throw new PanelApiException('invalid_response', 'Panel returned an invalid redirect.', $status);
                }

                try {
                    $next = $this->guard->validateRedirect($base, $location, $url);
                } catch (UnsafePanelUrl $e) {
                    throw new PanelApiException('connection_failed', $e->getMessage(), $status);
                }

                $base = $next;
                $url = $next->url;
                continue;
            }

            if (in_array($status, [401, 403], true)) {
                throw new PanelApiException('authentication_failed', 'Authentication or permission check failed.', $status);
            }
            if ($status === 404) {
                throw new PanelApiException('unavailable', 'The panel server identifier is missing or inaccessible.', $status);
            }
            if ($status === 429) {
                if ($attempt++ < $maxRetries) {
                    $wait = min(2, max(0, (int) $response->header('Retry-After')));
                    if ($wait > 0) sleep($wait);
                    continue;
                }
                throw new PanelApiException('unavailable', 'The panel rate limit was reached.', $status);
            }
            if ($status >= 500) {
                if ($attempt++ < $maxRetries) {
                    continue;
                }
                throw new PanelApiException('unavailable', 'The panel is temporarily unavailable.', $status);
            }
            if ($status < 200 || $status >= 300) {
                throw new PanelApiException('unknown_error', 'The panel returned an unexpected HTTP status.', $status);
            }

            $body = $response->body();
            if (strlen($body) > $maxResponseBytes) {
                throw new PanelApiException('invalid_response', 'Panel response exceeded the supported size limit.', $status);
            }

            return new PanelHttpResponse($status, $response->headers(), $body, $latency);
        }
    }

    private function decrypt(?string $ciphertext): ?string
    {
        if (! filled($ciphertext)) {
            return null;
        }

        try {
            return Crypt::decryptString($ciphertext);
        } catch (\Throwable) {
            throw new PanelApiException('configuration_invalid', 'Stored credential cannot be decrypted.');
        }
    }
}
