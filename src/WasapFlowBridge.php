<?php

namespace WasapFlow\Bridge;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class BridgeException extends \RuntimeException
{
    public string $code;
    public int    $httpStatus;
    public array  $bridgeError;

    public function __construct(string $message, string $code = 'BRIDGE_ERROR', int $httpStatus = 0, array $bridgeError = [])
    {
        parent::__construct($message);
        $this->code        = $code;
        $this->httpStatus  = $httpStatus;
        $this->bridgeError = $bridgeError;
    }
}

class Http
{
    private Client $client;
    private string $base;

    public function __construct(string $partnerKey, string $baseUrl, float $timeout)
    {
        $this->base   = rtrim($baseUrl, '/') . '/bridge/v1';
        $this->client = new Client([
            'timeout' => $timeout,
            'headers' => [
                'x-partner-key' => $partnerKey,
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    private function handle(array $data, int $status): array
    {
        if (isset($data['success']) && $data['success'] === false) {
            $err = $data['error'] ?? [];
            throw new BridgeException($err['message'] ?? 'Bridge error', $err['code'] ?? 'BRIDGE_ERROR', $status, $err);
        }
        return $data;
    }

    public function get(string $path, array $headers = []): array
    {
        try {
            $res  = $this->client->get($this->base . $path, ['headers' => $headers]);
            return $this->handle(json_decode($res->getBody(), true), $res->getStatusCode());
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? json_decode($e->getResponse()->getBody(), true) : [];
            $err  = $body['error'] ?? [];
            throw new BridgeException($err['message'] ?? $e->getMessage(), $err['code'] ?? 'NETWORK_ERROR', $e->getCode(), $err);
        }
    }

    public function post(string $path, array $body = [], array $headers = []): array
    {
        try {
            $res  = $this->client->post($this->base . $path, ['json' => $body, 'headers' => $headers]);
            return $this->handle(json_decode($res->getBody(), true), $res->getStatusCode());
        } catch (RequestException $e) {
            $resp = $e->hasResponse() ? json_decode($e->getResponse()->getBody(), true) : [];
            $err  = $resp['error'] ?? [];
            throw new BridgeException($err['message'] ?? $e->getMessage(), $err['code'] ?? 'NETWORK_ERROR', $e->getCode(), $err);
        }
    }

    public function delete(string $path, array $headers = []): array
    {
        try {
            $res  = $this->client->delete($this->base . $path, ['headers' => $headers]);
            return $this->handle(json_decode($res->getBody(), true), $res->getStatusCode());
        } catch (RequestException $e) {
            $resp = $e->hasResponse() ? json_decode($e->getResponse()->getBody(), true) : [];
            $err  = $resp['error'] ?? [];
            throw new BridgeException($err['message'] ?? $e->getMessage(), $err['code'] ?? 'NETWORK_ERROR', $e->getCode(), $err);
        }
    }
}

class Messages
{
    public function __construct(private Http $http, private string $wabaId) {}

    private function h(): array { return ['x-waba-id' => $this->wabaId]; }

    public function send(string $to, string $text, bool $previewUrl = false): array
    {
        return $this->http->post('/messages/send', ['to' => $to, 'text' => $text, 'preview_url' => $previewUrl], $this->h());
    }

    public function template(string $to, string $name, string $language = 'ms', array $params = [], array $components = []): array
    {
        $tmpl = [
            'name'       => $name,
            'language'   => ['code' => $language],
            'components' => $components ?: ($params ? [['type' => 'body', 'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => (string)$p], $params)]] : []),
        ];
        return $this->http->post('/messages/template', ['to' => $to, 'template' => $tmpl], $this->h());
    }

    public function image(string $to, ?string $url = null, ?string $mediaId = null, ?string $caption = null): array
    {
        $media = $mediaId ? ['id' => $mediaId] : ['link' => $url];
        if ($caption) $media['caption'] = $caption;
        return $this->http->post('/messages/media', ['to' => $to, 'type' => 'image', 'media' => $media], $this->h());
    }

    public function document(string $to, ?string $url = null, ?string $mediaId = null, ?string $filename = null, ?string $caption = null): array
    {
        $media = $mediaId ? ['id' => $mediaId] : ['link' => $url];
        if ($filename) $media['filename'] = $filename;
        if ($caption)  $media['caption']  = $caption;
        return $this->http->post('/messages/media', ['to' => $to, 'type' => 'document', 'media' => $media], $this->h());
    }

    public function audio(string $to, ?string $url = null, ?string $mediaId = null): array
    {
        $media = $mediaId ? ['id' => $mediaId] : ['link' => $url];
        return $this->http->post('/messages/media', ['to' => $to, 'type' => 'audio', 'media' => $media], $this->h());
    }

    public function video(string $to, ?string $url = null, ?string $mediaId = null, ?string $caption = null): array
    {
        $media = $mediaId ? ['id' => $mediaId] : ['link' => $url];
        if ($caption) $media['caption'] = $caption;
        return $this->http->post('/messages/media', ['to' => $to, 'type' => 'video', 'media' => $media], $this->h());
    }

    public function buttons(string $to, string $body, array $buttons, ?string $header = null, ?string $footer = null): array
    {
        $interactive = [
            'type'   => 'button',
            'body'   => ['text' => $body],
            'action' => ['buttons' => array_map(fn($b) => ['type' => 'reply', 'reply' => ['id' => $b['id'], 'title' => $b['title']]], $buttons)],
        ];
        if ($header) $interactive['header'] = ['type' => 'text', 'text' => $header];
        if ($footer) $interactive['footer'] = ['text' => $footer];
        return $this->http->post('/messages/interactive', ['to' => $to, 'interactive' => $interactive], $this->h());
    }

    public function list(string $to, string $body, array $sections, string $buttonText = 'Choose', ?string $header = null, ?string $footer = null): array
    {
        $interactive = [
            'type'   => 'list',
            'body'   => ['text' => $body],
            'action' => ['button' => $buttonText, 'sections' => $sections],
        ];
        if ($header) $interactive['header'] = ['type' => 'text', 'text' => $header];
        if ($footer) $interactive['footer'] = ['text' => $footer];
        return $this->http->post('/messages/interactive', ['to' => $to, 'interactive' => $interactive], $this->h());
    }
}

class ClientScope
{
    public Messages $messages;
    public function __construct(Http $http, string $wabaId)
    {
        $this->messages = new Messages($http, $wabaId);
    }
}

class WasapFlowBridge
{
    private Http    $http;
    public  Clients  $clients;
    public  Contacts $contacts;

    /**
     * @param string $partnerKey    Partner API key (wf_live_xxx)
     * @param string $webhookSecret Webhook secret (whsec_xxx)
     * @param string $baseUrl       WasapFlow server URL
     * @param float  $timeout       HTTP timeout in seconds
     */
    public function __construct(
        string $partnerKey,
        public readonly string $webhookSecret = '',
        string $baseUrl = 'https://api.wasapflow.com',
        float $timeout = 15.0
    ) {
        if (!$partnerKey) throw new \InvalidArgumentException('partnerKey is required');
        $this->http     = new Http($partnerKey, $baseUrl, $timeout);
        $this->clients  = new Clients($this->http);
        $this->contacts = new Contacts($this->http);
    }

    public function client(string $wabaId): ClientScope
    {
        return new ClientScope($this->http, $wabaId);
    }
}

class Clients
{
    public function __construct(private Http $http) {}

    public function register(string $wabaId, string $phoneNumberId, string $accessToken, string $displayName = ''): array
    {
        return $this->http->post('/clients/register', [
            'waba_id'         => $wabaId,
            'phone_number_id' => $phoneNumberId,
            'access_token'    => $accessToken,
            'display_name'    => $displayName,
        ]);
    }

    public function list(): array  { return $this->http->get('/clients'); }

    public function remove(string $wabaId): array { return $this->http->delete("/clients/{$wabaId}"); }

    public function refresh(string $wabaId): array { return $this->http->post("/clients/{$wabaId}/refresh"); }
}

class Contacts
{
    public function __construct(private Http $http) {}

    public function check(string $phone, ?string $wabaId = null): array
    {
        return $this->http->get("/contacts/{$phone}", $wabaId ? ['x-waba-id' => $wabaId] : []);
    }

    public function uploadMedia(string $url, string $mimeType, string $wabaId, ?string $type = null): array
    {
        return $this->http->post('/media/upload', [
            'url'       => $url,
            'mime_type' => $mimeType,
            'type'      => $type ?? explode('/', $mimeType)[0],
        ], ['x-waba-id' => $wabaId]);
    }
}
