<?php

declare(strict_types=1);

namespace Monitor\Exceptions\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class RemoteErrorReportingHandler extends AbstractProcessingHandler
{
    private const SEND_EXCEPTIONS_TO = 'https://system.datasmugglers.com/api/v1/monitor/exception';

    private const SENSITIVE_HEADERS = [
        'authorization',
        'cookie',
        'cookie2',
        'x-api-key',
        'x-auth-token',
        'x-csrf-token',
        'x-csrftoken',
        'x-forwarded-for',
        'x-real-ip',
        'proxy-authorization',
    ];

    private const TIMEOUT = 1.0;

    public function __construct(int|string|Level $level = Level::Error, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $environmentId = trim((string) Environment::getEnv('MONITOR_EXCEPTION_ENVIRONMENT_ID'));
        $environmentKey = trim((string) Environment::getEnv('MONITOR_EXCEPTION_ENVIRONMENT_KEY'));
        if ($environmentId === '' || $environmentKey === '') {
            return;
        }

        $context = $record->context;
        $exception = $context['exception'] ?? null;

        $errorSeverity = $record->level instanceof Level ? match ($record->level) {
            Level::Debug => 100,
            Level::Info => 200,
            Level::Notice => 250,
            Level::Warning => 300,
            Level::Error => 400,
            Level::Critical => 500,
            Level::Alert => 550,
            Level::Emergency => 600,
            default => 400,
        } : null;
        $exceptionClass = null;
        $errorMessage = $record->message;
        $errorCode = null;
        $errorFile = $context['file'] ?? null;
        $errorLine = isset($context['line']) ? (int) $context['line'] : null;
        $stackTrace = null;

        if ($exception instanceof Throwable) {
            $exceptionClass = $exception::class;
            $errorMessage = $exception->getMessage();
            $errorCode = $exception->getCode() !== 0 ? (string) $exception->getCode() : null;
            $errorFile = $exception->getFile();
            $errorLine = $exception->getLine();
            $stackTrace = $exception->getTraceAsString();
        } elseif (isset($context['trace']) && is_array($context['trace'])) {
            $traceLines = [];
            foreach ($context['trace'] as $i => $frame) {
                $file = $frame['file'] ?? '[internal]';
                $line = $frame['line'] ?? 0;
                $function = $frame['function'] ?? '';
                $class = isset($frame['class']) ? $frame['class'] . ($frame['type'] ?? '') : '';
                $traceLines[] = "#{$i} {$file}({$line}): {$class}{$function}()";
            }
            $stackTrace = implode("\n", $traceLines);
        }

        if (Director::is_cli()) {
            $requestUrl = null;
            $requestMethod = null;
            $requestHeaders = [];
        } else {
            $httpRequest = Request::createFromGlobals();
            $requestUrl = $httpRequest->getSchemeAndHttpHost() . $httpRequest->getPathInfo();
            $requestMethod = $httpRequest->getMethod();
            $requestHeaders = [];
            foreach ($httpRequest->headers->all() as $name => $values) {
                if (in_array(strtolower($name), self::SENSITIVE_HEADERS, true)) {
                    continue;
                }
                $requestHeaders[$name] = is_array($values) ? implode(', ', $values) : (string) $values;
            }
        }

        $payload = [
            'environmentId' => $environmentId,
            'environmentKey' => $environmentKey,
            'reportedBy' => 'silverstripe',
            'errorSeverity' => $errorSeverity,
            'exceptionClass' => $exceptionClass,
            'errorMessage' => $errorMessage,
            'errorCode' => $errorCode,
            'errorFile' => $errorFile,
            'errorLine' => $errorLine,
            'stackTrace' => $stackTrace,
            'requestUrl' => $requestUrl,
            'requestMethod' => $requestMethod,
            'requestHeaders' => $requestHeaders,
        ];

        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode($payload),
                    'timeout' => self::TIMEOUT,
                ],
            ]);
            @file_get_contents(self::SEND_EXCEPTIONS_TO, false, $context);
        } catch (Throwable) {
            // Don't let reporting failure affect the error response
        }
    }
}
