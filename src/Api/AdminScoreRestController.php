<?php

namespace BSO\Survival\Api;

use BSO\Survival\Service\AdminScoreService;
use BSO\Survival\Support\ApiResponse;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AdminScoreRestController {
    private const NAMESPACE = 'bso-survival/v1';
    private const CREATE_ROUTE = '/scores/entries';
    private const UPDATE_ROUTE = '/scores/entries/(?P<score_entry_id>\d+)';
    private const RECALCULATE_ROUTE = '/scores/recalculate';

    /** @var AdminScoreService */
    private $scores;

    public function __construct(AdminScoreService $scores) {
        $this->scores = $scores;
    }

    public function registerRoutes(): void {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(self::NAMESPACE, self::CREATE_ROUTE, [[
            'methods' => 'POST',
            'callback' => [$this, 'createEntry'],
            'permission_callback' => [$this, 'canManage'],
        ]]);

        register_rest_route(self::NAMESPACE, self::UPDATE_ROUTE, [[
            'methods' => 'PATCH',
            'callback' => [$this, 'updateEntry'],
            'permission_callback' => [$this, 'canManage'],
        ]]);

        register_rest_route(self::NAMESPACE, self::RECALCULATE_ROUTE, [[
            'methods' => 'POST',
            'callback' => [$this, 'recalculatePart'],
            'permission_callback' => [$this, 'canManage'],
        ]]);
    }

    /**
     * @param mixed $request
     */
    public function canManage($request = null): bool {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            return false;
        }

        if (!function_exists('wp_verify_nonce') || !is_object($request) || !method_exists($request, 'get_header')) {
            return true;
        }

        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce === '') {
            $nonce = (string) $request->get_header('x-wp-nonce');
        }

        if ($nonce === '') {
            return false;
        }

        $valid = wp_verify_nonce($nonce, 'wp_rest');
        return $valid === 1 || $valid === 2;
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function createEntry($request) {
        $payload = [
            'event_id' => $this->extractIntParam($request, 'event_id'),
            'assignment_id' => $this->extractIntParam($request, 'assignment_id'),
            'raw_value' => $this->extractRawParam($request, 'raw_value'),
            'changed_by' => $this->extractStringParam($request, 'changed_by'),
            'entered_by_role' => $this->extractStringParam($request, 'entered_by_role'),
        ];

        try {
            $result = $this->scores->create($payload);
            return ApiResponse::success(['result' => $result], 201);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('invalid_score_input', $exception->getMessage(), 400);
        } catch (RuntimeException $exception) {
            return ApiResponse::error('score_update_blocked', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return ApiResponse::error('score_create_failed', 'Score kon niet worden opgeslagen.', 500);
        }
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function updateEntry($request) {
        $scoreEntryId = $this->extractIntParam($request, 'score_entry_id');
        $payload = [
            'event_id' => $this->extractIntParam($request, 'event_id'),
            'raw_value' => $this->extractRawParam($request, 'raw_value'),
            'changed_by' => $this->extractStringParam($request, 'changed_by'),
            'entered_by_role' => $this->extractStringParam($request, 'entered_by_role'),
        ];

        try {
            $result = $this->scores->update($scoreEntryId, $payload);
            return ApiResponse::success(['result' => $result]);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('invalid_score_input', $exception->getMessage(), 400);
        } catch (RuntimeException $exception) {
            return ApiResponse::error('score_update_blocked', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return ApiResponse::error('score_update_failed', 'Score kon niet worden bijgewerkt.', 500);
        }
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function recalculatePart($request) {
        $payload = [
            'event_id' => $this->extractIntParam($request, 'event_id'),
            'part_id' => $this->extractIntParam($request, 'part_id'),
            'changed_by' => $this->extractStringParam($request, 'changed_by'),
        ];

        try {
            $result = $this->scores->recalculate($payload);
            return ApiResponse::success(['result' => $result]);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('invalid_recalculate_input', $exception->getMessage(), 400);
        } catch (RuntimeException $exception) {
            return ApiResponse::error('recalculate_blocked', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return ApiResponse::error('recalculate_failed', 'Herberekening kon niet worden uitgevoerd.', 500);
        }
    }

    /** @param mixed $request */
    private function extractIntParam($request, string $key): int {
        if (is_object($request) && method_exists($request, 'get_param')) {
            return (int) $request->get_param($key);
        }

        if (is_array($request) && isset($request[$key])) {
            return (int) $request[$key];
        }

        return 0;
    }

    /** @param mixed $request */
    private function extractStringParam($request, string $key): string {
        if (is_object($request) && method_exists($request, 'get_param')) {
            return trim((string) $request->get_param($key));
        }

        if (is_array($request) && isset($request[$key])) {
            return trim((string) $request[$key]);
        }

        return '';
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    private function extractRawParam($request, string $key) {
        if (is_object($request) && method_exists($request, 'get_param')) {
            return $request->get_param($key);
        }

        if (is_array($request) && array_key_exists($key, $request)) {
            return $request[$key];
        }

        return null;
    }
}
