<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Standard success response.
     *
     * @param mixed  $data
     * @param string|null $message
     * @param int    $code
     * @return JsonResponse
     */
    protected function success(mixed $data = [], string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Standard error response.
     *
     * @param string|null $message
     * @param int    $code
     * @param mixed  $data
     * @return JsonResponse
     */
    protected function error(string $message = null, int $code = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }
}
