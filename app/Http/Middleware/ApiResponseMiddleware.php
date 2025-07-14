<?php
// app/Http/Middleware/ApiResponseMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiResponseMiddleware
{
    /**
     * Handle an incoming request and format API responses consistently
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
            
            // Only process JSON responses for API routes
            if ($this->shouldProcessResponse($request, $response)) {
                return $this->formatResponse($response);
            }
            
            return $response;
            
        } catch (Throwable $e) {
            Log::error('🚨 Unhandled exception in API middleware', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => auth()->id(),
            ]);
            
            return $this->createErrorResponse($e);
        }
    }

    /**
     * Determine if we should process this response
     */
    private function shouldProcessResponse(Request $request, Response $response): bool
    {
        // Only process API routes
        if (!str_starts_with($request->path(), 'api/')) {
            return false;
        }
        
        // Only process JSON responses
        if (!$response instanceof JsonResponse) {
            return false;
        }
        
        // Don't process file downloads or streams
        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/octet-stream') ||
            str_contains($contentType, 'application/pdf') ||
            $response->headers->has('Content-Disposition')) {
            return false;
        }
        
        return true;
    }

    /**
     * Format the response to ensure consistency
     */
    private function formatResponse(JsonResponse $response): JsonResponse
    {
        $data = json_decode($response->getContent(), true);
        
        // If response is already properly formatted, return as-is
        if (isset($data['success']) && isset($data['status']) && isset($data['message'])) {
            return $response;
        }
        
        // Format the response consistently
        $formatted = [
            'success' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 300,
            'status' => $response->getStatusCode(),
            'message' => $this->extractMessage($data, $response->getStatusCode()),
            'timestamp' => now()->toISOString(),
        ];

        // Add data if present
        if ($data !== null) {
            if (is_array($data) && !isset($data['success'])) {
                $formatted['data'] = $data;
            } elseif (!is_array($data)) {
                $formatted['data'] = $data;
            }
        }

        // Add errors if this is an error response
        if ($response->getStatusCode() >= 400) {
            $formatted['errors'] = $this->extractErrors($data);
        }

        return response()->json($formatted, $response->getStatusCode());
    }

    /**
     * Extract message from response data
     */
    private function extractMessage($data, int $statusCode): string
    {
        if (is_array($data) && isset($data['message'])) {
            return $data['message'];
        }

        // Default messages based on status code
        return match($statusCode) {
            200 => 'Request successful',
            201 => 'Resource created successfully',
            204 => 'Request completed successfully',
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Resource not found',
            422 => 'Validation failed',
            429 => 'Too many requests',
            500 => 'Internal server error',
            default => 'Request processed',
        };
    }

    /**
     * Extract errors from response data
     */
    private function extractErrors($data): ?array
    {
        if (is_array($data)) {
            if (isset($data['errors'])) {
                return $data['errors'];
            }
            
            if (isset($data['error'])) {
                return ['general' => [$data['error']]];
            }
        }

        return null;
    }

    /**
     * Create consistent error response for exceptions
     */
    private function createErrorResponse(Throwable $e): JsonResponse
    {
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        
        $response = [
            'success' => false,
            'status' => $statusCode,
            'message' => $this->getErrorMessage($e),
            'timestamp' => now()->toISOString(),
        ];

        // Add debug information in debug mode
        if (config('app.debug')) {
            $response['debug'] = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(5)->toArray(),
            ];
        }

        // Add validation errors for validation exceptions
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            $response['errors'] = $e->errors();
            $response['error_count'] = count($e->errors());
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Get appropriate error message for exception
     */
    private function getErrorMessage(Throwable $e): string
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return 'Validation failed';
        }

        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return 'Resource not found';
        }

        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return 'Authentication required';
        }

        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return 'Access denied';
        }

        if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return 'Endpoint not found';
        }

        if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
            return 'Method not allowed';
        }

        if ($e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException) {
            return 'Too many requests';
        }

        // Generic server error message
        return config('app.debug') ? $e->getMessage() : 'An error occurred';
    }

    /**
     * Handle the response after it's been sent (for logging cleanup)
     */
    public function terminate(Request $request, Response $response): void
    {
        // Log final response details for API routes in debug mode
        if (config('app.debug') && str_starts_with($request->path(), 'api/')) {
            $duration = defined('LARAVEL_START') ? 
                round((microtime(true) - LARAVEL_START) * 1000, 2) : 0;
            
            Log::info('🏁 Request completed', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'status' => $response->getStatusCode(),
                'duration' => $duration . 'ms',
                'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
                'user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $size, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
}

