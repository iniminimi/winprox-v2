<?php

namespace App\Contracts;

use App\Data\Notifications\WebPushResult;

interface WebPushClient
{
    public function send(string $endpoint, string $publicKey, string $authToken, string $payload): WebPushResult;
}
