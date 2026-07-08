<?php

namespace BSO\Survival\Api;

use BSO\Survival\Service\DashboardMessageService;
use BSO\Survival\Support\ApiResponse;
use BSO\Survival\Support\Capabilities;
use InvalidArgumentException;
use Throwable;

class DashboardMessageV2RestController {
    private const NAMESPACE = 'bso-survival/v2';
    private const COLLECTION_ROUTE = '/dashboard/messages';
    private const ITEM_ROUTE = '/dashboard/messages/(?P<message_id>\d+)';
    private const BULK_STATUS_ROUTE = '/dashboard/messages/bulk-status';
    private const BULK_DELETE_ROUTE = '/dashboard/messages/bulk-delete';

    /** @var DashboardMessageService */
    private $messages;

    public function __construct(DashboardMessageService $messages) {
        $this->messages = $messages;
    }

    public function registerRoutes(): void {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(self::NAMESPACE, self::COLLECTION_ROUTE, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listMessages'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createMessage'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, self::ITEM_ROUTE, [[
            'methods' => 'PATCH',
            'callback' => [$this, 'updateMessage'],
            'permission_callback' => [$this, 'canManage'],
        ]]);

        register_rest_route(self::NAMESPACE, self::BULK_STATUS_ROUTE, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'bulkUpdateStatus'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, self::BULK_DELETE_ROUTE, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'bulkDeleteMessages'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    /**
     * @param mixed $request
     */
    public function canManage($request = null): bool {
        if (!Capabilities::canManageMessages()) {
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
    public function listMessages($request) {
        $eventId = $this->extractIntParam($request, 'event_id');
        $scope = $this->extractStringParam($request, 'scope');
        $status = $this->extractStringParam($request, 'status');
        $type = $this->extractStringParam($request, 'type');
        $visibleAt = $this->extractStringParam($request, 'visible_at');
        $search = $this->extractStringParam($request, 'search');

        $page = $this->extractIntParam($request, 'page');
        if ($page <= 0) {
            $page = 1;
        }

        $perPage = $this->extractIntParam($request, 'per_page');
        if ($perPage <= 0) {
            $perPage = 20;
        }

        try {
            $result = $this->messages->listAdvancedPageForEvent(
                $eventId,
                [
                    'scope' => $scope,
                    'status' => $status,
                    'type' => $type,
                    'visible_at' => $visibleAt,
                    'search' => $search,
                ],
                $page,
                $perPage
            );

            return ApiResponse::paginated(
                $result['items'],
                (int) ($result['total'] ?? 0),
                (int) ($result['page'] ?? $page),
                (int) ($result['per_page'] ?? $perPage),
                [
                    'event_id' => $eventId,
                    'filters' => $result['filters'] ?? [],
                ]
            );
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $code = (strpos($message, 'page') !== false || strpos($message, 'per_page') !== false)
                ? 'invalid_pagination'
                : 'invalid_filter';

            return ApiResponse::error($code, $message, 400);
        } catch (Throwable $exception) {
            return ApiResponse::error('message_list_failed', 'Meldingen konden niet worden opgehaald.', 500);
        }
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function createMessage($request) {
        try {
            $payload = [
                'event_id' => $this->extractIntParam($request, 'event_id'),
                'type' => $this->extractStringParam($request, 'type'),
                'text' => $this->extractStringParam($request, 'text'),
                'visibility' => $this->extractStringParam($request, 'visibility'),
                'status' => $this->extractStringParam($request, 'status'),
                'scope' => $this->extractStringParam($request, 'scope'),
                'visible_from' => $this->extractStringParam($request, 'visible_from'),
                'visible_until' => $this->extractStringParam($request, 'visible_until'),
                'changed_by' => $this->extractStringParam($request, 'changed_by'),
            ];

            $metaPayload = $this->extractMetaPayload($request);
            if ($metaPayload['has_meta']) {
                $payload['meta_data'] = $metaPayload['meta_data'];
            } elseif ($metaPayload['has_legacy_meta_data']) {
                $payload['meta_data'] = $metaPayload['legacy_meta_data'];
            }

            $row = $this->messages->create($payload);
            return ApiResponse::success(['item' => $row], 201);
        } catch (InvalidArgumentException $exception) {
            if ($this->isMetaValidationMessage($exception->getMessage())) {
                return ApiResponse::error('invalid_meta_block', $exception->getMessage(), 400);
            }

            return ApiResponse::error('invalid_message_payload', $exception->getMessage(), 400);
        } catch (\RuntimeException $exception) {
            return ApiResponse::error('message_create_failed', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return ApiResponse::error('message_create_failed', 'Melding kon niet worden opgeslagen.', 500);
        }
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function updateMessage($request) {
        try {
            $messageId = $this->extractIntParam($request, 'message_id');
            $eventId = $this->extractIntParam($request, 'event_id');
            $changedBy = $this->extractStringParam($request, 'changed_by');
            $payload = [];

            $type = $this->extractOptionalStringParam($request, 'type');
            if ($type !== null) {
                $payload['type'] = $type;
            }

            $text = $this->extractOptionalStringParam($request, 'text');
            if ($text !== null) {
                $payload['text'] = $text;
            }

            $visibility = $this->extractOptionalStringParam($request, 'visibility');
            if ($visibility !== null) {
                $payload['visibility'] = $visibility;
            }

            $status = $this->extractOptionalStringParam($request, 'status');
            if ($status !== null) {
                $payload['status'] = $status;
            }

            $scope = $this->extractOptionalStringParam($request, 'scope');
            if ($scope !== null) {
                $payload['scope'] = $scope;
            }

            $visibleFrom = $this->extractOptionalStringParam($request, 'visible_from');
            if ($visibleFrom !== null) {
                $payload['visible_from'] = $visibleFrom;
            }

            $visibleUntil = $this->extractOptionalStringParam($request, 'visible_until');
            if ($visibleUntil !== null) {
                $payload['visible_until'] = $visibleUntil;
            }

            $metaPayload = $this->extractMetaPayload($request);
            if ($metaPayload['has_meta']) {
                $payload['meta_data'] = $metaPayload['meta_data'];
            } elseif ($metaPayload['has_legacy_meta_data']) {
                $payload['meta_data'] = $metaPayload['legacy_meta_data'];
            }

            $row = $this->messages->update($messageId, $eventId, $payload, $changedBy);
            return ApiResponse::success(['item' => $row]);
        } catch (InvalidArgumentException $exception) {
            if ($this->isMetaValidationMessage($exception->getMessage())) {
                return ApiResponse::error('invalid_meta_block', $exception->getMessage(), 400);
            }

            return ApiResponse::error('invalid_message_payload', $exception->getMessage(), 400);
        } catch (\RuntimeException $exception) {
            return ApiResponse::error('message_update_failed', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return ApiResponse::error('message_update_failed', 'Melding kon niet worden bijgewerkt.', 500);
        }
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function bulkUpdateStatus($request) {
        $eventId = $this->extractIntParam($request, 'event_id');
        $messageIds = $this->extractArrayParam($request, 'message_ids');
        $status = $this->extractStringParam($request, 'status');
        $changedBy = $this->extractStringParam($request, 'changed_by');

        try {
            $result = $this->messages->bulkSetStatusForEvent($eventId, $messageIds, $status, $changedBy);
            return ApiResponse::success(['result' => $result]);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('invalid_bulk_payload', $exception->getMessage(), 400);
        } catch (\RuntimeException $exception) {
            return ApiResponse::error('bulk_update_conflict', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return ApiResponse::error('bulk_update_failed', 'Bulk status update kon niet worden uitgevoerd.', 500);
        }
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function bulkDeleteMessages($request) {
        $eventId = $this->extractIntParam($request, 'event_id');
        $messageIds = $this->extractArrayParam($request, 'message_ids');
        $confirm = $this->extractBoolParam($request, 'confirm');
        $changedBy = $this->extractStringParam($request, 'changed_by');

        try {
            $result = $this->messages->bulkDeleteForEvent($eventId, $messageIds, $confirm, $changedBy);
            return ApiResponse::success(['result' => $result]);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('invalid_bulk_payload', $exception->getMessage(), 400);
        } catch (\RuntimeException $exception) {
            return ApiResponse::error('bulk_delete_conflict', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return ApiResponse::error('bulk_delete_failed', 'Bulk delete kon niet worden uitgevoerd.', 500);
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
     * @return array<int, mixed>
     */
    private function extractArrayParam($request, string $key): array {
        if (is_object($request) && method_exists($request, 'get_param')) {
            $value = $request->get_param($key);
            return is_array($value) ? array_values($value) : [];
        }

        if (is_array($request) && isset($request[$key]) && is_array($request[$key])) {
            return array_values($request[$key]);
        }

        return [];
    }

    /** @param mixed $request */
    private function extractOptionalStringParam($request, string $key): ?string {
        if (is_object($request) && method_exists($request, 'get_param')) {
            $value = $request->get_param($key);
            return $value === null ? null : trim((string) $value);
        }

        if (is_array($request) && array_key_exists($key, $request)) {
            return trim((string) $request[$key]);
        }

        return null;
    }

    /**
     * @param mixed $request
     * @return array{has_meta: bool, meta_data: array<string, mixed>, has_legacy_meta_data: bool, legacy_meta_data: mixed}
     */
    private function extractMetaPayload($request): array {
        $hasMeta = $this->hasParam($request, 'meta');
        $rawMeta = $this->extractRawParam($request, 'meta');

        $hasLegacy = $this->hasParam($request, 'meta_data');
        $legacyMeta = $this->extractRawParam($request, 'meta_data');

        $result = [
            'has_meta' => false,
            'meta_data' => [],
            'has_legacy_meta_data' => false,
            'legacy_meta_data' => null,
        ];

        if ($hasMeta) {
            if ($rawMeta === null || $rawMeta === '') {
                $result['has_meta'] = true;
                $result['meta_data'] = [];
                return $result;
            }

            if (!is_array($rawMeta)) {
                throw new InvalidArgumentException('meta moet een object zijn.');
            }

            $allowedKeys = ['source', 'labels', 'trace_id'];
            $unknown = array_diff(array_keys($rawMeta), $allowedKeys);
            if ($unknown !== []) {
                throw new InvalidArgumentException('meta bevat onbekende velden: ' . implode(',', $unknown));
            }

            $normalized = [];

            if (array_key_exists('source', $rawMeta)) {
                $source = trim((string) $rawMeta['source']);
                if ($source === '') {
                    throw new InvalidArgumentException('meta.source moet een niet-lege string zijn.');
                }

                $normalized['source'] = $source;
            }

            if (array_key_exists('trace_id', $rawMeta)) {
                $traceId = trim((string) $rawMeta['trace_id']);
                if ($traceId === '') {
                    throw new InvalidArgumentException('meta.trace_id moet een niet-lege string zijn.');
                }

                $normalized['trace_id'] = $traceId;
            }

            if (array_key_exists('labels', $rawMeta)) {
                if (!is_array($rawMeta['labels'])) {
                    throw new InvalidArgumentException('meta.labels moet een array van strings zijn.');
                }

                $labels = [];
                foreach ($rawMeta['labels'] as $label) {
                    $value = trim((string) $label);
                    if ($value === '') {
                        throw new InvalidArgumentException('meta.labels mag geen lege waarden bevatten.');
                    }

                    $labels[] = $value;
                }

                $normalized['labels'] = array_values(array_unique($labels));
            }

            $result['has_meta'] = true;
            $result['meta_data'] = $normalized;
            return $result;
        }

        if ($hasLegacy) {
            $result['has_legacy_meta_data'] = true;
            $result['legacy_meta_data'] = $legacyMeta;
        }

        return $result;
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

    private function isMetaValidationMessage(string $message): bool {
        return strpos($message, 'meta') === 0;
    }

    /** @param mixed $request */
    private function extractBoolParam($request, string $key): bool {
        $raw = null;

        if (is_object($request) && method_exists($request, 'get_param')) {
            $raw = $request->get_param($key);
        } elseif (is_array($request) && array_key_exists($key, $request)) {
            $raw = $request[$key];
        }

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw)) {
            return $raw === 1;
        }

        if (is_string($raw)) {
            $normalized = strtolower(trim($raw));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
