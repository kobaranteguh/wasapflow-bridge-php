# Deprecated — do not use

**This SDK is no longer maintained and is not part of the WasapFlow Bridge
documentation.** It was retired on 21 August 2026.

## Why

Bridge is plain REST — two headers, JSON in, JSON out. A client library added a
dependency to version and audit, and it lagged the API: this SDK wraps roughly 13
endpoints, while Bridge now has **68**. That gap taught people the API was smaller
than it is, and integrations built workarounds for things Bridge already did.

Checking a month of production access logs, **no request to Bridge came from this
SDK.** Every partner was already calling over plain HTTP.

## What to use instead

Call the API directly with whatever HTTP client your stack already has — `fetch`,
axios, Guzzle, `requests`, `httpx`, `curl`. One small wrapper covers all 68
endpoints:

```javascript
const BASE = 'https://officialapi.wasapflow.com/bridge/v1';

async function bridge(path, { method = 'GET', wabaId, body } = {}) {
    const res = await fetch(BASE + path, {
        method,
        headers: {
            'x-partner-key': process.env.WF_PARTNER_KEY,
            ...(wabaId ? { 'x-waba-id': wabaId } : {}),
            'Content-Type': 'application/json',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json();
    if (!data.success) {
        const e = new Error(`${data.error.code}: ${data.error.message}`);
        e.code = data.error.code;
        e.metaCode = data.error.meta_code;   // branch on this, never on message text
        e.status = res.status;
        throw e;
    }
    return data;
}
```

**All fields are `snake_case`** — requests and responses alike: `waba_id`,
`phone_number_id`, `access_token`. This SDK used camelCase internally, so any code
copied from it that reads `client.wabaId` is reading a field the API never
returns.

## Documentation

- API reference — https://github.com/kobaranteguh/api
- Getting started guide — https://github.com/kobaranteguh/guide
- Changelog — https://github.com/kobaranteguh/changelog
- Docs site — https://partner.wasapflow.com/bridge/docs

Already integrated? The guide's **Prompt C** is written for an AI coding
assistant and audits an existing integration against every change, including
replacing this SDK.

## If you still have this installed

It will keep working, but it is unsupported and knows nothing about the 32
endpoints added in Bridge 2.9.0 — QR codes, conversational automation, blocking,
commerce settings, call settings, phone status, WABA diagnostics, Flows, number
lifecycle, calling and groups. Replace it with the wrapper above.
