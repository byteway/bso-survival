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

    /**
     * Deterministic demo scores for the live event golden test set.
     *
     * Keyed by timeslot ordinal -> part name -> team name -> raw_value.
     * Generates scores for every timeslot (1..13) so any moment in the day
     * can be activated during scenario testing.
     *
     * - "Kano Bungee" uses time mode (seconds, lower_is_better, max 1200).
     * - All other parts use points mode (0..100).
     *
     * @return array<int, array<string, array<string, int>>>
     */
    public static function demoScores(): array {
        $partNames = [
            'Kanovaren',
            'Touwbaan',
            'Kasteelspel',
            'Kano Bungee',
            'Survivalbaan',
            'Vrachtauto / tokkelbaan',
            'Kano touwtrekken',
            'Water scheppen',
            'Water dragen',
            'Vlotten bouw',
            'Step-run',
            'Labyrint',
            'Paal zitten',
        ];

        $teamNames = [];
        for ($team = 1; $team <= 22; $team++) {
            $teamNames[] = 'Team' . str_pad((string) $team, 3, '0', STR_PAD_LEFT);
        }

        $scores = [];
        for ($slot = 1; $slot <= 13; $slot++) {
            $scores[$slot] = [];

            foreach ($partNames as $partIndex => $partName) {
                $scores[$slot][$partName] = [];

                foreach ($teamNames as $teamIndex => $teamName) {
                    $seed = ($slot * 131) + (($partIndex + 1) * 17) + (($teamIndex + 1) * 29);

                    if ($partName === 'Kano Bungee') {
                        // Time mode: keep values realistic and below max_time.
                        $value = 320 + ($seed % 581); // 320..900
                    } else {
                        // Points mode: spread around mid/high range for readable standings.
                        $value = 45 + ($seed % 51); // 45..95
                    }

                    $scores[$slot][$partName][$teamName] = $value;
                }
            }
        }

        return $scores;
    }
}
