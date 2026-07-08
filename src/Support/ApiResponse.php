<?php

namespace BSO\Survival\Support;

class ApiResponse {
    /**
     * @param array<string, mixed> $data
     * @return mixed
     */
    public static function success(array $data = [], int $status = 200) {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if (function_exists('rest_ensure_response')) {
            $response = rest_ensure_response($payload);
            if (is_object($response) && method_exists($response, 'set_status')) {
                $response->set_status($status);
            }

            return $response;
        }

        return $payload;
    }

    /**
     * @return mixed
     */
    public static function error(string $code, string $message, int $status = 400, array $details = []) {
        if (class_exists('WP_Error')) {
            return new \WP_Error($code, $message, [
                'status' => $status,
                'details' => $details,
            ]);
        }

        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'status' => $status,
                'details' => $details,
            ],
        ];
    }
}
