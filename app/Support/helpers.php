<?php

use Illuminate\Http\JsonResponse;

if (!function_exists('apiResponse')) {
    function apiResponse(
        mixed $data = null,
        string $message = '',
        bool $success = true,
        int $status = 200,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        $body = [
            'success' => $success,
            'message' => $message,
        ];

        if ($success) {
            $body['data'] = $data;
        } else {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status)->withHeaders($headers);
    }
}