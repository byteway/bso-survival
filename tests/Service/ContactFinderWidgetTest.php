<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Widgets\ContactFinderWidget;
use PHPUnit\Framework\TestCase;

class ContactFinderWidgetTest extends TestCase {
    /** @test */
    public function it_builds_searchable_contact_data_from_overview_teams(): void {
        $widget = new ContactFinderWidget();

        $data = $widget->getData([
            'teams' => [
                (object) [
                    'name' => 'Team Delta',
                    'contact_name' => 'Daan Delta',
                    'contact_phone' => '06 12345678',
                    'contact_email' => 'daan@example.test',
                    'status' => 'bevestigd',
                ],
                (object) [
                    'name' => 'Team Alfa',
                    'contact_name' => 'Anja Alfa',
                    'contact_phone' => '06 87654321',
                    'contact_email' => 'anja@example.test',
                    'status' => 'ingeschreven',
                ],
            ],
        ]);

        $this->assertSame(2, $data['total_contacts']);
        $this->assertCount(2, $data['contacts']);
        $this->assertSame('Team Alfa', $data['contacts'][0]['team_name']);
        $this->assertSame('Team Delta', $data['contacts'][1]['team_name']);
        $this->assertStringContainsString('team alfa', $data['contacts'][0]['search_index']);
        $this->assertStringContainsString('anja@example.test', $data['contacts'][0]['search_index']);
    }

    /** @test */
    public function it_renders_contact_search_ui_and_contact_links(): void {
        $widget = new ContactFinderWidget();

        $html = $widget->render([
            'widget_id' => 'contact_finder',
            'data' => [
                'contacts' => [
                    [
                        'team_name' => 'Team Orion',
                        'contact_name' => 'Olaf Orion',
                        'contact_phone' => '06 11112222',
                        'contact_email' => 'olaf@example.test',
                        'status' => 'bevestigd',
                        'search_index' => 'team orion olaf orion 06 11112222 olaf@example.test bevestigd',
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('data-bso-contact-search="1"', $html);
        $this->assertStringContainsString('data-bso-contact-clear="1"', $html);
        $this->assertStringContainsString('data-bso-contact-item="1"', $html);
        $this->assertStringContainsString('mailto:olaf@example.test', $html);
        $this->assertStringContainsString('https://wa.me/31611112222', $html);
        $this->assertStringContainsString('Team Orion', $html);
        $this->assertStringContainsString('Zoek op team, contact, e-mail, telefoon of status', $html);
        $this->assertStringContainsString('data-bso-contact-list="1" hidden="hidden"', $html);
        $this->assertStringContainsString('0 resultaten', $html);
    }

    /** @test */
    public function it_renders_empty_state_when_no_contacts_exist(): void {
        $widget = new ContactFinderWidget();

        $html = $widget->render([
            'widget_id' => 'contact_finder',
            'data' => [
                'contacts' => [],
            ],
        ]);

        $this->assertStringContainsString('Geen contactgegevens beschikbaar voor dit event.', $html);
        $this->assertStringNotContainsString('data-bso-contact-list="1"', $html);
    }
}
