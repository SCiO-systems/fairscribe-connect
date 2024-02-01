<?php

namespace App\Utilities\SCIO;

use Http;
use Cache;

class TokenGenerator
{
    protected $clientID;
    protected $clientSecret;
    protected $cacheKey;
    protected $requestTimeout;
    protected $authUrl;

    // The time difference between the current time and the time the token was issued.
    public const TOKEN_CACHE_TIME_DIFF_SECONDS = 60;

    public function __construct()
    {
        $this->clientID = env('SCIO_SERVICES_CLIENT_ID');
        $this->clientSecret = env('SCIO_SERVICES_CLIENT_SECRET');
        $this->requestTimeout = env('REQUEST_TIMEOUT_SECONDS', 10);
        $this->authUrl = env('SCIO_SERVICES_AUTH_URL');
        $this->audience = env('SCIO_SERVICES_AUDIENCE');
        $this->grantType = env('SCIO_SERVICES_GRANT_TYPE');
        $this->cacheKey = 'scio_auth_token';
    }

    /**
     * Get an auth token from the SCIO Auth API and store it in the cache.
     *
     * @param bool $cache Whether to cache the token or not.
     * @return string
     */
    public function getToken($cache = true): string
    {
        if (Cache::has($this->cacheKey)) {
            return Cache::get($this->cacheKey);
        }

        $response = Http::timeout($this->requestTimeout)
            ->post($this->authUrl, [
                'client_id' => $this->clientID,
                'client_secret' => $this->clientSecret,
                'audience' => $this->audience,
                'grant_type' => $this->grantType
            ])->throw();

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in');

        if ($cache) {
            Cache::put(
                $this->cacheKey,
                $accessToken,
                $expiresIn - self::TOKEN_CACHE_TIME_DIFF_SECONDS
            );
        }

        return $accessToken;
    }
}
