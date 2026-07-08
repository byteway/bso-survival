<?php

namespace BSO\Survival\Api;

use BSO\Survival\Service\FrontendScoreSubmissionService;
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
            'entered_by_role' => $this->extractStringParam($request, 'entered_by_role'),
        ];

        try {
            $result = $this->scores->submit($payload);

            return $this->response([
                'created' => true,
                'result' => $result,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->error('invalid_score_input', $exception->getMessage(), 400);
        } catch (RuntimeException $exception) {
            return $this->error('score_submit_blocked', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return $this->error('score_submit_failed', 'Score-invoer kon niet worden verwerkt.', 500);
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

    /**
     * @param array<string, mixed> $payload
     * @return mixed
     */
    private function response(array $payload) {
        if (function_exists('rest_ensure_response')) {
            return rest_ensure_response($payload);
        }

        return $payload;
    }

    /** @return mixed */
    private function error(string $code, string $message, int $status) {
        if (class_exists('WP_Error')) {
            return new \WP_Error($code, $message, ['status' => $status]);
        }

        return [
            'error' => [
                'code' => $code,
                'message' => $message,
                'status' => $status,
            ],
        ];
    }
}
