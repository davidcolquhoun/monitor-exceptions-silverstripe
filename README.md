# Monitor Exceptions – SilverStripe

Lightweight exception monitoring for SilverStripe. Sends errors and exceptions from your app’s logger to the DataSmugglers monitoring API, where you can filter, search, and inspect them in a dashboard.

## Requirements

- PHP 8.1+
- SilverStripe Framework 6.1.x
- Monolog 3.x (pulled in by SilverStripe)

## Installation

In your SilverStripe project:

```bash
composer require davidcolquhoun/monitor-exceptions-silverstripe
```

## Configuration

Set these environment variables (e.g. in `.env` or your server config):

```env
MONITOR_EXCEPTION_ENVIRONMENT_ID=myapp-prod
MONITOR_EXCEPTION_ENVIRONMENT_KEY=your-secret-key
```

| Variable | Description |
|----------|-------------|
| `MONITOR_EXCEPTION_ENVIRONMENT_ID` | Environment identifier (e.g. `myapp-dev`, `myapp-prod`). |
| `MONITOR_EXCEPTION_ENVIRONMENT_KEY` | Secret key for this environment from DataSmugglers. |

- **environment_id**: Identifies the environment (e.g. `myapp-dev`, `myapp-prod`) for grouping in the DataSmugglers UI.
- **environment_key**: Secret key that authenticates this app with the DataSmugglers API.

If either is missing or empty, the handler does nothing and no data is sent.

## How it works

The module registers a Monolog handler on SilverStripe’s `errorhandler` logger. When the framework logs an error or exception, the handler sends a payload to the DataSmugglers API. No code changes are required beyond installing and setting the env vars.

## What gets sent

The client does not send request bodies, client IP, or query strings. It sends a JSON payload with:

| Field               | Type         | Description |
|---------------------|-------------|-------------|
| `environmentId`     | string      | Your environment identifier. |
| `environmentKey`    | string      | Your environment key. |
| `reportedByHandler` | string      | Always `silverstripe`. |
| `errorSeverity`     | int \| null | PHP error level when available (e.g. `ErrorException`). |
| `exceptionClass`    | string      | Exception class name. |
| `errorMessage`      | string      | Exception message. |
| `errorCode`         | string \| null | Exception code when set. |
| `errorFile`         | string      | File where the exception was thrown. |
| `errorLine`         | int         | Line number. |
| `stackTrace`        | string      | Stack trace as a string. |
| `requestUrl`        | string \| null | Request URL without query string (null in CLI). |
| `requestMethod`     | string \| null | HTTP method (null in CLI). |
| `requestHeaders`    | array       | Request headers with sensitive ones stripped (empty in CLI). |

Sensitive headers (e.g. `authorization`, `cookie`, `x-api-key`) are removed before sending.

## Behaviour

- **Registration**: If `environment_id` or `environment_key` is empty, the handler does not attach and no exceptions are sent.
- **CLI**: When running in CLI, `requestUrl`, `requestMethod`, and `requestHeaders` are null/empty.
- **Failures**: Errors inside the client (e.g. network) are caught and ignored so your app’s error handling is not affected.
- **Blocking**: The HTTP send is blocking (up to the configured timeout) so the report is sent before the request ends when possible.

## Testing

After configuring env vars, trigger an exception or error (e.g. in a controller or via the logger) and check the DataSmugglers dashboard for the event.
