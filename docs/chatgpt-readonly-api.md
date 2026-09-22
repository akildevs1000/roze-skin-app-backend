# ChatGPT read-only API

Lets ChatGPT answer questions about orders, customers and invoices — and
nothing else. It cannot create, edit or delete anything.

## Why it is safe

Four independent layers. Any one of them alone would stop a write; all four
have to be removed before the integration could change data.

| Layer | What it does | Where |
|---|---|---|
| Routes | Only `GET` routes exist under `api/chatgpt` | [routes/api.php](../routes/api.php) |
| `readonly` middleware | Rejects any method that is not GET/HEAD with a 403 | [EnforceReadOnly.php](../app/Http/Middleware/EnforceReadOnly.php) |
| Token abilities | The Sanctum token carries `read` only; `abilities:read` enforces it | [ChatGptToken.php](../app/Console/Commands/ChatGptToken.php) |
| Database role | The whole request runs as a Postgres role with `SELECT` and nothing else | [chatgpt_reader.sql](../database/readonly/chatgpt_reader.sql) |

The database role is the one that matters most — it is the only layer that
holds even if someone later writes buggy code. Verified behaviour:

```
select count(*) from orders;                      -> 5037
update orders set order_status='x' where id=1;    -> ERROR: permission denied for table orders
select email, password from users limit 1;        -> ERROR: permission denied for table users
```

It is granted SELECT on twelve business tables only. `users`,
`personal_access_tokens`, permissions and everything else are **not** readable
by the integration at all.

## Customer privacy

Phone numbers, emails and street addresses are **masked by default**:

```json
{ "name": "francis gill", "phone": "*********483", "email": "f**********@gmail.com" }
```

They are returned in full only if the token was issued with `--pii`. Decide
this deliberately — every value returned is sent to OpenAI's servers.

City, state and country stay visible either way, since delivery questions need
them and they do not identify anyone on their own.

## Setup

**1. Create the database role** (once, as a Postgres superuser):

```bash
# edit the password and database name in the file first
psql -U postgres -d "<database>" -f database/readonly/chatgpt_reader.sql
```

**2. Add the credentials to `.env`:**

```
READONLY_DB_USERNAME=chatgpt_reader
READONLY_DB_PASSWORD=<the password from step 1>
```

Host, port and database default to the normal `DB_*` values.

**3. Issue a token:**

```bash
php artisan chatgpt:token "ChatGPT Read Only"          # masked contact details
php artisan chatgpt:token "ChatGPT Full" --pii         # unmasked — think first
```

The token is shown once. It belongs to a dedicated non-master integration
user, so revoking it never locks a person out.

```bash
php artisan chatgpt:token --list
php artisan chatgpt:token --revoke=<id>
```

**4. Connect it to ChatGPT.** Import [chatgpt-openapi.yaml](chatgpt-openapi.yaml)
as the Action schema, set auth to **API Key / Bearer**, and paste the token.

> If `READONLY_DB_PASSWORD` is not set the API still works and still cannot
> write (the other three layers hold), but it runs on the normal connection
> and logs a warning. Check `storage/logs/chatgpt-*.log` after deploying.

## What it can answer

| Question | Endpoint |
|---|---|
| "Check order 61310" | `GET /orders/61310` |
| "Find invoice INV-006014" | `GET /invoices/INV-006014` |
| "How many orders did Sagar Mothi make this year and how much did he spend?" | `GET /search?query=Sagar Mothi` then `GET /customers/{id}/spend?from=2026-01-01` |
| "Today's processing orders with no tracking number" | `GET /orders?status=processing&has_tracking=false&from=2026-09-22` |
| "How did each sales channel do last month?" | `GET /reports/orders-summary?from=…&to=…&group_by=channel` |

Totals are calculated in SQL, not by the model reading rows and adding them
up, so the numbers are right.

## Auditing

Every read is logged to `storage/logs/chatgpt-*.log` with the path, query,
token name and IP — so "what did it look at?" always has an answer. Blocked
write attempts are logged as warnings.

Requests are rate-limited to 60/minute and page sizes are capped at 100.

## What this does not change

Every change is additive or scoped to `api/chatgpt/*`. Verified by capturing
real responses from ten existing endpoints, reverting the whole branch,
capturing again, and diffing: **identical status codes and byte-identical
bodies** on `product-list`, `business-source-list`, `payment-mode-list`,
`delivery-service-list`, `top-menu`, `side-menu`, `products`, `customers`,
`orders` and `invoices`.

Two files outside the new code are touched, both narrowly:

- `Authenticate::redirectTo()` and `Handler::unauthenticated()` return a 401
  for `api/chatgpt/*` only. `/api/me` and `/api/user` still return exactly
  what they returned before.
- The audit middleware is added to the global `api` group, and is built to be
  invisible — see below.

> **Known pre-existing issue, deliberately left alone:** an unauthenticated
> call to `/api/me` or `/api/user` returns a **500 with a full stack trace**,
> not a 401, because the framework redirects to a `login` route this app does
> not have. With `APP_DEBUG=true` in production that leaks file paths and
> code. Fixing it is a one-line change to the scope above, but it changes a
> status code those endpoints have always returned, so it should be done as
> its own deliberate change.

### The audit middleware

It runs on every API request, so it is written to cost nothing:

- requests that send a bearer token return immediately — **no extra database
  query** on normal authenticated traffic (test-enforced);
- each (method, route, IP) is written at most once an hour, so the log cannot
  grow without bound and fill the disk;
- it runs in `terminate()`, after the response is sent;
- every path is wrapped in a catch-all, so a logging failure cannot reach a
  caller (test-enforced).

## Who can ask

Anyone who can talk to the connected GPT can read everything the token can
read. Access control on the ChatGPT side is as important as the token itself —
do not share the GPT more widely than you would share a database export.
