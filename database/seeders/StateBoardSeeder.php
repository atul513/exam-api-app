<?php

// FILE: database/seeders/StateBoardSeeder.php
// Run: php artisan db:seed --class=StateBoardSeeder

namespace Database\Seeders;

use App\Models\ExamSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StateBoardSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Root: State Boards ──
        $root = ExamSection::create([
            'name'        => 'State Boards',
            'slug'        => 'state-boards',
            'code'        => 'STATE_BOARDS',
            'type'        => 'exam_group',
            'description' => 'All Indian state education boards',
            'is_active'   => true,
            'is_featured' => true,
            'sort_order'  => 3,
        ]);

        // ── 2. All 28 States + 8 UTs ──
        $states = [
            ['name' => 'Andhra Pradesh Board',          'code' => 'AP',   'state' => 'Andhra Pradesh',          'board_name' => 'BSEAP'],
            ['name' => 'Arunachal Pradesh Board',       'code' => 'AR',   'state' => 'Arunachal Pradesh',       'board_name' => 'DSEAP'],
            ['name' => 'Assam Board',                   'code' => 'AS',   'state' => 'Assam',                   'board_name' => 'SEBA / AHSEC'],
            ['name' => 'Bihar Board',                   'code' => 'BR',   'state' => 'Bihar',                   'board_name' => 'BSEB'],
            ['name' => 'Chhattisgarh Board',            'code' => 'CG',   'state' => 'Chhattisgarh',            'board_name' => 'CGBSE'],
            ['name' => 'Goa Board',                     'code' => 'GA',   'state' => 'Goa',                     'board_name' => 'GBSHSE'],
            ['name' => 'Gujarat Board',                 'code' => 'GJ',   'state' => 'Gujarat',                 'board_name' => 'GSEB'],
            ['name' => 'Haryana Board',                 'code' => 'HR',   'state' => 'Haryana',                 'board_name' => 'BSEH'],
            ['name' => 'Himachal Pradesh Board',        'code' => 'HP',   'state' => 'Himachal Pradesh',        'board_name' => 'HPBOSE'],
            ['name' => 'Jharkhand Board',               'code' => 'JH',   'state' => 'Jharkhand',               'board_name' => 'JAC'],
            ['name' => 'Karnataka Board',               'code' => 'KA',   'state' => 'Karnataka',               'board_name' => 'KSEEB'],
            ['name' => 'Kerala Board',                  'code' => 'KL',   'state' => 'Kerala',                  'board_name' => 'DHSE Kerala'],
            ['name' => 'Madhya Pradesh Board',          'code' => 'MP',   'state' => 'Madhya Pradesh',          'board_name' => 'MPBSE'],
            ['name' => 'Maharashtra Board',             'code' => 'MH',   'state' => 'Maharashtra',             'board_name' => 'MSBSHSE'],
            ['name' => 'Manipur Board',                 'code' => 'MN',   'state' => 'Manipur',                 'board_name' => 'BOSEM / COHSEM'],
            ['name' => 'Meghalaya Board',               'code' => 'ML',   'state' => 'Meghalaya',               'board_name' => 'MBOSE'],
            ['name' => 'Mizoram Board',                 'code' => 'MZ',   'state' => 'Mizoram',                 'board_name' => 'MBSE'],
            ['name' => 'Nagaland Board',                'code' => 'NL',   'state' => 'Nagaland',                'board_name' => 'NBSE'],
            ['name' => 'Odisha Board',                  'code' => 'OD',   'state' => 'Odisha',                  'board_name' => 'BSE Odisha / CHSE'],
            ['name' => 'Punjab Board',                  'code' => 'PB',   'state' => 'Punjab',                  'board_name' => 'PSEB'],
            ['name' => 'Rajasthan Board',               'code' => 'RJ',   'state' => 'Rajasthan',               'board_name' => 'RBSE'],
            ['name' => 'Sikkim Board',                  'code' => 'SK',   'state' => 'Sikkim',                  'board_name' => 'SHSEB'],
            ['name' => 'Tamil Nadu Board',              'code' => 'TN',   'state' => 'Tamil Nadu',              'board_name' => 'TN SSLC / HSC'],
            ['name' => 'Telangana Board',               'code' => 'TS',   'state' => 'Telangana',               'board_name' => 'BSETS'],
            ['name' => 'Tripura Board',                 'code' => 'TR',   'state' => 'Tripura',                 'board_name' => 'TBSE'],
            ['name' => 'Uttar Pradesh Board',           'code' => 'UP',   'state' => 'Uttar Pradesh',           'board_name' => 'UPMSP'],
            ['name' => 'Uttarakhand Board',             'code' => 'UK',   'state' => 'Uttarakhand',             'board_name' => 'UBSE'],
            ['name' => 'West Bengal Board',             'code' => 'WB',   'state' => 'West Bengal',             'board_name' => 'WBBSE / WBCHSE'],

            // Union Territories
            ['name' => 'Delhi Board',                   'code' => 'DL',   'state' => 'Delhi',                   'board_name' => 'DSEB'],
            ['name' => 'Jammu & Kashmir Board',         'code' => 'JK',   'state' => 'Jammu & Kashmir',         'board_name' => 'JKBOSE'],
            ['name' => 'Chandigarh (follows Punjab)',    'code' => 'CH',   'state' => 'Chandigarh',              'board_name' => 'PSEB (shared)'],
            ['name' => 'Puducherry (follows TN)',        'code' => 'PY',   'state' => 'Puducherry',              'board_name' => 'TN Board (shared)'],
        ];

        $classes = [
            ['name' => 'Class 10', 'grade' => 10, 'label' => '10th'],
            ['name' => 'Class 11', 'grade' => 11, 'label' => '11th'],
            ['name' => 'Class 12', 'grade' => 12, 'label' => '12th'],
        ];

        $subjects = [
            ['name' => 'Physics',    'code_suffix' => 'PHY',  'sort' => 1],
            ['name' => 'Chemistry',  'code_suffix' => 'CHEM', 'sort' => 2],
            ['name' => 'Mathematics','code_suffix' => 'MATH', 'sort' => 3],
        ];

        $stateSort = 0;

        foreach ($states as $state) {
            $stateSort++;

            // ── Create State Board ──
            $stateBoard = ExamSection::create([
                'parent_id'   => $root->id,
                'name'        => $state['name'],
                'slug'        => Str::slug($state['name']),
                'code'        => 'SB_' . $state['code'],
                'type'        => 'state_board',
                'short_name'  => $state['code'],
                'meta'        => [
                    'state'      => $state['state'],
                    'state_code' => $state['code'],
                    'board_name' => $state['board_name'],
                ],
                'sort_order'  => $stateSort,
                'is_active'   => true,
            ]);

            foreach ($classes as $classInfo) {
                // ── Create Class ──
                $classSection = ExamSection::create([
                    'parent_id'  => $stateBoard->id,
                    'name'       => $classInfo['name'],
                    'slug'       => Str::slug($state['name'] . '-' . $classInfo['name']),
                    'code'       => 'SB_' . $state['code'] . '_C' . $classInfo['grade'],
                    'type'       => 'class',
                    'short_name' => $classInfo['label'],
                    'meta'       => [
                        'grade_number' => $classInfo['grade'],
                        'stream'       => 'Science',
                    ],
                    'sort_order' => $classInfo['grade'],
                    'is_active'  => true,
                ]);

                foreach ($subjects as $subject) {
                    // ── Create Subject ──
                    ExamSection::create([
                        'parent_id'  => $classSection->id,
                        'name'       => $subject['name'],
                        'slug'       => Str::slug($state['name'] . '-c' . $classInfo['grade'] . '-' . $subject['name']),
                        'code'       => 'SB_' . $state['code'] . '_C' . $classInfo['grade'] . '_' . $subject['code_suffix'],
                        'type'       => 'subject',
                        'meta'       => [
                            'subject_code' => $subject['code_suffix'],
                        ],
                        'sort_order' => $subject['sort'],
                        'is_active'  => true,
                    ]);
                }
            }

            $this->command->info("Created: {$state['name']} → 3 classes × 3 subjects");
        }

        // ── Summary ──
        $totalSections = ExamSection::count();
        $this->command->newLine();
        $this->command->info("=== STATE BOARD SEEDER COMPLETE ===");
        $this->command->info("Root:     1 (State Boards)");
        $this->command->info("States:   " . count($states));
        $this->command->info("Classes:  " . count($states) * count($classes));
        $this->command->info("Subjects: " . count($states) * count($classes) * count($subjects));
        $this->command->info("Total exam_sections created: " . $totalSections);
        // Expected: 1 + 32 + 96 + 288 = 417 rows
    }
}