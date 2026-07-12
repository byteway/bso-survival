<?php

namespace BSO\Survival\Api;

use BSO\Survival\Service\FrontendScoreSubmissionService;
use BSO\Survival\Support\ApiResponse;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class FrontendScoreRestController {
    private const NAMESPACE = 'bso-survival/v1';
    private const ROUTE = '/score-entries';

    /** @var FrontendScoreSubmissionService */
    private $scores;

    public function __construct(FrontendScoreSubmissionService $scores) {
        $this->scores = $scores;
    }

    public function registerRoutes(): void {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(self::NAMESPACE, self::ROUTE, [[
            'methods' => 'POST',
            'callback' => [$this, 'submitScore'],
            'permission_callback' => [$this, 'canSubmit'],
        ]]);
    }

    /**
     * @param mixed $request
     */
    public function canSubmit($request = null): bool {
        if (!function_exists('current_user_can') || !current_user_can('read')) {
            return false;
        }

        if (!function_exists('wp_verify_nonce')) {
            return true;
        }

        $nonce = $this->extractStringParam($request, 'score_nonce');
        if ($nonce === '' && is_object($request) && method_exists($request, 'get_header')) {
            $nonce = (string) $request->get_header('X-BSO-Score-Nonce');
        }

        if ($nonce === '') {
            return false;
        }

        $valid = wp_verify_nonce($nonce, 'bso_survival_score_submission');
        return $valid === 1 || $valid === 2;
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function submitScore($request) {
        $payload = [
            'event_id' => $this->extractIntParam($request, 'event_id'),
            'assignment_id' => $this->extractIntParam($request, 'assignment_id'),
            'raw_value' => $this->extractRawParam($request, 'raw_value'),
            'bonus_points' => $this->extractRawParam($request, 'bonus_points'),
            'joker_applied' => $this->extractBoolParam($request, 'joker_applied'),
            'joker_validated_by' => $this->extractStringParam($request, 'joker_validated_by'),
            'entered_by_role' => $this->extractStringParam($request, 'entered_by_role'),
        ];

        try {
            $result = $this->scores->submit($payload);

            return ApiResponse::success([
                'created' => true,
                'result' => $result,
            ], 201);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('invalid_score_input', $exception->getMessage(), 400);
        } catch (RuntimeException $exception) {
            return ApiResponse::error('score_submit_blocked', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return ApiResponse::error('score_submit_failed', 'Score-invoer kon niet worden verwerkt.', 500);
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

    /** @param mixed $request */
    private function extractBoolParam($request, string $key): bool {
        $raw = $this->extractRawParam($request, $key);

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw) || is_float($raw)) {
            return (int) $raw === 1;
        }

        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower(trim((string) $raw))
            : strtolower(trim((string) $raw));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
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
