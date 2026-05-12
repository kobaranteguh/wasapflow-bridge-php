# wasapflow/bridge (PHP SDK)

Official PHP SDK for **WasapFlow Bridge**.

## Install

```bash
composer require wasapflow/bridge
```

## Usage

```php
use WasapFlow\Bridge\WasapFlowBridge;

$bridge = new WasapFlowBridge(
    partnerKey:    'wf_live_xxx',
    webhookSecret: 'whsec_xxx',
    baseUrl:       'https://api.wasapflow.com'
);

// Register WABA
$bridge->clients->register('123456789', '987654321', 'EAAxxxxx', 'Kedai ABC');

// Send text
$waba = $bridge->client('123456789');
$waba->messages->send('60123456789', 'Hello dari PHP!');

// Send template
$waba->messages->template('60123456789', 'order_confirmed', 'ms', ['John', 'RM50']);

// Send image
$waba->messages->image('60123456789', url: 'https://example.com/img.jpg', caption: 'Produk');

// Send buttons
$waba->messages->buttons('60123456789', 'Pilih pakej:', [
    ['id' => 'basic', 'title' => 'Basic RM29'],
    ['id' => 'pro',   'title' => 'Pro RM79'],
]);

// Check contact
$contact = $bridge->contacts->check('60123456789', '123456789');
echo $contact['whatsapp_id'];

// Upload media
$media = $bridge->contacts->uploadMedia('https://example.com/file.pdf', 'application/pdf', '123456789');
$waba->messages->document('60123456789', mediaId: $media['media_id'], filename: 'Invoice.pdf');
```

## Webhook (Laravel)

```php
use WasapFlow\Bridge\Webhook;

Route::post('/webhook', function (Request $request) {
    $wh    = new Webhook(secret: env('WF_WEBHOOK_SECRET'));
    $event = $wh->verify($request->headers->all(), $request->getContent());

    if (!$event) return response('Invalid signature', 401);

    match ($event['event']) {
        'message.received'     => handleIncoming($event['data']),
        'message.delivered'    => markDelivered($event['data']['message_id']),
        'waba.quality_updated' => updateQuality($event['data']),
        default                => null,
    };

    return response('OK');
});
```

## Error Handling

```php
use WasapFlow\Bridge\BridgeException;

try {
    $waba->messages->send('60123456789', 'Hello');
} catch (BridgeException $e) {
    match ($e->code) {
        'RATE_LIMIT_EXCEEDED'   => handleRateLimit(),
        'PAYMENT_FAILED'        => redirectToBilling(),
        'META_ERROR'            => logMetaError($e->bridgeError),
        default                 => logger()->error($e->getMessage()),
    };
}
```
