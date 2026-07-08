<?php

namespace BSO\Survival\Widgets;

use BSO\Survival\Contracts\DashboardWidgetInterface;
use BSO\Survival\Database\Repository\DashboardMessageRepository;
use BSO\Survival\Service\DashboardMessageService;

class MessageWidget implements DashboardWidgetInterface {
    /** @var DashboardMessageService */
    private $messages;

    public function __construct(DashboardMessageService $messages = null) {
        $this->messages = $messages ?? new DashboardMessageService(new DashboardMessageRepository());
    }

    public function getId(): string { return 'message_widget'; }
    public function getTitle(): string { return 'Meldingen'; }
    public function getPriority(): int { return 40; }
    public function getCapabilities(): array { return ['read']; }

    public function getData(array $overview, array $filters = []): array {
        $eventId = (int) ($overview['event']->id ?? 0);
        $eventName = (string) ($overview['event']->name ?? 'event');

        if ($eventId > 0) {
            try {
                $rows = $this->messages->listActiveForEvent($eventId, 5);
                if ($rows !== []) {
                    $items = [];
                    foreach ($rows as $row) {
                        $items[] = [
                            'type' => (string) ($row->type ?? 'info'),
                            'text' => (string) ($row->text ?? ''),
                        ];
                    }

                    return ['items' => $items];
                }
            } catch (\Throwable $exception) {
            }
        }

        return [
            'message' => sprintf('Dashboard actief voor %s.', $eventName),
            'items' => [],
        ];
    }

    public function render(array $context): string {
        $items = $context['data']['items'] ?? [];
        if (is_array($items) && $items !== []) {
            $html = '<article class="bso-widget bso-widget-message"><h3>' . esc_html($this->getTitle()) . '</h3><ul>';
            foreach ($items as $item) {
                $type = esc_html((string) ($item['type'] ?? 'info'));
                $text = esc_html((string) ($item['text'] ?? ''));
                $html .= '<li><strong>' . $type . '</strong>: ' . $text . '</li>';
            }
            $html .= '</ul></article>';

            return $html;
        }

        return '<article class="bso-widget bso-widget-message"><h3>' . esc_html($this->getTitle()) . '</h3><p>' .
            esc_html((string) ($context['data']['message'] ?? '')) . '</p></article>';
    }

    public function getScriptDependencies(): array { return []; }
    public function getStyleDependencies(): array { return ['bso-survival-dashboard-widgets']; }
}
