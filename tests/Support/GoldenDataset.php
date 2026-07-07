<?php

namespace BSO\Survival\Tests\Support;

class GoldenDataset {
    /**
     * Deterministic golden dataset for unit and regression tests.
     *
     * @return array<string, mixed>
     */
    public static function v1(): array {
        return [
            'dataset_version' => '1.0.0',
            'event' => [
                'id' => 1,
                'name' => 'BSO Survival Golden Event',
                'event_date' => '2026-07-07',
                'status' => 'gepland',
            ],
            'parts' => [
                ['id' => 1, 'name' => 'Kanovaren', 'status' => 'actief'],
                ['id' => 2, 'name' => 'Touwbaan', 'status' => 'actief'],
                ['id' => 3, 'name' => 'Kasteelspel', 'status' => 'actief'],
                ['id' => 4, 'name' => 'Kano Bungee', 'status' => 'actief'],
                ['id' => 5, 'name' => 'Survivalbaan', 'status' => 'actief'],
                ['id' => 6, 'name' => 'Vrachtauto / tokkelbaan', 'status' => 'actief'],
                ['id' => 7, 'name' => 'Kano touwtrekken', 'status' => 'actief'],
                ['id' => 8, 'name' => 'Water scheppen', 'status' => 'actief'],
                ['id' => 9, 'name' => 'Water dragen', 'status' => 'actief'],
                ['id' => 10, 'name' => 'Vlotten bouw', 'status' => 'actief'],
                ['id' => 11, 'name' => 'Step-run', 'status' => 'actief'],
                ['id' => 12, 'name' => 'Labyrint', 'status' => 'actief'],
            ],
            'teams' => [
                ['id' => 1, 'name' => 'Team001', 'status' => 'ingeschreven'],
                ['id' => 2, 'name' => 'Team002', 'status' => 'ingeschreven'],
                ['id' => 3, 'name' => 'Team003', 'status' => 'ingeschreven'],
                ['id' => 4, 'name' => 'Team004', 'status' => 'ingeschreven'],
                ['id' => 5, 'name' => 'Team005', 'status' => 'ingeschreven'],
                ['id' => 6, 'name' => 'Team006', 'status' => 'ingeschreven'],
                ['id' => 7, 'name' => 'Team007', 'status' => 'ingeschreven'],
                ['id' => 8, 'name' => 'Team008', 'status' => 'ingeschreven'],
                ['id' => 9, 'name' => 'Team009', 'status' => 'ingeschreven'],
                ['id' => 10, 'name' => 'Team010', 'status' => 'ingeschreven'],
                ['id' => 11, 'name' => 'Team011', 'status' => 'ingeschreven'],
                ['id' => 12, 'name' => 'Team012', 'status' => 'ingeschreven'],
                ['id' => 13, 'name' => 'Team013', 'status' => 'ingeschreven'],
                ['id' => 14, 'name' => 'Team014', 'status' => 'ingeschreven'],
                ['id' => 15, 'name' => 'Team015', 'status' => 'ingeschreven'],
                ['id' => 16, 'name' => 'Team016', 'status' => 'ingeschreven'],
                ['id' => 17, 'name' => 'Team017', 'status' => 'ingeschreven'],
                ['id' => 18, 'name' => 'Team018', 'status' => 'ingeschreven'],
                ['id' => 19, 'name' => 'Team019', 'status' => 'ingeschreven'],
                ['id' => 20, 'name' => 'Team020', 'status' => 'ingeschreven'],
                ['id' => 21, 'name' => 'Team021', 'status' => 'ingeschreven'],
                ['id' => 22, 'name' => 'Team022', 'status' => 'ingeschreven'],
            ],
        ];
    }
}
