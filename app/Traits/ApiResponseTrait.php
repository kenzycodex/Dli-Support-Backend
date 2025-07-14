<?php
// app/Traits/ApiResponseTrait.php (ENHANCED with configurable logging)

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ApiResponseTrait
{
    /**
     * Check if API response logging is enabled
     */
    private function shouldLogResponse(): bool
    {
        return env('API_RESPONSE_LOGGING', 
            config('logging.api_response_logging', 
                config('app.debug', false)
            )
        );
    }

    /**
     * Check if detailed logging is enabled
     */
    private function shouldLogDetailed(): bool
    {
        return env('API_LOG_DETAILED', 
            config('logging.api_detailed', 
                config('app.debug', false)
            )
        );
    }

    /**
     * Return a success JSON response
     */
    protected function successResponse($data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'status' => $status,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        // Log successful responses only if enabled
        if ($this->shouldLogResponse()) {
            $logData = [
                'status' => $status,
                'message' => $message,
            ];

            // Add detailed data only if detailed logging is enabled
            if ($this->shouldLogDetailed()) {
                $logData = array_merge($logData, [
                    'data_type' => $data ? gettype($data) : 'null',
                    'response_size' => strlen(json_encode($response))
                ]);
            }

            Log::info("✅ API Success Response", $logData);
        }

        return response()->json($response, $status);
    }

    /**
     * Return an error JSON response
     */
    protected function errorResponse(string $message = 'Error occurred', int $status = 400, $errors = null, $data = null): JsonResponse
    {
        $response = [
            'success' => false,
            'status' => $status,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        // Always log error responses (regardless of logging settings)
        // But make the detail level configurable
        $logData = [
            'status' => $status,
            'message' => $message,
        ];

        if ($this->shouldLogDetailed()) {
            $logData = array_merge($logData, [
                'errors' => $errors,
                'request_url' => request()->fullUrl(),
                'request_method' => request()->method(),
                'user_id' => auth()->id(),
            ]);
        }

        Log::warning("❌ API Error Response", $logData);

        return response()->json($response, $status);
    }

    /**
     * Return validation error response
     */
    protected function validationErrorResponse($validator, string $message = 'Validation failed'): JsonResponse
    {
        $errors = $validator->errors();
        
        // Format errors for better frontend consumption
        $formattedErrors = [];
        foreach ($errors->toArray() as $field => $messages) {
            $formattedErrors[$field] = [
                'messages' => $messages,
                'first' => $messages[0] ?? '',
            ];
        }

        // Log validation errors with configurable detail level
        $logData = [
            'message' => $message,
            'error_count' => count($formattedErrors),
        ];

        if ($this->shouldLogDetailed()) {
            $logData = array_merge($logData, [
                'errors' => $formattedErrors,
                'request_data' => request()->except(['password', 'password_confirmation', '_token']),
                'request_url' => request()->fullUrl(),
                'user_id' => auth()->id(),
            ]);
        }

        Log::warning("❌ Validation Error", $logData);

        return response()->json([
            'success' => false,
            'status' => 422,
            'message' => $message,
            'errors' => $formattedErrors,
            'error_count' => count($formattedErrors),
            'timestamp' => now()->toISOString(),
        ], 422);
    }

    /**
     * Return not found response
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Return unauthorized response
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Return forbidden response
     */
    protected function forbiddenResponse(string $message = 'Access denied'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Return server error response
     */
    protected function serverErrorResponse(string $message = 'Internal server error', $debug = null): JsonResponse
    {
        $response = [
            'success' => false,
            'status' => 500,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        // Always log server errors (critical)
        $logData = [
            'message' => $message,
        ];

        if ($this->shouldLogDetailed()) {
            $logData = array_merge($logData, [
                'debug' => $debug,
                'request_url' => request()->fullUrl(),
                'request_method' => request()->method(),
                'user_id' => auth()->id(),
                'stack_trace' => $debug instanceof \Exception ? $debug->getTraceAsString() : null,
            ]);
        }

        Log::error("🚨 Server Error Response", $logData);

        // Only include debug info if debug mode is enabled AND detailed logging is on
        if (config('app.debug') && $this->shouldLogDetailed() && $debug) {
            if ($debug instanceof \Exception) {
                $response['debug'] = [
                    'exception' => get_class($debug),
                    'message' => $debug->getMessage(),
                    'file' => $debug->getFile(),
                    'line' => $debug->getLine(),
                    'trace' => $debug->getTraceAsString(),
                ];
            } else {
                $response['debug'] = $debug;
            }
        }

        return response()->json($response, 500);
    }

    /**
     * Return paginated response
     */
    protected function paginatedResponse($paginator, string $message = 'Data retrieved successfully'): JsonResponse
    {
        $pagination = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more_pages' => $paginator->hasMorePages(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];

        return $this->successResponse([
            'items' => $paginator->items(),
            'pagination' => $pagination,
        ], $message);
    }

    /**
     * Return file download response
     */
    protected function fileDownloadResponse($filePath, $fileName = null, $mimeType = null): Response
    {
        try {
            if (!file_exists($filePath)) {
                if ($this->shouldLogResponse()) {
                    Log::error("File not found for download", ['path' => $filePath]);
                }
                return $this->notFoundResponse('File not found');
            }

            $fileName = $fileName ?: basename($filePath);
            $mimeType = $mimeType ?: mime_content_type($filePath) ?: 'application/octet-stream';
            $fileSize = filesize($filePath);

            if ($this->shouldLogResponse()) {
                $logData = [
                    'file' => $fileName,
                    'size' => $fileSize,
                    'mime_type' => $mimeType,
                ];

                if ($this->shouldLogDetailed()) {
                    $logData['path'] = $filePath;
                }

                Log::info("📁 File download initiated", $logData);
            }

            return response()->download($filePath, $fileName, [
                'Content-Type' => $mimeType,
                'Content-Length' => $fileSize,
                'Cache-Control' => 'no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            if ($this->shouldLogResponse()) {
                Log::error("File download failed", [
                    'file' => $fileName,
                    'error' => $e->getMessage(),
                ]);
            }
            return $this->serverErrorResponse('File download failed', $e);
        }
    }

    /**
     * Return streaming response for large files
     */
    protected function streamResponse($filePath, $fileName = null): StreamedResponse
    {
        if (!file_exists($filePath)) {
            abort(404, 'File not found');
        }

        $fileName = $fileName ?: basename($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $fileSize = filesize($filePath);

        if ($this->shouldLogResponse()) {
            Log::info("📡 File streaming initiated", [
                'file' => $fileName,
                'size' => $fileSize,
                'mime_type' => $mimeType,
            ]);
        }

        return response()->stream(function () use ($filePath) {
            $stream = fopen($filePath, 'rb');
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Length' => $fileSize,
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Handle file response for preview or download
     */
    protected function fileResponse($filePath, $fileName = null, $inline = false): Response
    {
        try {
            if (!file_exists($filePath)) {
                return $this->notFoundResponse('File not found');
            }

            $fileName = $fileName ?: basename($filePath);
            $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
            $fileSize = filesize($filePath);
            $disposition = $inline ? 'inline' : 'attachment';
            
            if ($this->shouldLogResponse()) {
                Log::info("📄 File response", [
                    'file' => $fileName,
                    'size' => $fileSize,
                    'mime_type' => $mimeType,
                    'disposition' => $disposition,
                ]);
            }

            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => $disposition . '; filename="' . $fileName . '"',
                'Content-Length' => $fileSize,
                'Cache-Control' => 'public, max-age=3600',
            ]);

        } catch (\Exception $e) {
            if ($this->shouldLogResponse()) {
                Log::error("File response failed", [
                    'file' => $fileName,
                    'error' => $e->getMessage(),
                ]);
            }
            return $this->serverErrorResponse('File access failed', $e);
        }
    }

    /**
     * Return delete success response
     */
    protected function deleteSuccessResponse($resourceName = 'Resource', $resourceId = null): JsonResponse
    {
        $message = $resourceId 
            ? "{$resourceName} #{$resourceId} deleted successfully"
            : "{$resourceName} deleted successfully";

        if ($this->shouldLogResponse()) {
            Log::info("🗑️ Delete operation successful", [
                'resource' => $resourceName,
                'id' => $resourceId,
                'user_id' => auth()->id(),
            ]);
        }

        return $this->successResponse([
            'deleted' => true,
            'resource_type' => $resourceName,
            'resource_id' => $resourceId,
            'deleted_at' => now()->toISOString(),
        ], $message);
    }

    /**
     * Handle exceptions uniformly
     */
    protected function handleException(\Exception $e, string $operation = 'Operation'): JsonResponse
    {
        // Always log exceptions (critical), but make detail level configurable
        $logData = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'operation' => $operation,
        ];

        if ($this->shouldLogDetailed()) {
            $logData = array_merge($logData, [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_url' => request()->fullUrl(),
                'user_id' => auth()->id(),
            ]);
        }

        Log::error("🚨 Exception in {$operation}", $logData);

        // Return appropriate error response
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return $this->validationErrorResponse($e->validator);
        }

        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFoundResponse('Resource not found');
        }

        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return $this->unauthorizedResponse('Authentication required');
        }

        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return $this->forbiddenResponse('Access denied');
        }

        // Generic server error
        return $this->serverErrorResponse(
            "{$operation} failed. Please try again.",
            $this->shouldLogDetailed() ? $e : null
        );
    }

    /**
     * Log request details for debugging
     */
    protected function logRequestDetails(string $operation): void
    {
        if ($this->shouldLogDetailed()) {
            Log::info("🔍 {$operation} - Request Details", [
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'headers' => request()->headers->all(),
                'query' => request()->query(),
                'body' => request()->except(['password', 'password_confirmation', '_token']),
                'files' => request()->file() ? array_keys(request()->file()) : [],
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }
    }
}