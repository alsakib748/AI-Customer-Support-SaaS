<?php

namespace App;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Nette\Schema\ValidationException;

trait ApiResponse
{

    /**
     * Send a success response
     */

    protected function successResponse($data = null, string $message = 'Success', int $statusCode = Response::HTTP_OK): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if (!is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);

    }

    /**
     * Send an error response
     */

    protected function errorResponse(
        string $message = 'Error occurred',
        $errors = null,
        int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY,
        array $extra = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Send a validation error response
     */
    protected function validationErrorResponse($errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, $errors, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Send an authentication error response
     */
    protected function authErrorResponse(string $message = 'Unauthenticated', $errors = null): JsonResponse
    {
        return $this->errorResponse($message, $errors, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Send a forbidden error response
     */
    protected function forbiddenResponse(string $message = 'Forbidden', $errors = null): JsonResponse
    {
        return $this->errorResponse($message, $errors, Response::HTTP_FORBIDDEN);
    }

    /**
     * Send a not found error response
     */
    protected function notFoundResponse(string $message = 'Resource not found', $errors = null): JsonResponse
    {
        return $this->errorResponse($message, $errors, Response::HTTP_NOT_FOUND);
    }

    /**
     * Send a server error response
     */
    protected function serverErrorResponse(string $message = 'Internal server error', $errors = null): JsonResponse
    {
        return $this->errorResponse($message, $errors, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Send a created response (201)
     */
    protected function createdResponse($data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Send an accepted response (202)
     */
    protected function acceptedResponse($data = null, string $message = 'Request accepted'): JsonResponse
    {
        return $this->successResponse($data, $message, Response::HTTP_ACCEPTED);
    }

    /**
     * Send a no content response (204)
     */
    protected function noContentResponse(string $message = 'No content'): JsonResponse
    {
        return $this->successResponse(null, $message, Response::HTTP_NO_CONTENT);
    }

    /**
     * Send a conflict response (409)
     */
    protected function conflictResponse(string $message = 'Conflict', $errors = null): JsonResponse
    {
        return $this->errorResponse($message, $errors, Response::HTTP_CONFLICT);
    }

    /**
     * Send a too many requests response (429)
     */
    protected function tooManyRequestsResponse(string $message = 'Too many requests', $errors = null): JsonResponse
    {
        return $this->errorResponse($message, $errors, Response::HTTP_TOO_MANY_REQUESTS);
    }

    /**
     * Handle validation exception and return formatted error response
     */
    protected function handleValidationException(ValidationException $e): JsonResponse
    {
        $errors = $e->errors();

        // Extract first error message
        $errorMessages = [];
        foreach ($errors as $field => $messages) {
            $errorMessages = array_merge($errorMessages, $messages);
        }
        $firstError = $errorMessages[0] ?? 'Validation failed';

        return $this->validationErrorResponse($errors, $firstError);
    }

    /**
     * Handle exception and return appropriate error response
     */
    protected function handleException(\Exception $e, string $defaultMessage = 'An unexpected error occurred'): JsonResponse
    {
        Log::error('Exception caught:', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        // Handle specific exception types
        if ($e instanceof ValidationException) {
            return $this->handleValidationException($e);
        }

        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return $this->authErrorResponse('Unauthenticated');
        }

        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return $this->forbiddenResponse('You are not authorized to perform this action');
        }

        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFoundResponse('Resource not found');
        }

        if ($e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
            throw $e;
        }

        // Return generic error response
        return $this->serverErrorResponse(
            config('app.debug') ? $e->getMessage() : $defaultMessage,
            config('app.debug') ? ['exception' => $e->getTraceAsString()] : null
        );
    }

    /**
     * Send a paginated response
     */
    protected function paginatedResponse($paginator, string $message = 'Data retrieved successfully'): JsonResponse
    {
        return $this->successResponse([
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], $message);
    }

    /**
     * Send a response with custom status code and message
     */
    protected function customResponse($data = null, string $message = '', int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return $this->successResponse($data, $message, $statusCode);
    }

}
