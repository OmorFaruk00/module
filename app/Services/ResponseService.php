<?php

namespace App\Services;

class ResponseService
{
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200
    ) {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'status' => $status,
            'errors' => null,
        ], $status);
    }
}
