<?php

namespace WasapFlow\Bridge;

class Webhook
{
    public function __construct(private string $secret) {}

    /**
     * Verify signature and return parsed event, or null if invalid.
     *
     * Example (Laravel):
     *   $event = $webhook->verify(request()->headers->all(), request()->getContent());
     *   if (!$event) return response('Invalid', 401);
     *   return response('OK');
     *
     * @param array        $headers  Associative array of request headers
     * @param string|null  $rawBody  Raw request body string
     * @return array|null Parsed event, or null if signature invalid
     */
    public function verify(array $headers, ?string $rawBody): ?array
    {
        $sig = $headers['x-wasapflow-signature'][0]
            ?? $headers['X-Wasapflow-Signature'][0]
            ?? $headers['x-wasapflow-signature']
            ?? $headers['X-Wasapflow-Signature']
            ?? null;

        if (!$sig || !$this->secret) return null;

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody ?? '', $this->secret);

        if (!hash_equals($expected, $sig)) return null;

        return json_decode($rawBody, true);
    }

    public function isValid(array $headers, ?string $rawBody): bool
    {
        return $this->verify($headers, $rawBody) !== null;
    }
}
