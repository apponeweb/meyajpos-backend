<?php

namespace App\Service\WhatsApp;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiResponseService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $openAiApiKey
    ) {
    }

    public function isEnabled(): bool
    {
        return trim($this->openAiApiKey) !== '';
    }
}