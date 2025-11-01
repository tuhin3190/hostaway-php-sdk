<?php

namespace RanaTuhin\HostawaySDK;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use RanaTuhin\HostawaySDK\Exceptions\AuthenticationException;
use RanaTuhin\HostawaySDK\Exceptions\HostawayException;

/**
 * HostawayClient
 *
 * Authenticates via /v1/accessTokens and manages API resources.
 */
class HostawayClient
{
    protected GuzzleClient $http;
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected ?string $accessToken = null;
    protected string $baseUri;
    protected float $timeout;

    public function __construct(array $config = [])
    {
        $this->clientId = $config['client_id'] ?? null;
        $this->clientSecret = $config['client_secret'] ?? null;
        $this->baseUri = rtrim($config['base_uri'] ?? 'https://api.hostaway.com/v1/', '/') . '/';
        $this->timeout = isset($config['timeout']) ? (float)$config['timeout'] : 30.0;

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new AuthenticationException('client_id and client_secret are required for Hostaway API.');
        }

        $this->http = new GuzzleClient([
            'base_uri' => $this->baseUri,
            'timeout'  => $this->timeout,
            'headers'  => [
                'Cache-Control' => 'no-cache',
                'User-Agent'    => 'RanaTuhin/HostawaySDK',
            ],
        ]);

        $this->authenticate();
    }

    /**
     * Authenticate and retrieve access token
     */
    protected function authenticate(): void
    {
        try {
            $response = $this->http->post('accessTokens', [
                'form_params' => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope'         => 'general',
                ],
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            ]);

            $data = json_decode((string)$response->getBody(), true);

            if (empty($data['access_token'])) {
                throw new AuthenticationException('No access_token returned from Hostaway.');
            }

            $this->accessToken = $data['access_token'];
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Failed to get Hostaway access token: ' . $e->getMessage());
        }
    }

    /**
     * Generic request helper
     */
    public function request(string $method, string $uri, array $options = []): ?array
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Cache-Control' => 'no-cache',
                'Accept'        => 'application/json',
            ]
        );

        try {
            $response = $this->http->request($method, ltrim($uri, '/'), $options);
            $body = (string)$response->getBody();

            return $body ? json_decode($body, true) : null;
        } catch (GuzzleException $e) {
            $respBody = method_exists($e, 'getResponse') && $e->getResponse()
                ? (string)$e->getResponse()->getBody()
                : $e->getMessage();

            throw new HostawayException("Request failed: {$respBody}", $e->getCode(), $e);
        }
    }

    // Shorthand HTTP methods
    public function get(string $uri, array $query = []): ?array
    {
        return $this->request('GET', $uri, ['query' => $query]);
    }

    public function post(string $uri, array $data = []): ?array
    {
        return $this->request('POST', $uri, ['json' => $data]);
    }

    public function put(string $uri, array $data = []): ?array
    {
        return $this->request('PUT', $uri, ['json' => $data]);
    }

    public function delete(string $uri): ?array
    {
        return $this->request('DELETE', $uri);
    }

    // ----------------------------
    // Resource accessors
    // ----------------------------

    public function listings(): Resources\Listings
    {
        return new Resources\Listings($this);
    }

    public function reservations(): Resources\Reservations
    {
        return new Resources\Reservations($this);
    }

    public function messages(): Resources\Messages
    {
        return new Resources\Messages($this);
    }

    public function channels(): Resources\Channels
    {
        return new Resources\Channels($this);
    }

    public function calendar(): Resources\Calendar
    {
        return new Resources\Calendar($this);
    }

    public function guests(): Resources\Guests
    {
        return new Resources\Guests($this);
    }

    public function tasks(): Resources\Tasks
    {
        return new Resources\Tasks($this);
    }

    public function users(): Resources\Users
    {
        return new Resources\Users($this);
    }

    public function httpClient(): GuzzleClient
    {
        return $this->http;
    }
}

