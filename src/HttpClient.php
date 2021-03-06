<?php

namespace RemoteAuthPhp;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use Psr\SimpleCache\CacheInterface;
use Carbon\Carbon;

class HttpClient
{
    /** @var array */
    private $options;

    /** @var GuzzleClient */
    private $http;

    /** @var CacheInterface */
    private $cache;

    /** @var bool */
    private $attemptingRefresh = false;

    /**
     * Creates a new RemoteAuthPhp Client.
     *
     * @param array $options
     */
    public function __construct(
        array $options = [],
        ?CacheInterface $cache = null,
        ?GuzzleClient $http = null
    ) {
        $this->options = $options;

        $this->cache = $cache;

        if (is_null($http)) {
            $http = new GuzzleClient();
        }
        $this->http = $http;
    }

    /**
     * Performs a synchronous GET request to the given URL.
     *
     * Returns the response
     *
     * @param string $url
     * @param RemoteAuthUser $user
     * @return array
     */
    public function get(string $url, RemoteAuthUser $user, ?bool $ignoreCache = false)
    {
        return $this->request('GET', $url, $user, null, $ignoreCache);
    }

    /**
     * Performs a synchronous POST request to the given URL.
     *
     * Returns the response
     *
     * @param string $url
     * @param RemoteAuthUser $user
     * @param array $payload
     * @return array
     */
    public function post(string $url, RemoteAuthUser $user, ?array $payload = [])
    {
        return $this->request('POST', $url, $user, $payload);
    }

    /**
     * Performs a synchronous PUT request to the given URL.
     *
     * Returns the response
     *
     * @param string $url
     * @param RemoteAuthUser $user
     * @param array $payload
     * @return array
     */
    public function put(string $url, RemoteAuthUser $user, ?array $payload = [])
    {
        return $this->request('PUT', $url, $user, $payload);
    }

    /**
     * Performs a synchronous DELETE request to the given URL.
     *
     * Returns the response
     *
     * @param string $url
     * @param RemoteAuthUser $user
     * @param array $payload
     * @return array
     */
    public function delete(string $url, RemoteAuthUser $user, ?array $payload = [])
    {
        return $this->request('DELETE', $url, $user, $payload);
    }

    /**
     * Makes a HTTP request.
     *
     * Adds Authentication header for authenticating the user.
     *
     * @param string $method
     * @param string $url
     * @param RemoteAuthUser $user
     * @param array $payload
     * @return array
     */
    public function request(string $method, string $url, RemoteAuthUser $user, ?array $payload = [], ?bool $ignoreCache = false)
    {
        try {
            $cacheKey = $this->getCacheKey($user, $method, $url, $payload);
            $isCacheAvailable = strtoupper($method) === 'GET' && !is_null($this->cache);

            if ($isCacheAvailable && !$ignoreCache && $this->cache->has($cacheKey)) {
                return $this->cache->get($cacheKey);
            }

            $response = $this->http->request($method, $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $user->accessToken()
                ],
                'json' => !empty($payload) ? $payload : null
            ]);
        } catch (ClientException $e) {
            if ($e->getCode() === 401 && !$this->attemptingRefresh) {
                // Request is unauthorized, use refresh token to get a new access token
                $this->attemptingRefresh = true;
    
                $response = $this->post(
                    $this->options['baseUrl'] . '/oauth/token',
                    $user,
                    [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $user->refreshToken(),
                        'client_id' => $this->options['clientId'],
                        'client_secret' => $this->options['clientSecret'],
                        'scope' => $this->options['scope']
                    ]
                );

                if (isset($response['error'])) {
                    throw new \Exception($response['message']);
                }
                
                $user->handleTokenRefresh(
                    $response['id'],
                    $response['access_token'],
                    $response['refresh_token'],
                    $response['expires_in'],
                    $response['user']['email_verified_at']
                );
    
                $this->attemptingRefresh = false;
    
                return $this->request($method, $url, $user, $payload);
            }

            $errorMessage = 'An unknown error occurred.';

            if ($e && $e->getResponse()) {
                $errorMessage = (string)$e->getResponse()->getBody();
            }

            return [
                'error' => true,
                'message' => $errorMessage
            ];
        }
        
        $responseBody = null;
        if ($response) {
            $responseBody = (string)$response->getBody();
        }

        $result = json_decode($responseBody, true);

        if ($isCacheAvailable) {
            $this->cache->set($cacheKey, $result, Carbon::now()->addHour(1));
        }
            
        return $result;
    }

    /**
     * Helper function to generate the URL to call.
     *
     * @param string $url
     * @return string
     */
    public function url(string $url)
    {
        return $this->options['baseUrl'] . '/api/v1/' . $url;
    }

    /**
     * Builds the cache key for caching GET requests.
     *
     * @param RemoteAuthUser $user
     * @param string $method
     * @param string $url
     * @param array $payload
     * @return string
     */
    public function getCacheKey(RemoteAuthUser $user, string $method, string $url, ?array $payload = [])
    {
        return md5($user->remoteAuthUserId() . '-' . $method . '-' . $url . '-' . json_encode($payload));
    }
}
