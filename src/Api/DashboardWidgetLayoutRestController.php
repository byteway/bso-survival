<?php

namespace BSO\Survival\Api;

use BSO\Survival\Service\DashboardWidgetLayoutService;
use BSO\Survival\Support\Capabilities;
use BSO\Survival\Support\ApiResponse;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DashboardWidgetLayoutRestController {
    private const NAMESPACE = 'bso-survival/v1';
    private const ROUTE = '/dashboard-layout/(?P<event_id>\d+)';

    /** @var DashboardWidgetLayoutService */
    private $layoutService;

    public function __construct(DashboardWidgetLayoutService $layoutService) {
        $this->layoutService = $layoutService;
    }

    public function registerRoutes(): void {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(self::NAMESPACE, self::ROUTE, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getLayout'],
                'permission_callback' => [$this, 'canRead'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'updateLayout'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function canRead(): bool {
        return function_exists('current_user_can') && current_user_can('read');
    }

    /**
     * @param mixed $request
     */
    public function canManage($request = null): bool {
        if (!Capabilities::canManageSettings()) {
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
    public function getLayout($request) {
        try {
            $eventId = $this->extractEventId($request);
            $layout = $this->layoutService->getLayoutForEvent($eventId);

            return ApiResponse::success([
                'event_id' => $eventId,
                'layout' => $layout,
            ]);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('invalid_layout_payload', $exception->getMessage(), 400);
        } catch (RuntimeException $exception) {
            return ApiResponse::error('layout_fetch_failed', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return ApiResponse::error('layout_fetch_failed', 'Layout kon niet worden opgehaald.', 500);
        }
    }

    /**
     * @param mixed $request
     * @return mixed
     */
    public function updateLayout($request) {
        try {
            $eventId = $this->extractEventId($request);
            $layout = $this->extractLayoutPayload($request);

            $saved = $this->layoutService->saveLayoutForEvent($eventId, $layout);

            return ApiResponse::success([
                'event_id' => $eventId,
                'layout' => $saved,
                'updated' => true,
            ]);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('invalid_layout_payload', $exception->getMessage(), 400);
        } catch (RuntimeException $exception) {
            return ApiResponse::error('layout_save_failed', $exception->getMessage(), 409);
        } catch (Throwable $exception) {
            return ApiResponse::error('layout_save_failed', 'Layout kon niet worden opgeslagen.', 500);
        }
    }

    /**
     * @param mixed $request
     */
    private function extractEventId($request): int {
        if (is_object($request) && method_exists($request, 'get_param')) {
            return (int) $request->get_param('event_id');
        }

        if (is_array($request) && isset($request['event_id'])) {
            return (int) $request['event_id'];
        }

        return 0;
    }

    /**
     * @param mixed $request
     * @return array<string, array<int, string>>
     */
    private function extractLayoutPayload($request): array {
        if (is_object($request) && method_exists($request, 'get_param')) {
            $layout = $request->get_param('layout');
            return is_array($layout) ? $layout : [];
        }

        if (is_array($request) && isset($request['layout']) && is_array($request['layout'])) {
            return $request['layout'];
        }

        return [];
    }

}
