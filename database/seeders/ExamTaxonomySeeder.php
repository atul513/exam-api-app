<?php

// FILE: database/seeders/ExamTaxonomySeeder.php
// Run: php artisan db:seed --class=ExamTaxonomySeeder
//
// Creates complete hierarchy:
// 1. Competitive Exams (JEE, NEET, GATE, CAT, UPSC + variants + subjects + chapters)
// 2. Government Exams (SSC, Railway, Banking, State PSC)
// 3. Academic Boards (CBSE, ICSE → Classes → Subjects → Chapters)
// 4. State Boards (All 28 states + UTs → Classes → Subjects)
//
// Estimated total: ~2000+ exam_section rows

namespace Database\Seeders;

use App\Models\ExamSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExamTaxonomySeeder extends Seeder
{
    private int $created = 0;

    public function run(): void
    {
        $this->command->info('Starting Exam Taxonomy Seeder...');
        $this->command->newLine();

        $this->seedCompetitiveExams();
        $this->seedGovernmentExams();
        $this->seedAcademicBoards();
        $this->seedStateBoards();

        $this->command->newLine();
        $this->command->info("=== SEEDER COMPLETE: {$this->created} exam_sections created ===");
    }

    // ================================================================
    // 1. COMPETITIVE EXAMS
    // ================================================================

    private function seedCompetitiveExams(): void
    {
        $group = $this->create([
            'name' => 'Competitive Exams', 'code' => 'COMPETITIVE', 'type' => 'exam_group',
            'description' => 'National & international competitive entrance exams',
            'is_featured' => true, 'sort_order' => 1,
        ]);

        // ── JEE ──
        $jee = $this->create([
            'parent_id' => $group->id, 'name' => 'JEE (Joint Entrance Exam)', 'short_name' => 'JEE',
            'code' => 'JEE', 'type' => 'exam', 'is_featured' => true, 'sort_order' => 1,
            'meta' => ['conducting_body' => 'NTA', 'frequency' => 'Twice a year', 'website' => 'https://jeemain.nta.nic.in'],
        ]);

        $jeeMains = $this->create([
            'parent_id' => $jee->id, 'name' => 'JEE Mains', 'code' => 'JEE_MAINS', 'type' => 'exam_variant',
            'meta' => ['for' => 'NITs, IIITs, CFTIs'], 'sort_order' => 1,
        ]);
        $this->addSubjectsWithChapters($jeeMains->id, 'JEE_MAINS', $this->jeePhysicsChapters(), $this->jeeChemistryChapters(), $this->jeeMathsChapters());

        $jeeAdv = $this->create([
            'parent_id' => $jee->id, 'name' => 'JEE Advanced', 'code' => 'JEE_ADV', 'type' => 'exam_variant',
            'meta' => ['for' => 'IITs only'], 'sort_order' => 2,
        ]);
        $this->addSubjectsWithChapters($jeeAdv->id, 'JEE_ADV', $this->jeePhysicsChapters(), $this->jeeChemistryChapters(), $this->jeeMathsChapters());

        // ── Years for JEE ──
        foreach ([2020, 2021, 2022, 2023, 2024, 2025, 2026] as $yr) {
            $this->create(['parent_id' => $jee->id, 'name' => "PYQ {$yr}", 'code' => "JEE_PYQ_{$yr}", 'type' => 'year', 'meta' => ['year' => $yr], 'sort_order' => $yr]);
        }

        // ── NEET ──
        $neet = $this->create([
            'parent_id' => $group->id, 'name' => 'NEET (National Eligibility cum Entrance Test)', 'short_name' => 'NEET',
            'code' => 'NEET', 'type' => 'exam', 'is_featured' => true, 'sort_order' => 2,
            'meta' => ['conducting_body' => 'NTA', 'frequency' => 'Once a year', 'for' => 'MBBS, BDS, AYUSH admissions'],
        ]);
        $this->addSubjectsWithChapters($neet->id, 'NEET', $this->neetPhysicsChapters(), $this->neetChemistryChapters(), $this->neetBiologyChapters(), true);

        foreach ([2020, 2021, 2022, 2023, 2024, 2025, 2026] as $yr) {
            $this->create(['parent_id' => $neet->id, 'name' => "PYQ {$yr}", 'code' => "NEET_PYQ_{$yr}", 'type' => 'year', 'meta' => ['year' => $yr], 'sort_order' => $yr]);
        }

        // ── GATE ──
        $gate = $this->create([
            'parent_id' => $group->id, 'name' => 'GATE (Graduate Aptitude Test in Engineering)', 'short_name' => 'GATE',
            'code' => 'GATE', 'type' => 'exam', 'sort_order' => 3,
            'meta' => ['conducting_body' => 'IITs (rotating)', 'for' => 'M.Tech, PSU recruitment'],
        ]);
        foreach (['Computer Science (CS)', 'Electronics (EC)', 'Mechanical (ME)', 'Electrical (EE)', 'Civil (CE)', 'Chemical (CH)', 'Biotechnology (BT)'] as $i => $paper) {
            $this->create(['parent_id' => $gate->id, 'name' => $paper, 'code' => 'GATE_' . explode(' ', $paper)[0], 'type' => 'exam_variant', 'sort_order' => $i + 1]);
        }

        // ── CAT ──
        $cat = $this->create([
            'parent_id' => $group->id, 'name' => 'CAT (Common Admission Test)', 'short_name' => 'CAT',
            'code' => 'CAT', 'type' => 'exam', 'sort_order' => 4,
            'meta' => ['conducting_body' => 'IIMs (rotating)', 'for' => 'MBA admissions'],
        ]);
        foreach (['Quantitative Aptitude', 'Verbal Ability & Reading Comprehension', 'Data Interpretation & Logical Reasoning'] as $i => $sec) {
            $this->create(['parent_id' => $cat->id, 'name' => $sec, 'code' => 'CAT_S' . ($i + 1), 'type' => 'subject', 'sort_order' => $i + 1]);
        }

        // ── UPSC ──
        $upsc = $this->create([
            'parent_id' => $group->id, 'name' => 'UPSC Civil Services', 'short_name' => 'UPSC',
            'code' => 'UPSC', 'type' => 'exam', 'sort_order' => 5,
            'meta' => ['conducting_body' => 'UPSC', 'for' => 'IAS, IPS, IFS'],
        ]);
        $prelims = $this->create(['parent_id' => $upsc->id, 'name' => 'Prelims (CSAT)', 'code' => 'UPSC_PRE', 'type' => 'exam_variant', 'sort_order' => 1]);
        foreach (['General Studies Paper I', 'General Studies Paper II (CSAT)'] as $i => $p) {
            $this->create(['parent_id' => $prelims->id, 'name' => $p, 'code' => 'UPSC_PRE_P' . ($i + 1), 'type' => 'subject', 'sort_order' => $i + 1]);
        }
        $mains = $this->create(['parent_id' => $upsc->id, 'name' => 'Mains', 'code' => 'UPSC_MAINS', 'type' => 'exam_variant', 'sort_order' => 2]);
        foreach (['Essay', 'GS Paper I (History, Culture)', 'GS Paper II (Governance, Polity)', 'GS Paper III (Economy, Env)', 'GS Paper IV (Ethics)', 'Optional Paper I', 'Optional Paper II'] as $i => $p) {
            $this->create(['parent_id' => $mains->id, 'name' => $p, 'code' => 'UPSC_M_P' . ($i + 1), 'type' => 'subject', 'sort_order' => $i + 1]);
        }

        // ── CLAT ──
        $this->create([
            'parent_id' => $group->id, 'name' => 'CLAT (Common Law Admission Test)', 'short_name' => 'CLAT',
            'code' => 'CLAT', 'type' => 'exam', 'sort_order' => 6,
            'meta' => ['conducting_body' => 'NLSIU Consortium', 'for' => 'NLU Law admissions'],
        ]);

        // ── NDA ──
        $this->create([
            'parent_id' => $group->id, 'name' => 'NDA (National Defence Academy)', 'short_name' => 'NDA',
            'code' => 'NDA', 'type' => 'exam', 'sort_order' => 7,
            'meta' => ['conducting_body' => 'UPSC', 'for' => 'Army, Navy, Air Force'],
        ]);

        // ── CUET ──
        $this->create([
            'parent_id' => $group->id, 'name' => 'CUET (Common University Entrance Test)', 'short_name' => 'CUET',
            'code' => 'CUET', 'type' => 'exam', 'sort_order' => 8,
            'meta' => ['conducting_body' => 'NTA', 'for' => 'Central university UG admissions'],
        ]);

        $this->command->info("Competitive Exams: done");
    }

    // ================================================================
    // 2. GOVERNMENT EXAMS
    // ================================================================

    private function seedGovernmentExams(): void
    {
        $group = $this->create([
            'name' => 'Government Exams', 'code' => 'GOVT', 'type' => 'exam_group',
            'description' => 'SSC, Railway, Banking, State PSC and other government recruitment exams',
            'is_featured' => true, 'sort_order' => 2,
        ]);

        // ── SSC ──
        $ssc = $this->create(['parent_id' => $group->id, 'name' => 'SSC (Staff Selection Commission)', 'short_name' => 'SSC', 'code' => 'SSC', 'type' => 'exam', 'sort_order' => 1, 'meta' => ['conducting_body' => 'SSC']]);
        foreach (['SSC CGL', 'SSC CHSL', 'SSC MTS', 'SSC GD Constable', 'SSC CPO', 'SSC Stenographer', 'SSC JE'] as $i => $v) {
            $variant = $this->create(['parent_id' => $ssc->id, 'name' => $v, 'code' => Str::upper(Str::snake(str_replace(['(', ')'], '', $v))), 'type' => 'exam_variant', 'sort_order' => $i + 1]);
            $this->addGovtExamSubjects($variant->id, Str::upper(Str::snake(str_replace(['(', ')', ' '], ['', '', '_'], $v))));
        }

        // ── Railway ──
        $railway = $this->create(['parent_id' => $group->id, 'name' => 'Railway Exams', 'short_name' => 'RRB', 'code' => 'RAILWAY', 'type' => 'exam', 'sort_order' => 2, 'meta' => ['conducting_body' => 'RRB']]);
        foreach (['RRB NTPC', 'RRB Group D', 'RRB JE', 'RRB ALP', 'RPF Constable', 'RPF SI'] as $i => $v) {
            $variant = $this->create(['parent_id' => $railway->id, 'name' => $v, 'code' => Str::upper(str_replace(' ', '_', $v)), 'type' => 'exam_variant', 'sort_order' => $i + 1]);
            $this->addGovtExamSubjects($variant->id, Str::upper(str_replace(' ', '_', $v)));
        }

        // ── Banking ──
        $banking = $this->create(['parent_id' => $group->id, 'name' => 'Banking Exams', 'short_name' => 'Bank', 'code' => 'BANKING', 'type' => 'exam', 'sort_order' => 3, 'meta' => ['conducting_body' => 'IBPS / SBI / RBI']]);
        foreach (['IBPS PO', 'IBPS Clerk', 'SBI PO', 'SBI Clerk', 'RBI Grade B', 'RBI Assistant', 'NABARD Grade A'] as $i => $v) {
            $variant = $this->create(['parent_id' => $banking->id, 'name' => $v, 'code' => Str::upper(str_replace(' ', '_', $v)), 'type' => 'exam_variant', 'sort_order' => $i + 1]);
            $this->addBankingSubjects($variant->id, Str::upper(str_replace(' ', '_', $v)));
        }

        // ── State PSC ──
        $psc = $this->create(['parent_id' => $group->id, 'name' => 'State PSC Exams', 'short_name' => 'PSC', 'code' => 'STATE_PSC', 'type' => 'exam', 'sort_order' => 4]);
        foreach (['UPPSC', 'MPPSC', 'BPSC', 'RPSC', 'HPSC', 'WBPSC', 'TNPSC', 'KPSC', 'APPSC', 'TSPSC', 'GPSC', 'MPSC'] as $i => $v) {
            $this->create(['parent_id' => $psc->id, 'name' => $v, 'code' => $v, 'type' => 'exam_variant', 'sort_order' => $i + 1]);
        }

        // ── Defence ──
        $defence = $this->create(['parent_id' => $group->id, 'name' => 'Defence Exams', 'short_name' => 'Defence', 'code' => 'DEFENCE', 'type' => 'exam', 'sort_order' => 5]);
        foreach (['CDS', 'AFCAT', 'Indian Navy AA/SSR', 'Indian Army GD', 'Territorial Army'] as $i => $v) {
            $this->create(['parent_id' => $defence->id, 'name' => $v, 'code' => 'DEF_' . Str::upper(Str::slug($v, '_')), 'type' => 'exam_variant', 'sort_order' => $i + 1]);
        }

        // ── Insurance ──
        $ins = $this->create(['parent_id' => $group->id, 'name' => 'Insurance Exams', 'code' => 'INSURANCE', 'type' => 'exam', 'sort_order' => 6]);
        foreach (['LIC AAO', 'LIC ADO', 'NIACL AO', 'UIIC AO'] as $i => $v) {
            $this->create(['parent_id' => $ins->id, 'name' => $v, 'code' => Str::upper(str_replace(' ', '_', $v)), 'type' => 'exam_variant', 'sort_order' => $i + 1]);
        }

        // ── Teaching ──
        $teach = $this->create(['parent_id' => $group->id, 'name' => 'Teaching Exams', 'code' => 'TEACHING', 'type' => 'exam', 'sort_order' => 7]);
        foreach (['CTET', 'SUPER TET', 'KVS', 'NVS', 'DSSSB TGT/PGT', 'UGC NET'] as $i => $v) {
            $this->create(['parent_id' => $teach->id, 'name' => $v, 'code' => 'TCH_' . Str::upper(Str::slug($v, '_')), 'type' => 'exam_variant', 'sort_order' => $i + 1]);
        }

        $this->command->info("Government Exams: done");
    }

    // ================================================================
    // 3. ACADEMIC BOARDS (CBSE, ICSE)
    // ================================================================

    private function seedAcademicBoards(): void
    {
        $group = $this->create([
            'name' => 'Academic Boards', 'code' => 'ACADEMIC', 'type' => 'exam_group',
            'description' => 'CBSE, ICSE and other national education boards',
            'is_featured' => true, 'sort_order' => 3,
        ]);

        // ── CBSE ──
        $cbse = $this->create([
            'parent_id' => $group->id, 'name' => 'CBSE', 'code' => 'CBSE', 'type' => 'board',
            'is_featured' => true, 'sort_order' => 1,
            'meta' => ['full_name' => 'Central Board of Secondary Education'],
        ]);
        $this->addClassesWithSubjectsAndChapters($cbse->id, 'CBSE');

        // ── ICSE ──
        $icse = $this->create([
            'parent_id' => $group->id, 'name' => 'ICSE / ISC', 'code' => 'ICSE', 'type' => 'board',
            'sort_order' => 2,
            'meta' => ['full_name' => 'Indian Certificate of Secondary Education'],
        ]);
        $this->addClassesWithSubjectsAndChapters($icse->id, 'ICSE');

        $this->command->info("Academic Boards: done");
    }

    // ================================================================
    // 4. STATE BOARDS (All 28 states + UTs)
    // ================================================================

    private function seedStateBoards(): void
    {
        $group = $this->create([
            'name' => 'State Boards', 'code' => 'STATE_BOARDS', 'type' => 'exam_group',
            'description' => 'All Indian state education boards',
            'sort_order' => 4,
        ]);

        $states = [
            ['Andhra Pradesh Board','AP','BSEAP'],['Arunachal Pradesh Board','AR','DSEAP'],
            ['Assam Board','AS','SEBA/AHSEC'],['Bihar Board','BR','BSEB'],
            ['Chhattisgarh Board','CG','CGBSE'],['Goa Board','GA','GBSHSE'],
            ['Gujarat Board','GJ','GSEB'],['Haryana Board','HR','BSEH'],
            ['Himachal Pradesh Board','HP','HPBOSE'],['Jharkhand Board','JH','JAC'],
            ['Karnataka Board','KA','KSEEB'],['Kerala Board','KL','DHSE Kerala'],
            ['Madhya Pradesh Board','MP','MPBSE'],['Maharashtra Board','MH','MSBSHSE'],
            ['Manipur Board','MN','BOSEM'],['Meghalaya Board','ML','MBOSE'],
            ['Mizoram Board','MZ','MBSE'],['Nagaland Board','NL','NBSE'],
            ['Odisha Board','OD','BSE Odisha'],['Punjab Board','PB','PSEB'],
            ['Rajasthan Board','RJ','RBSE'],['Sikkim Board','SK','SHSEB'],
            ['Tamil Nadu Board','TN','TN SSLC/HSC'],['Telangana Board','TS','BSETS'],
            ['Tripura Board','TR','TBSE'],['Uttar Pradesh Board','UP','UPMSP'],
            ['Uttarakhand Board','UK','UBSE'],['West Bengal Board','WB','WBBSE/WBCHSE'],
            ['Delhi Board','DL','DSEB'],['Jammu & Kashmir Board','JK','JKBOSE'],
        ];

        foreach ($states as $i => [$name, $code, $board]) {
            $stateBoard = $this->create([
                'parent_id' => $group->id, 'name' => $name, 'code' => 'SB_' . $code,
                'type' => 'state_board', 'short_name' => $code, 'sort_order' => $i + 1,
                'meta' => ['state_code' => $code, 'board_name' => $board],
            ]);

            foreach ([10, 11, 12] as $grade) {
                $classSection = $this->create([
                    'parent_id' => $stateBoard->id, 'name' => "Class {$grade}",
                    'code' => "SB_{$code}_C{$grade}", 'type' => 'class',
                    'short_name' => "{$grade}th", 'sort_order' => $grade,
                    'meta' => ['grade_number' => $grade],
                ]);

                foreach ([['Physics','PHY',1],['Chemistry','CHEM',2],['Mathematics','MATH',3]] as [$subName, $subCode, $sort]) {
                    $this->create([
                        'parent_id' => $classSection->id, 'name' => $subName,
                        'code' => "SB_{$code}_C{$grade}_{$subCode}", 'type' => 'subject',
                        'sort_order' => $sort, 'meta' => ['subject_code' => $subCode],
                    ]);
                }
            }
        }

        $this->command->info("State Boards: done");
    }

    // ================================================================
    // HELPER: Add Physics, Chemistry, Maths/Biology with chapters
    // ================================================================

    private function addSubjectsWithChapters(int $parentId, string $prefix, array $phyChapters, array $chemChapters, array $mathOrBioChapters, bool $isBiology = false): void
    {
        $phy = $this->create(['parent_id' => $parentId, 'name' => 'Physics', 'code' => "{$prefix}_PHY", 'type' => 'subject', 'sort_order' => 1]);
        foreach ($phyChapters as $i => $ch) {
            $this->create(['parent_id' => $phy->id, 'name' => $ch, 'code' => "{$prefix}_PHY_C" . ($i + 1), 'type' => 'chapter', 'sort_order' => $i + 1, 'meta' => ['chapter_number' => $i + 1]]);
        }

        $chem = $this->create(['parent_id' => $parentId, 'name' => 'Chemistry', 'code' => "{$prefix}_CHEM", 'type' => 'subject', 'sort_order' => 2]);
        foreach ($chemChapters as $i => $ch) {
            $this->create(['parent_id' => $chem->id, 'name' => $ch, 'code' => "{$prefix}_CHEM_C" . ($i + 1), 'type' => 'chapter', 'sort_order' => $i + 1, 'meta' => ['chapter_number' => $i + 1]]);
        }

        $thirdName = $isBiology ? 'Biology' : 'Mathematics';
        $thirdCode = $isBiology ? 'BIO' : 'MATH';
        $third = $this->create(['parent_id' => $parentId, 'name' => $thirdName, 'code' => "{$prefix}_{$thirdCode}", 'type' => 'subject', 'sort_order' => 3]);
        foreach ($mathOrBioChapters as $i => $ch) {
            $this->create(['parent_id' => $third->id, 'name' => $ch, 'code' => "{$prefix}_{$thirdCode}_C" . ($i + 1), 'type' => 'chapter', 'sort_order' => $i + 1, 'meta' => ['chapter_number' => $i + 1]]);
        }
    }

    private function addGovtExamSubjects(int $parentId, string $prefix): void
    {
        foreach (['General Intelligence & Reasoning', 'General Awareness', 'Quantitative Aptitude', 'English Language'] as $i => $sub) {
            $this->create(['parent_id' => $parentId, 'name' => $sub, 'code' => "{$prefix}_S" . ($i + 1), 'type' => 'subject', 'sort_order' => $i + 1]);
        }
    }

    private function addBankingSubjects(int $parentId, string $prefix): void
    {
        foreach (['Reasoning Ability', 'Quantitative Aptitude', 'English Language', 'General/Financial Awareness', 'Computer Aptitude'] as $i => $sub) {
            $this->create(['parent_id' => $parentId, 'name' => $sub, 'code' => "{$prefix}_S" . ($i + 1), 'type' => 'subject', 'sort_order' => $i + 1]);
        }
    }

    private function addClassesWithSubjectsAndChapters(int $boardId, string $prefix): void
    {
        foreach ([10, 11, 12] as $grade) {
            $class = $this->create([
                'parent_id' => $boardId, 'name' => "Class {$grade}",
                'code' => "{$prefix}_C{$grade}", 'type' => 'class',
                'short_name' => "{$grade}th", 'sort_order' => $grade,
                'meta' => ['grade_number' => $grade, 'stream' => $grade >= 11 ? 'Science' : 'General'],
            ]);

            // Physics
            $phy = $this->create(['parent_id' => $class->id, 'name' => 'Physics', 'code' => "{$prefix}_C{$grade}_PHY", 'type' => 'subject', 'sort_order' => 1]);
            $phyCh = $grade === 10 ? $this->class10PhysicsChapters() : ($grade === 11 ? $this->class11PhysicsChapters() : $this->class12PhysicsChapters());
            foreach ($phyCh as $i => $ch) {
                $this->create(['parent_id' => $phy->id, 'name' => $ch, 'code' => "{$prefix}_C{$grade}_PHY_CH" . ($i + 1), 'type' => 'chapter', 'sort_order' => $i + 1, 'meta' => ['chapter_number' => $i + 1]]);
            }

            // Chemistry
            $chem = $this->create(['parent_id' => $class->id, 'name' => 'Chemistry', 'code' => "{$prefix}_C{$grade}_CHEM", 'type' => 'subject', 'sort_order' => 2]);
            $chemCh = $grade === 10 ? $this->class10ChemistryChapters() : ($grade === 11 ? $this->class11ChemistryChapters() : $this->class12ChemistryChapters());
            foreach ($chemCh as $i => $ch) {
                $this->create(['parent_id' => $chem->id, 'name' => $ch, 'code' => "{$prefix}_C{$grade}_CHEM_CH" . ($i + 1), 'type' => 'chapter', 'sort_order' => $i + 1, 'meta' => ['chapter_number' => $i + 1]]);
            }

            // Maths
            $math = $this->create(['parent_id' => $class->id, 'name' => 'Mathematics', 'code' => "{$prefix}_C{$grade}_MATH", 'type' => 'subject', 'sort_order' => 3]);
            $mathCh = $grade === 10 ? $this->class10MathsChapters() : ($grade === 11 ? $this->class11MathsChapters() : $this->class12MathsChapters());
            foreach ($mathCh as $i => $ch) {
                $this->create(['parent_id' => $math->id, 'name' => $ch, 'code' => "{$prefix}_C{$grade}_MATH_CH" . ($i + 1), 'type' => 'chapter', 'sort_order' => $i + 1, 'meta' => ['chapter_number' => $i + 1]]);
            }

            // Biology (only for class 11, 12)
            if ($grade >= 11) {
                $bio = $this->create(['parent_id' => $class->id, 'name' => 'Biology', 'code' => "{$prefix}_C{$grade}_BIO", 'type' => 'subject', 'sort_order' => 4]);
                $bioCh = $grade === 11 ? $this->class11BiologyChapters() : $this->class12BiologyChapters();
                foreach ($bioCh as $i => $ch) {
                    $this->create(['parent_id' => $bio->id, 'name' => $ch, 'code' => "{$prefix}_C{$grade}_BIO_CH" . ($i + 1), 'type' => 'chapter', 'sort_order' => $i + 1, 'meta' => ['chapter_number' => $i + 1]]);
                }
            }
        }
    }

    // ================================================================
    // CHAPTER LISTS (NCERT-based)
    // ================================================================

    private function jeePhysicsChapters(): array { return ['Mechanics','Thermodynamics','Waves & Oscillations','Optics','Electrostatics','Current Electricity','Magnetism','Electromagnetic Induction','Modern Physics','Semiconductors']; }
    private function jeeChemistryChapters(): array { return ['Atomic Structure','Chemical Bonding','Thermochemistry','Equilibrium','Organic Chemistry Basics','Hydrocarbons','Polymers','Electrochemistry','Coordination Compounds','Environmental Chemistry']; }
    private function jeeMathsChapters(): array { return ['Sets & Relations','Complex Numbers','Quadratic Equations','Matrices & Determinants','Sequences & Series','Trigonometry','Coordinate Geometry','Calculus (Limits & Derivatives)','Calculus (Integrals)','Probability & Statistics','Vectors & 3D Geometry']; }

    private function neetPhysicsChapters(): array { return ['Physical World & Measurement','Kinematics','Laws of Motion','Work, Energy & Power','Rotational Motion','Gravitation','Properties of Matter','Thermodynamics','Kinetic Theory','Oscillations & Waves','Electrostatics','Current Electricity','Magnetic Effects of Current','EMI & AC','EM Waves','Optics','Dual Nature of Radiation','Atoms & Nuclei','Semiconductors']; }
    private function neetChemistryChapters(): array { return ['Some Basic Concepts','Atomic Structure','Classification of Elements','Chemical Bonding','States of Matter','Thermodynamics','Equilibrium','Redox Reactions','Hydrogen','s-Block Elements','p-Block Elements','Organic Chemistry Basics','Hydrocarbons','Environmental Chemistry']; }
    private function neetBiologyChapters(): array { return ['The Living World','Biological Classification','Plant Kingdom','Animal Kingdom','Morphology of Plants','Anatomy of Plants','Structural Organisation in Animals','Cell Biology','Biomolecules','Cell Division','Transport in Plants','Mineral Nutrition','Photosynthesis','Respiration in Plants','Plant Growth','Digestion & Absorption','Breathing','Body Fluids & Circulation','Excretory Products','Locomotion & Movement','Neural Control','Chemical Coordination']; }

    private function class10PhysicsChapters(): array { return ['Light — Reflection & Refraction','Human Eye & Colourful World','Electricity','Magnetic Effects of Current','Sources of Energy']; }
    private function class10ChemistryChapters(): array { return ['Chemical Reactions & Equations','Acids, Bases & Salts','Metals & Non-metals','Carbon & Its Compounds','Periodic Classification of Elements']; }
    private function class10MathsChapters(): array { return ['Real Numbers','Polynomials','Pair of Linear Equations','Quadratic Equations','Arithmetic Progressions','Triangles','Coordinate Geometry','Trigonometry','Applications of Trigonometry','Circles','Constructions','Areas Related to Circles','Surface Areas & Volumes','Statistics','Probability']; }

    private function class11PhysicsChapters(): array { return ['Physical World','Units & Measurements','Motion in a Straight Line','Motion in a Plane','Laws of Motion','Work, Energy & Power','System of Particles & Rotational Motion','Gravitation','Mechanical Properties of Solids','Mechanical Properties of Fluids','Thermal Properties of Matter','Thermodynamics','Kinetic Theory','Oscillations','Waves']; }
    private function class11ChemistryChapters(): array { return ['Some Basic Concepts','Structure of Atom','Classification of Elements','Chemical Bonding','States of Matter','Thermodynamics','Equilibrium','Redox Reactions','Hydrogen','s-Block Elements','p-Block Elements','Organic Chemistry: Basic Principles','Hydrocarbons','Environmental Chemistry']; }
    private function class11MathsChapters(): array { return ['Sets','Relations & Functions','Trigonometric Functions','Principle of Mathematical Induction','Complex Numbers','Linear Inequalities','Permutations & Combinations','Binomial Theorem','Sequences & Series','Straight Lines','Conic Sections','3D Geometry Intro','Limits & Derivatives','Mathematical Reasoning','Statistics','Probability']; }
    private function class11BiologyChapters(): array { return ['The Living World','Biological Classification','Plant Kingdom','Animal Kingdom','Morphology of Flowering Plants','Anatomy of Flowering Plants','Structural Organisation in Animals','Cell: Unit of Life','Biomolecules','Cell Cycle & Division','Transport in Plants','Mineral Nutrition','Photosynthesis in Higher Plants','Respiration in Plants','Plant Growth & Development','Digestion & Absorption','Breathing & Exchange of Gases','Body Fluids & Circulation','Excretory Products','Locomotion & Movement','Neural Control & Coordination','Chemical Coordination & Integration']; }

    private function class12PhysicsChapters(): array { return ['Electric Charges & Fields','Electrostatic Potential & Capacitance','Current Electricity','Moving Charges & Magnetism','Magnetism & Matter','Electromagnetic Induction','Alternating Current','Electromagnetic Waves','Ray Optics','Wave Optics','Dual Nature of Radiation & Matter','Atoms','Nuclei','Semiconductor Electronics','Communication Systems']; }
    private function class12ChemistryChapters(): array { return ['Solid State','Solutions','Electrochemistry','Chemical Kinetics','Surface Chemistry','General Principles of Isolation','p-Block Elements','d and f Block Elements','Coordination Compounds','Haloalkanes & Haloarenes','Alcohols, Phenols & Ethers','Aldehydes, Ketones & Carboxylic Acids','Amines','Biomolecules','Polymers','Chemistry in Everyday Life']; }
    private function class12MathsChapters(): array { return ['Relations & Functions','Inverse Trigonometric Functions','Matrices','Determinants','Continuity & Differentiability','Application of Derivatives','Integrals','Application of Integrals','Differential Equations','Vector Algebra','3D Geometry','Linear Programming','Probability']; }
    private function class12BiologyChapters(): array { return ['Reproduction in Organisms','Sexual Reproduction in Flowering Plants','Human Reproduction','Reproductive Health','Principles of Inheritance','Molecular Basis of Inheritance','Evolution','Human Health & Disease','Strategies for Enhancement in Food Production','Microbes in Human Welfare','Biotechnology: Principles & Processes','Biotechnology & Its Applications','Organisms & Populations','Ecosystem','Biodiversity & Conservation','Environmental Issues']; }

    // ================================================================
    // HELPER: Create and count
    // ================================================================

    private function create(array $data): ExamSection
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']) . '-' . Str::random(4);
        }
        $this->created++;
        return ExamSection::create(array_merge(['is_active' => true], $data));
    }
}