<?php
// app/Providers/LoggingServiceProvider.php (ENHANCED with configuration)

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Check if API logging is enabled (configurable)
        if ($this->isApiLoggingEnabled()) {
            $this->enableDetailedApiLogging();
        }
    }

    /**
     * Check if API logging should be enabled
     */
    private function isApiLoggingEnabled(): bool
    {
        // Multiple ways to enable logging (in order of priority):
        // 1. Environment variable API_LOGGING_ENABLED
        // 2. Config setting logging.api_enabled
        // 3. Debug mode (fallback for backward compatibility)
        
        return env('API_LOGGING_ENABLED', 
            config('logging.api_enabled', 
                config('app.debug', false)
            )
        );
    }

    /**
     * Check if detailed request logging is enabled
     */
    private function isRequestLoggingEnabled(): bool
    {
        return env('API_REQUEST_LOGGING', 
            config('logging.api_request_logging', true)
        );
    }

    /**
     * Check if response logging is enabled
     */
    private function isResponseLoggingEnabled(): bool
    {
        return env('API_RESPONSE_LOGGING', 
            config('logging.api_response_logging', true)
        );
    }

    /**
     * Check if error-only logging mode is enabled
     */
    private function isErrorOnlyMode(): bool
    {
        return env('API_LOG_ERRORS_ONLY', 
            config('logging.api_errors_only', false)
        );
    }

    private function enableDetailedApiLogging(): void
    {
        // Log API requests (if enabled)
        if ($this->isRequestLoggingEnabled()) {
            $this->app['router']->matched(function ($event) {
                $request = $event->request;
                
                // Only log API routes
                if (str_starts_with($request->path(), 'api/')) {
                    $this->logApiRequest($request);
                }
            });
        }

        // Log API responses (if enabled)
        if ($this->isResponseLoggingEnabled()) {
            $this->app->terminating(function () {
                $request = request();
                
                if (str_starts_with($request->path(), 'api/')) {
                    $response = response();
                    
                    // In error-only mode, only log if response has error status
                    if (!$this->isErrorOnlyMode() || $response->getStatusCode() >= 400) {
                        $this->logApiResponse($request, $response);
                    }
                }
            });
        }
    }

    private function logApiRequest(Request $request): void
    {
        // Skip logging if in error-only mode (requests will be logged with their responses)
        if ($this->isErrorOnlyMode()) {
            return;
        }

        $logData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
        ];

        // Add detailed data only if not in minimal mode
        if (!env('API_LOG_MINIMAL', config('logging.api_minimal', false))) {
            $logData = array_merge($logData, [
                'headers' => $this->filterHeaders($request->headers->all()),
                'query' => $request->query(),
                'body' => $this->filterRequestBody($request),
                'files' => $request->file() ? array_keys($request->file()) : [],
            ]);
        }

        Log::channel('api_requests')->info('🌐 API Request', $logData);
    }

    private function logApiResponse(Request $request, $response): void
    {
        $logData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status' => $response->getStatusCode(),
            'duration' => $this->getRequestDuration(),
        ];

        // Add performance data only if not in minimal mode
        if (!env('API_LOG_MINIMAL', config('logging.api_minimal', false))) {
            $logData['memory_usage'] = $this->formatBytes(memory_get_peak_usage(true));
        }

        // Log response body for errors or if detailed logging is enabled
        $shouldLogResponse = $response->getStatusCode() >= 400 || 
                           env('API_LOG_RESPONSES', config('logging.api_log_responses', false));
        
        if ($shouldLogResponse) {
            $content = $response->getContent();
            if ($this->isJson($content)) {
                $logData['response'] = json_decode($content, true);
            }
        }

        $logLevel = $response->getStatusCode() >= 400 ? 'error' : 'info';
        $emoji = $response->getStatusCode() >= 400 ? '❌' : '✅';
        
        Log::channel('api_requests')->$logLevel("{$emoji} API Response", $logData);
    }

    private function filterHeaders(array $headers): array
    {
        $filtered = [];
        $sensitiveHeaders = env('API_LOG_SENSITIVE_HEADERS', 'authorization,cookie,x-api-key');
        $sensitiveHeadersList = explode(',', $sensitiveHeaders);
        
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveHeadersList)) {
                $filtered[$key] = '[FILTERED]';
            } else {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }

    private function filterRequestBody(Request $request): array
    {
        $defaultSensitive = 'password,password_confirmation,_token,secret,key,api_key';
        $sensitiveFields = explode(',', env('API_LOG_SENSITIVE_FIELDS', $defaultSensitive));
        
        $body = $request->all();
        
        foreach ($sensitiveFields as $field) {
            if (isset($body[$field])) {
                $body[$field] = '[FILTERED]';
            }
        }
        
        return $body;
    }

    private function getRequestDuration(): string
    {
        if (defined('LARAVEL_START')) {
            $duration = (microtime(true) - LARAVEL_START) * 1000;
            return round($duration, 2) . 'ms';
        }
        
        return 'unknown';
    }

    private function formatBytes(int $size, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }

    private function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}