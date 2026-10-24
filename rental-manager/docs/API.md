# API reference

## Scope

This MVP exposes a small, versioned read endpoint for the signed-in workspace. It is **not** a public, token-authenticated integration platform. Writes use Laravel web forms, session authentication, CSRF and the same server business rules. No API keys or bearer tokens are created. A third-party integration should add Sanctum and separately scoped tokens rather than reusing staff session cookies.

## Availability

`GET /api/v1/availability?starts_on=2026-11-01&ends_on=2026-11-03`

Requires a signed-in, active staff account with a personal password set. Every role has read permission. Limit: 60 requests/minute. Dates use `YYYY-MM-DD` and are inclusive. Missing dates default to today; when providing dates, send both. Send `Accept: application/json` for predictable JSON authentication and validation errors. Authentication is the existing same-origin session cookie, set by `POST /login` after CSRF-protected login; it is not passed through the URL.

```json
{
  "starts_on": "2026-11-01",
  "ends_on": "2026-11-03",
  "data": [
    {
      "id": 1,
      "sku": "CHR-001",
      "name": "Cross-back oak chair",
      "quantity": 180,
      "available": 140,
      "daily_rate_cents": 18000
    }
  ]
}
```

`quantity` is owned stock, not immediately free stock. `available` accounts for peak overlapping confirmed reservations, all checked-out units and open maintenance holds. Values are advisory: a successful booking confirmation rechecks stock inside a transaction. Amounts are integer cents in the configured business currency. Archived equipment is excluded.

| Status | Meaning |
| --- | --- |
| 200 | Success |
| 401 | Not authenticated, when JSON is requested |
| 302 | Web authentication redirect; also inactive/forced-password-change redirect |
| 403 | Role restriction on protected web actions |
| 422 | Invalid input; JSON contains `message` and field-keyed `errors` |
| 429 | Rate limit exceeded |

## Web action map

| Method | Route | Capability |
| --- | --- | --- |
| POST | `/bookings` | bookings |
| PUT | `/bookings/{id}` | bookings, quote only |
| POST | `/bookings/{id}/actions/confirm` | bookings |
| POST | `/bookings/{id}/actions/cancel` | bookings |
| POST | `/bookings/{id}/actions/checkout` | operations |
| POST | `/bookings/{id}/actions/return` | operations |
| POST | `/bookings/{id}/payments` | finance |
| GET | `/bookings/{id}/print` | view |
| GET | `/reports/export?from=YYYY-MM-DD&to=YYYY-MM-DD` | reports |
| POST/PUT | `/inventory[/{id}]`, `/customers[/{id}]` | inventory/customers |
| POST | `/maintenance`, `/maintenance/{id}/complete` | operations |
| PUT | `/settings` | admin |
| POST/PUT | `/users[/{id}]` | admin |

All web writes require the session's CSRF token. PUT forms use `_method=PUT` with a POST and `_token`. Form validation returns redirect-with-errors for HTML, or 422 for JSON. See controller validation and the user manual for exact fields. Payment forms include `request_key`, a UUID used for idempotency. Do not retry with a fresh key unless you intend a new transaction.

Machine-readable availability contract: [`openapi.yaml`](openapi.yaml).
