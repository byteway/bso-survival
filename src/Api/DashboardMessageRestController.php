<?php

namespace BSO\Survival\Api;

use BSO\Survival\Service\DashboardMessageService;
use BSO\Survival\Support\ApiResponse;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DashboardMessageRestController {
    private const NAMESPACE = 'bso-survival/v1';
    private const COLLECTION_ROUTE = '/dashboard/messages';
    private const ITEM_ROUTE = '/dashboard/messages/(?P<message_id>\d+)';
    private const ACTIVATE_ROUTE = '/dashboard/messages/(?P<message_id>\d+)/activate';
    private const DEACTIVATE_ROUTE = '/dashboard/messages/(?P<message_id>\d+)/deactivate';

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

        register_rest_route(self::NAMESPACE, self::ACTIVATE_ROUTE, [[
            'methods' => 'POST',
            'callback' => [$this, 'activateMessage'],
            'permission_callback' => [$this, 'canManage'],
        ]]);

        register_rest_route(self::NAMESPACE, self::DEACTIVATE_ROUTE, [[
            'methods' => 'POST',
            'callback' => [$this, 'deactivateMessage'],
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
    public function listMessages($request) {
        $eventId = $this->extractIntParam($request, 'event_id');
        $scope = $this->extractStringParam($request, 'scope');
        $page = $this->extractIntParam($request, 'page');
        if ($page <= 0) {
            $page = 1;
        }

        $perPage = $this->extractIntParam($request, 'per_page');
        if ($perPage <= 0) {
            $legacyLimit = $this->extractIntParam($request, 'limit');
            $perPage = $legacyLimit > 0 ? $legacyLimit : 20;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $resolvedScope = $scope !== '' ? $scope : 'all';

        try {
            $result = $this->messages->listPageForEvent($eventId, $page, $perPage, $resolvedScope);

            return ApiResponse::paginated(
                $result['items'],
                (int) ($result['total'] ?? 0),
                (int) ($result['page'] ?? $page),
                (int) ($result['per_page'] ?? $perPage),
                [
                    'event_id' => $eventId,
                    'scope' => $resolvedScope,
                ]
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('invalid_message_filter', $exception->getMessage(), 400);
        } catch (Throwable $exception) {
            return ApiResponse::error('message_list_failed', 'Meldingen konden niet worden opgehaald.', 500);
        }
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function createMessage($request) {
        $payload = [
            'event_id' => $this->extractIntParam($request, 'event_id'),
            'type' => $this->extractStringParam($request, 'type'),
            'text' => $this->extractStringParam($request, 'text'),
            'visibility' => $this->extractStringParam($request, 'visibility'),
            'status' => $this->extractStringParam($request, 'status'),
            'scope' => $this->extractStringParam($request, 'scope'),
            'changed_by' => $this->extractStringParam($request, 'changed_by'),
        ];

        try {
            $row = $this->messages->create($payload);
            return ApiResponse::success(['item' => $row], 201);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('invalid_message_payload', $exception->getMessage(), 400);
        } catch (RuntimeException $exception) {
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
        $messageId = $this->extractIntParam($request, 'message_id');
        $eventId = $this->extractIntParam($request, 'event_id');
        $status = $this->extractStringParam($request, 'status');
        $changedBy = $this->extractStringParam($request, 'changed_by');

        try {
            $row = $this->messages->setStatus($messageId, $eventId, $status, $changedBy);
            return ApiResponse::success(['item' => $row]);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('invalid_message_payload', $exception->getMessage(), 400);
        } catch (RuntimeException $exception) {
            return ApiResponse::error('message_update_failed', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return ApiResponse::error('message_update_failed', 'Melding kon niet worden bijgewerkt.', 500);
        }
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function activateMessage($request) {
        return $this->updateMessageWithStatus($request, 'actief');
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function deactivateMessage($request) {
        return $this->updateMessageWithStatus($request, 'inactief');
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    private function updateMessageWithStatus($request, string $status) {
        $payload = [
            'message_id' => $this->extractIntParam($request, 'message_id'),
            'event_id' => $this->extractIntParam($request, 'event_id'),
            'status' => $status,
            'changed_by' => $this->extractStringParam($request, 'changed_by'),
        ];

        return $this->updateMessage($payload);
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
}
