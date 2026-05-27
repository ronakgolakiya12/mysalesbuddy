<?php

declare(strict_types=1);

namespace App\Exceptions;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class RecallApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(ResponseInterface $response, ?Throwable $previous = null): self
    {
        $body = (string) $response->getBody();
        $context = [];
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $context = $decoded;
        }

        return new self(
            sprintf('Recall.ai API error (%d): %s', $response->getStatusCode(), $body),
            $response->getStatusCode(),
            $context,
            $previous
        );
    }

    public static function fromThrowable(Throwable $previous, string $message = 'Recall.ai API request failed'): self
    {
        return new self($message.': '.$previous->getMessage(), null, [], $previous);
    }
}
