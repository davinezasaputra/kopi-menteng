<?php

namespace App\Support\Responses;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200)
    {
        return response()->json(['status' => 'success', 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status, mixed $errors = null)
    {
        $payload = ['status' => 'error', 'message' => $message];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        return response()->json($payload, $status);
    }
}
