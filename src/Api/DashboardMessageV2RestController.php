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
    private const BULK_STATUS_ROUTE = '/dashboard/messages/bulk-status';

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
        ]);

        register_rest_route(self::NAMESPACE, self::BULK_STATUS_ROUTE, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'bulkUpdateStatus'],
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
}
