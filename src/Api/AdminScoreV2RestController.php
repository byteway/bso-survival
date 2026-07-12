<?php

namespace BSO\Survival\Api;

use BSO\Survival\Service\AdminScoreService;
use BSO\Survival\Service\PartConfirmationService;
use BSO\Survival\Support\ApiResponse;
use BSO\Survival\Support\Capabilities;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AdminScoreV2RestController {
    private const NAMESPACE = 'bso-survival/v2';
    private const CREATE_ROUTE = '/scores/entries';
    private const UPDATE_ROUTE = '/scores/entries/(?P<score_entry_id>\d+)';

    /** @var AdminScoreService */
    private $scores;

    /** @var PartConfirmationService|null */
    private $partConfirmations;

    public function __construct(AdminScoreService $scores, PartConfirmationService $partConfirmations = null) {
        $this->scores = $scores;
        $this->partConfirmations = $partConfirmations;
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

        register_rest_route(self::NAMESPACE, '/scores/parts/(?P<part_id>\d+)/status', [[
            'methods' => 'GET',
            'callback' => [$this, 'partStatus'],
            'permission_callback' => [$this, 'canManage'],
        ]]);

        register_rest_route(self::NAMESPACE, '/scores/parts/(?P<part_id>\d+)/confirm', [[
            'methods' => 'POST',
            'callback' => [$this, 'confirmPart'],
            'permission_callback' => [$this, 'canManage'],
        ]]);
    }

    /**
     * @param mixed $request
     */
    public function canManage($request = null): bool {
        if (!Capabilities::canManageScores()) {
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
        try {
            $payload = [
                'event_id' => $this->extractIntParam($request, 'event_id'),
                'assignment_id' => $this->extractIntParam($request, 'assignment_id'),
                'raw_value' => $this->extractRawParam($request, 'raw_value'),
                'bonus_points' => $this->extractRawParam($request, 'bonus_points'),
                'joker_applied' => $this->extractBoolParam($request, 'joker_applied'),
                'joker_validated_by' => $this->extractStringParam($request, 'joker_validated_by'),
                'changed_by' => $this->extractStringParam($request, 'changed_by'),
                'entered_by_role' => $this->extractStringParam($request, 'entered_by_role'),
            ];

            if ($this->hasParam($request, 'meta')) {
                $payload['meta'] = $this->normalizeMeta($this->extractRawParam($request, 'meta'));
            }

            $result = $this->scores->create($payload);
            return ApiResponse::success(['result' => $result], 201);
        } catch (InvalidArgumentException $exception) {
            if ($this->isMetaValidationMessage($exception->getMessage())) {
                return ApiResponse::error('invalid_meta_block', $exception->getMessage(), 400);
            }

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

        try {
            $payload = [
                'event_id' => $this->extractIntParam($request, 'event_id'),
                'raw_value' => $this->extractRawParam($request, 'raw_value'),
                'bonus_points' => $this->extractRawParam($request, 'bonus_points'),
                'joker_applied' => $this->extractBoolParam($request, 'joker_applied'),
                'joker_validated_by' => $this->extractStringParam($request, 'joker_validated_by'),
                'changed_by' => $this->extractStringParam($request, 'changed_by'),
                'entered_by_role' => $this->extractStringParam($request, 'entered_by_role'),
            ];

            if ($this->hasParam($request, 'meta')) {
                $payload['meta'] = $this->normalizeMeta($this->extractRawParam($request, 'meta'));
            }

            $result = $this->scores->update($scoreEntryId, $payload);
            return ApiResponse::success(['result' => $result]);
        } catch (InvalidArgumentException $exception) {
            if ($this->isMetaValidationMessage($exception->getMessage())) {
                return ApiResponse::error('invalid_meta_block', $exception->getMessage(), 400);
            }

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
    public function partStatus($request) {
        if ($this->partConfirmations === null) {
            return ApiResponse::error('part_confirmation_unavailable', 'Onderdeelbevestiging is niet beschikbaar.', 501);
        }

        $eventId = $this->extractIntParam($request, 'event_id');
        $partId = $this->extractIntParam($request, 'part_id');

        try {
            return ApiResponse::success([
                'status' => $this->partConfirmations->getPartStatus($eventId, $partId),
            ]);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('invalid_score_input', $exception->getMessage(), 400);
        } catch (RuntimeException $exception) {
            return ApiResponse::error('score_update_blocked', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return ApiResponse::error('part_status_failed', 'Status van onderdeel kon niet worden opgehaald.', 500);
        }
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function confirmPart($request) {
        if ($this->partConfirmations === null) {
            return ApiResponse::error('part_confirmation_unavailable', 'Onderdeelbevestiging is niet beschikbaar.', 501);
        }

        $eventId = $this->extractIntParam($request, 'event_id');
        $partId = $this->extractIntParam($request, 'part_id');
        $confirmNoChanges = $this->extractBoolParam($request, 'confirm_no_changes');
        $changedBy = $this->extractStringParam($request, 'changed_by');

        if ($changedBy === '') {
            $changedBy = 'scheidsrechter';
        }

        try {
            $result = $this->partConfirmations->confirmPart($eventId, $partId, $changedBy, $confirmNoChanges);
            return ApiResponse::success(['result' => $result]);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('invalid_score_input', $exception->getMessage(), 400);
        } catch (RuntimeException $exception) {
            return ApiResponse::error('score_update_blocked', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return ApiResponse::error('part_confirm_failed', 'Onderdeel kon niet worden bevestigd.', 500);
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

    /** @param mixed $request */
    private function hasParam($request, string $key): bool {
        if (is_object($request) && method_exists($request, 'has_param')) {
            return (bool) $request->has_param($key);
        }

        if (is_array($request)) {
            return array_key_exists($key, $request);
        }

        if (is_object($request) && method_exists($request, 'get_param')) {
            return $request->get_param($key) !== null;
        }

        return false;
    }

    /**
     * @param mixed $meta
     * @return array<string, mixed>
     */
    private function normalizeMeta($meta): array {
        if (!is_array($meta)) {
            throw new InvalidArgumentException('meta moet een object zijn.');
        }

        $allowedKeys = ['source', 'labels', 'trace_id'];
        $unknown = array_diff(array_keys($meta), $allowedKeys);
        if ($unknown !== []) {
            throw new InvalidArgumentException('meta bevat onbekende velden: ' . implode(',', $unknown));
        }

        $normalized = [];

        if (array_key_exists('source', $meta)) {
            $source = trim((string) $meta['source']);
            if ($source === '') {
                throw new InvalidArgumentException('meta.source moet een niet-lege string zijn.');
            }

            $normalized['source'] = $source;
        }

        if (array_key_exists('trace_id', $meta)) {
            $traceId = trim((string) $meta['trace_id']);
            if ($traceId === '') {
                throw new InvalidArgumentException('meta.trace_id moet een niet-lege string zijn.');
            }

            $normalized['trace_id'] = $traceId;
        }

        if (array_key_exists('labels', $meta)) {
            if (!is_array($meta['labels'])) {
                throw new InvalidArgumentException('meta.labels moet een array van strings zijn.');
            }

            $labels = [];
            foreach ($meta['labels'] as $label) {
                $value = trim((string) $label);
                if ($value === '') {
                    throw new InvalidArgumentException('meta.labels mag geen lege waarden bevatten.');
                }

                $labels[] = $value;
            }

            $normalized['labels'] = array_values(array_unique($labels));
        }

        return $normalized;
    }

    private function isMetaValidationMessage(string $message): bool {
        return strpos($message, 'meta') === 0;
    }
}
