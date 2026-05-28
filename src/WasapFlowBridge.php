<?php

namespace WasapFlow\Bridge;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// ─── Exception ───────────────────────────────────────────────────────────────

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

// ─── HTTP client ─────────────────────────────────────────────────────────────

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
            $res = $this->client->get($this->base . $path, ['headers' => $headers]);
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
            $res = $this->client->post($this->base . $path, ['json' => $body, 'headers' => $headers]);
            return $this->handle(json_decode($res->getBody(), true), $res->getStatusCode());
        } catch (RequestException $e) {
            $resp = $e->hasResponse() ? json_decode($e->getResponse()->getBody(), true) : [];
            $err  = $resp['error'] ?? [];
            throw new BridgeException($err['message'] ?? $e->getMessage(), $err['code'] ?? 'NETWORK_ERROR', $e->getCode(), $err);
        }
    }

    public function put(string $path, array $body = [], array $headers = []): array
    {
        try {
            $res = $this->client->put($this->base . $path, ['json' => $body, 'headers' => $headers]);
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
            $res = $this->client->delete($this->base . $path, ['headers' => $headers]);
            return $this->handle(json_decode($res->getBody(), true), $res->getStatusCode());
        } catch (RequestException $e) {
            $resp = $e->hasResponse() ? json_decode($e->getResponse()->getBody(), true) : [];
            $err  = $resp['error'] ?? [];
            throw new BridgeException($err['message'] ?? $e->getMessage(), $err['code'] ?? 'NETWORK_ERROR', $e->getCode(), $err);
        }
    }
}

// ─── Messages ────────────────────────────────────────────────────────────────

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
            'components' => $components ?: ($params
                ? [['type' => 'body', 'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => (string)$p], $params)]]
                : []),
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

    /** Send a location pin. */
    public function location(string $to, float $latitude, float $longitude, ?string $name = null, ?string $address = null): array
    {
        $payload = ['to' => $to, 'latitude' => $latitude, 'longitude' => $longitude];
        if ($name)    $payload['name']    = $name;
        if ($address) $payload['address'] = $address;
        return $this->http->post('/messages/location', $payload, $this->h());
    }

    /** Send an emoji reaction to a received message. */
    public function reaction(string $to, string $messageId, string $emoji): array
    {
        return $this->http->post('/messages/reaction', ['to' => $to, 'message_id' => $messageId, 'emoji' => $emoji], $this->h());
    }

    /** Mark a received message as read. */
    public function markRead(string $messageId): array
    {
        return $this->http->post('/messages/read', ['message_id' => $messageId], $this->h());
    }
}

// ─── Templates ───────────────────────────────────────────────────────────────

class Templates
{
    public function __construct(private Http $http) {}

    public function list(string $wabaId): array
    {
        return $this->http->get('/templates', ['x-waba-id' => $wabaId]);
    }

    public function create(string $wabaId, string $name, string $language, string $category, array $components): array
    {
        return $this->http->post('/templates', [
            'name' => $name, 'language' => $language, 'category' => $category, 'components' => $components,
        ], ['x-waba-id' => $wabaId]);
    }

    public function delete(string $wabaId, string $templateName): array
    {
        return $this->http->delete('/templates/' . rawurlencode($templateName), ['x-waba-id' => $wabaId]);
    }
}

// ─── Broadcasts ──────────────────────────────────────────────────────────────

class Broadcasts
{
    public function __construct(private Http $http) {}

    public function create(
        string $wabaId,
        string $templateName,
        array  $contacts,
        string $templateLanguage   = 'en_US',
        array  $templateComponents = [],
        ?string $name              = null,
        ?string $scheduledAt       = null
    ): array {
        return $this->http->post('/broadcasts', [
            'template_name'       => $templateName,
            'template_language'   => $templateLanguage,
            'template_components' => $templateComponents,
            'contacts'            => $contacts,
            'name'                => $name,
            'scheduled_at'        => $scheduledAt,
        ], ['x-waba-id' => $wabaId]);
    }

    public function list(int $limit = 20, int $offset = 0): array
    {
        return $this->http->get("/broadcasts?limit={$limit}&offset={$offset}");
    }

    public function get(int|string $broadcastId): array
    {
        return $this->http->get("/broadcasts/{$broadcastId}");
    }

    public function cancel(int|string $broadcastId): array
    {
        return $this->http->post("/broadcasts/{$broadcastId}/cancel");
    }
}

// ─── Analytics ───────────────────────────────────────────────────────────────

class Analytics
{
    public function __construct(private Http $http) {}

    public function get(string $wabaId, int $days = 7): array
    {
        return $this->http->get("/analytics?days={$days}", ['x-waba-id' => $wabaId]);
    }
}

// ─── Profile ─────────────────────────────────────────────────────────────────

class Profile
{
    public function __construct(private Http $http) {}

    public function get(string $wabaId): array
    {
        return $this->http->get('/profile', ['x-waba-id' => $wabaId]);
    }

    public function update(string $wabaId, array $fields): array
    {
        return $this->http->put('/profile', $fields, ['x-waba-id' => $wabaId]);
    }
}

// ─── Clients & Contacts ──────────────────────────────────────────────────────

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

    /** Register a WABA using an Embedded Signup code. Token exchange happens server-side. */
    public function registerFromCode(string $code, string $displayName = '', string $connectionMode = 'coexistence'): array {
        return $this->http->post('/clients/register-from-code', [
            'code' => $code, 'display_name' => $displayName, 'connection_mode' => $connectionMode,
        ]);
    }

    /** Get Meta App ID and Config ID for your Embedded Signup frontend. */
    public function getEmbeddedSignupConfig(): array { return $this->http->get('/embedded-signup/config'); }

    /**
     * Get the WasapFlow hosted Embedded Signup popup URL.
     * Open this URL as a popup from your frontend.
     * FB.init runs on officialapi.wasapflow.com — Meta only sees WasapFlow.
     * Listen for postMessage with type='WASAPFLOW_CONNECT_SUCCESS'.
     */
    public function getConnectUrl(string $displayName = ''): string {
        $base = rtrim($this->http->getBaseUrl(), '/');
        return $base . '/bridge/connect?partner_key=' . urlencode($this->http->getPartnerKey()) . '&display_name=' . urlencode($displayName);
    }

    public function list(): array  { return $this->http->get('/clients'); }
    public function remove(string $wabaId): array  { return $this->http->delete("/clients/{$wabaId}"); }

    /** Refresh quality rating + tier. Optionally update access token. */
    public function refresh(string $wabaId, string $accessToken = null): array {
        $body = $accessToken ? ['access_token' => $accessToken] : [];
        return $this->http->post("/clients/{$wabaId}/refresh", $body);
    }

    /** Reconnect Meta webhook for a WABA. Call if webhook events stop arriving. */
    public function resubscribeWebhook(string $wabaId): array {
        return $this->http->post("/clients/{$wabaId}/resubscribe-webhook");
    }
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

    /** Get a download URL for inbound media received via webhook. */
    public function downloadMedia(string $mediaId, string $wabaId): array
    {
        return $this->http->get('/media/' . rawurlencode($mediaId), ['x-waba-id' => $wabaId]);
    }
}

// ─── Per-WABA scoped wrappers ─────────────────────────────────────────────────

class ScopedTemplates
{
    public function __construct(private Templates $t, private string $wabaId) {}
    public function list(): array { return $this->t->list($this->wabaId); }
    public function create(string $name, string $language, string $category, array $components): array
        { return $this->t->create($this->wabaId, $name, $language, $category, $components); }
    public function delete(string $templateName): array { return $this->t->delete($this->wabaId, $templateName); }
}

class ScopedBroadcasts
{
    public function __construct(private Broadcasts $b, private string $wabaId) {}
    public function create(string $templateName, array $contacts, string $language = 'en_US', array $components = [], ?string $name = null, ?string $scheduledAt = null): array
        { return $this->b->create($this->wabaId, $templateName, $contacts, $language, $components, $name, $scheduledAt); }
    public function list(int $limit = 20, int $offset = 0): array { return $this->b->list($limit, $offset); }
    public function get(int|string $id): array    { return $this->b->get($id); }
    public function cancel(int|string $id): array { return $this->b->cancel($id); }
}

class ScopedAnalytics
{
    public function __construct(private Analytics $a, private string $wabaId) {}
    public function get(int $days = 7): array { return $this->a->get($this->wabaId, $days); }
}

class ScopedProfile
{
    public function __construct(private Profile $p, private string $wabaId) {}
    public function get(): array               { return $this->p->get($this->wabaId); }
    public function update(array $fields): array { return $this->p->update($this->wabaId, $fields); }
}

// ─── ClientScope ─────────────────────────────────────────────────────────────

class ClientScope
{
    public Messages        $messages;
    public ScopedTemplates  $templates;
    public ScopedBroadcasts $broadcasts;
    public ScopedAnalytics  $analytics;
    public ScopedProfile    $profile;

    public function __construct(Http $http, string $wabaId)
    {
        $this->messages   = new Messages($http, $wabaId);
        $this->templates  = new ScopedTemplates(new Templates($http), $wabaId);
        $this->broadcasts = new ScopedBroadcasts(new Broadcasts($http), $wabaId);
        $this->analytics  = new ScopedAnalytics(new Analytics($http), $wabaId);
        $this->profile    = new ScopedProfile(new Profile($http), $wabaId);
    }
}

// ─── Main SDK class ───────────────────────────────────────────────────────────

/**
 * WasapFlow Bridge PHP SDK (v1.1.0)
 *
 * Usage:
 *   $bridge = new WasapFlowBridge('wf_live_xxx', 'whsec_xxx');
 *
 *   // Per-WABA client
 *   $waba = $bridge->client('1234567890');
 *   $waba->messages->send('60123456789', 'Hello!');
 *   $waba->templates->list();
 *   $waba->broadcasts->create('my_template', ['60123456789']);
 *   $waba->analytics->get(30);
 *   $waba->profile->update(['about' => 'We reply fast!']);
 */
class WasapFlowBridge
{
    private Http $http;
    public Clients    $clients;
    public Contacts   $contacts;
    public Templates  $templates;
    public Broadcasts $broadcasts;
    public Analytics  $analytics;
    public Profile    $profile;

    public function __construct(
        string $partnerKey,
        public readonly string $webhookSecret = '',
        string $baseUrl  = 'https://api.wasapflow.com',
        float  $timeout  = 15.0
    ) {
        if (!$partnerKey) throw new \InvalidArgumentException('partnerKey is required');
        $this->http       = new Http($partnerKey, $baseUrl, $timeout);
        $this->clients    = new Clients($this->http);
        $this->contacts   = new Contacts($this->http);
        $this->templates  = new Templates($this->http);
        $this->broadcasts = new Broadcasts($this->http);
        $this->analytics  = new Analytics($this->http);
        $this->profile    = new Profile($this->http);
    }

    public function client(string $wabaId): ClientScope
    {
        return new ClientScope($this->http, $wabaId);
    }
}
