<?php
// app/Traits/ApiResponseTrait.php (ENHANCED)

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ApiResponseTrait
{
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

        // Log successful responses in debug mode
        if (config('app.debug')) {
            Log::info("✅ API Success Response", [
                'status' => $status,
                'message' => $message,
                'data_type' => $data ? gettype($data) : 'null',
                'response_size' => strlen(json_encode($response))
            ]);
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

        // Always log error responses
        Log::warning("❌ API Error Response", [
            'status' => $status,
            'message' => $message,
            'errors' => $errors,
            'request_url' => request()->fullUrl(),
            'request_method' => request()->method(),
            'user_id' => auth()->id(),
        ]);

        return response()->json($response, $status);
    }

    /**
     * Return validation error response (ENHANCED for frontend)
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

        Log::warning("❌ Validation Error", [
            'message' => $message,
            'errors' => $formattedErrors,
            'request_data' => request()->except(['password', 'password_confirmation', '_token']),
            'request_url' => request()->fullUrl(),
            'user_id' => auth()->id(),
        ]);

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
     * Return server error response (ENHANCED with debugging)
     */
    protected function serverErrorResponse(string $message = 'Internal server error', $debug = null): JsonResponse
    {
        $response = [
            'success' => false,
            'status' => 500,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        // Always log server errors
        Log::error("🚨 Server Error Response", [
            'message' => $message,
            'debug' => $debug,
            'request_url' => request()->fullUrl(),
            'request_method' => request()->method(),
            'user_id' => auth()->id(),
            'stack_trace' => $debug instanceof \Exception ? $debug->getTraceAsString() : null,
        ]);

        if (config('app.debug') && $debug) {
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
     * Return paginated response (ENHANCED)
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
     * Return file download response (FIXED for frontend)
     */
    protected function fileDownloadResponse($filePath, $fileName = null, $mimeType = null): Response
    {
        try {
            if (!file_exists($filePath)) {
                Log::error("File not found for download", ['path' => $filePath]);
                return $this->notFoundResponse('File not found');
            }

            $fileName = $fileName ?: basename($filePath);
            $mimeType = $mimeType ?: mime_content_type($filePath) ?: 'application/octet-stream';
            $fileSize = filesize($filePath);

            Log::info("📁 File download initiated", [
                'file' => $fileName,
                'size' => $fileSize,
                'mime_type' => $mimeType,
                'path' => $filePath,
            ]);

            return response()->download($filePath, $fileName, [
                'Content-Type' => $mimeType,
                'Content-Length' => $fileSize,
                'Cache-Control' => 'no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            Log::error("File download failed", [
                'file' => $fileName,
                'error' => $e->getMessage(),
            ]);
            return $this->serverErrorResponse('File download failed', $e);
        }
    }

    /**
     * Return streaming response for large files (FIXED)
     */
    protected function streamResponse($filePath, $fileName = null): StreamedResponse
    {
        if (!file_exists($filePath)) {
            abort(404, 'File not found');
        }

        $fileName = $fileName ?: basename($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $fileSize = filesize($filePath);

        Log::info("📡 File streaming initiated", [
            'file' => $fileName,
            'size' => $fileSize,
            'mime_type' => $mimeType,
        ]);

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
     * Handle file response for preview or download (NEW)
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
            
            Log::info("📄 File response", [
                'file' => $fileName,
                'size' => $fileSize,
                'mime_type' => $mimeType,
                'disposition' => $disposition,
            ]);

            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => $disposition . '; filename="' . $fileName . '"',
                'Content-Length' => $fileSize,
                'Cache-Control' => 'public, max-age=3600',
            ]);

        } catch (\Exception $e) {
            Log::error("File response failed", [
                'file' => $fileName,
                'error' => $e->getMessage(),
            ]);
            return $this->serverErrorResponse('File access failed', $e);
        }
    }

    /**
     * Return delete success response (NEW - specific for delete operations)
     */
    protected function deleteSuccessResponse($resourceName = 'Resource', $resourceId = null): JsonResponse
    {
        $message = $resourceId 
            ? "{$resourceName} #{$resourceId} deleted successfully"
            : "{$resourceName} deleted successfully";

        Log::info("🗑️ Delete operation successful", [
            'resource' => $resourceName,
            'id' => $resourceId,
            'user_id' => auth()->id(),
        ]);

        return $this->successResponse([
            'deleted' => true,
            'resource_type' => $resourceName,
            'resource_id' => $resourceId,
            'deleted_at' => now()->toISOString(),
        ], $message);
    }

    /**
     * Handle exceptions uniformly (NEW)
     */
    protected function handleException(\Exception $e, string $operation = 'Operation'): JsonResponse
    {
        // Log the full exception
        Log::error("🚨 Exception in {$operation}", [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'request_url' => request()->fullUrl(),
            'user_id' => auth()->id(),
        ]);

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
            config('app.debug') ? $e : null
        );
    }

    /**
     * Log request details for debugging (NEW)
     */
    protected function logRequestDetails(string $operation): void
    {
        if (config('app.debug')) {
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