# Monitor Exceptions – SilverStripe

Lightweight exception monitoring for SilverStripe. Sends errors and exceptions from your app’s logger to the DataSmugglers monitoring API, where you can filter, search, and inspect them in a dashboard.

## Installation

In your SilverStripe project:

```bash
composer require davidcolquhoun/monitor-exceptions-silverstripe
```

## Configuration

Set these environment variables (e.g. in `.env` or your server config):

| Variable | Description |
|----------|-------------|
| `MONITOR_EXCEPTION_ENVIRONMENT_ID` | Environment identifier (e.g. `myapp-dev`, `myapp-prod`). |
| `MONITOR_EXCEPTION_ENVIRONMENT_KEY` | Secret key for this environment from DataSmugglers. |

If either is missing or empty, the handler does nothing and no data is sent.

## How it works

The module registers a Monolog handler on SilverStripe’s `errorhandler` logger. When the framework logs an error or exception, the handler sends a payload to the DataSmugglers API. No code changes are required beyond installing and setting the env vars.

Sensitive data is not sent: no request body, no client IP, no query string on the URL. Headers such as `authorization`, `cookie`, and `x-api-key` are stripped before sending.

## Requirements

- PHP 8.1+
- SilverStripe Framework 6.1.x
- Monolog 3.x (pulled in by SilverStripe)
