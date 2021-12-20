<?php

namespace Gmllt\CachingClient;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class Response implements ResponseInterface
{
    public const EXPIRE_AT = 'expireAt';
    public const EXPIRE_AFTER = 'expireAfter';

    protected const STATUS_CDDE = 'statusCode';
    protected const CONTENT = 'content';
    protected const HEADERS = 'headers';
    protected const INFOS = 'infos';

    /**
     * @var callable|null
     */
    protected mixed $cacheCallBack = null;

    protected int $statusCode = 0;

    protected string $content = '';

    protected array $headers = [];

    protected array $infos = [];

    /**
     * @var bool
     */
    protected bool $cached = false;

    public function __construct(protected string $cacheKey, protected ?ResponseInterface $response = null, protected \DateInterval|int|null $expireAfter = null, protected \DateTimeInterface|null $expireAt = null)
    {
    }

    /**
     * @inheritDoc
     */
    public function getStatusCode(): int
    {
        $this->statusCode = !empty($this->statusCode) ? $this->statusCode : $this->response->getStatusCode() ?? 0;
        $this->processCache();
        return $this->statusCode;
    }

    /**
     * @inheritDoc
     */
    public function getHeaders(bool $throw = true): array
    {
        $this->headers = !empty($this->headers) ? $this->headers : $this->response?->getHeaders($throw) ?? [];
        $this->processCache();
        return $this->headers;
    }

    /**
     * @inheritDoc
     */
    public function getContent(bool $throw = true): string
    {
        $this->content = !empty($this->content) ? $this->content : $this->response?->getContent($throw) ?? '';
        $this->processCache();
        return $this->content;
    }

    /**
     * @inheritDoc
     */
    public function toArray(bool $throw = true): array
    {
        $array = json_decode($this->getContent($throw), true) ?? [];
        $this->processCache();
        return $array;
    }

    /**
     * @inheritDoc
     */
    public function cancel(): void
    {
        $this->response->cancel();
    }

    /**
     * @inheritDoc
     */
    public function getInfo(string $type = null): mixed
    {
        $this->infos = !empty($this->infos) ? $this->infos : $this->response?->getInfo() ?? [];
        return (null === $type) ? $this->infos : $this->infos[$type] ?? null;
    }

    protected function getCacheInfos(): array
    {
        $infos = $this->getInfo();
        unset($infos['pause_handler']);
        return [
            self::STATUS_CDDE => $this->getStatusCode(),
            self::CONTENT => $this->getContent(false),
            self::HEADERS => $this->getHeaders(false),
            self::INFOS => $infos,
        ];
    }

    public function setCacheCallBack(mixed $callBack): void
    {
        $this->cacheCallBack = $callBack;
    }

    public function getCacheCallBack(): ?callable
    {
        return $this->cacheCallBack;
    }

    /**
     * @return bool
     */
    public function isCached(): bool
    {
        return $this->cached;
    }

    /**
     * @param bool $cached
     */
    public function setCached(bool $cached): void
    {
        $this->cached = $cached;
    }

    public function processCache() {
        if(!$this->isCached() && null !== $this->getCacheCallBack()) {
            $this->cached = true;
            call_user_func_array($this->getCacheCallBack(), [
                $this->cacheKey,
                $this->getCacheInfos(),
                $this->expireAfter,
                $this->expireAt
            ]);
            $this->cached = true;
        }
    }

    public static function buildFromCacheInfos(string $cacheKey, array $cacheInfos): self
    {
        $response = new self($cacheKey);
        $response->statusCode = $cacheInfos[self::STATUS_CDDE] ?? 0;
        $response->content = $cacheInfos[self::CONTENT] ?? '';
        $response->headers = $cacheInfos[self::HEADERS] ?? [];
        $response->infos = $cacheInfos[self::INFOS] ?? [];
        return $response;
    }

}