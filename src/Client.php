<?php

namespace Gmllt\CachingClient;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class Client
{

    /**
     * @param HttpClientInterface $client
     * @param CacheItemPoolInterface $itemPool
     */
    public function __construct(protected HttpClientInterface $client, protected CacheItemPoolInterface $itemPool)
    {
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $options
     * @return ResponseInterface
     * @throws TransportExceptionInterface
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $expireAfter = $options[Response::EXPIRE_AFTER] ?? null;
        $expireAt = $options[Response::EXPIRE_AT] ?? null;
        unset($options[Response::EXPIRE_AFTER]);
        unset($options[Response::EXPIRE_AT]);
        $cached = true;
        $cacheKey = $this->generateCacheKey($method, $url, $options);
        $cacheInfos = $this->getFromCache($cacheKey);
        if(null === $cacheInfos) {
            $response = $this->client->request($method, $url, $options);
            $cachedResponse = new Response($cacheKey, $response, $expireAfter, $expireAt);
            $cachedResponse->setCached(false);
            $cachedResponse->setCacheCallBack([$this, 'setInCache']);
        } else {
            $cachedResponse = Response::buildFromCacheInfos($cacheKey, (array)$cacheInfos);
            $cachedResponse->setCached(true);
        }
        return $cachedResponse;
    }

    protected function generateCacheKey(string $method, string $url, array $options = []): string
    {
        return 'hash'.hash('sha256', json_encode([$method, $url, $options]));
    }

    public function getFromCache(string $cacheKey): ?array {
        $item = $this->itemPool->getItem($cacheKey);
        if($item->isHit()) {
            return $item->get();
        }
        return null;
    }

    public function setInCache(string $cacheKey, array $cacheInfos, \DateInterval|int|null $expireAfter = null, \DateTimeInterface|null $expireAt = null) {
        $item = $this->itemPool->getItem($cacheKey);
        $item->set($cacheInfos);
        if(null !== $expireAfter) {
            $item->expiresAfter($expireAfter);
        }
        if(null !== $expireAt) {
            $item->expiresAt($expireAt);
        }
        $this->itemPool->save($item);
    }
}