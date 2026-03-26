<?php

// ============================================================
// FILE: app/Exports/DemoLongAnswersSheet.php
// ============================================================

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithTitle, WithStyles, WithColumnWidths};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DemoLongAnswersSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    public function title(): string { return 'long_answers'; }

    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 80, 'C' => 50, 'D' => 12, 'E' => 12];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'ffffff']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a56db']],
        ]);
        $sheet->getStyle('B:B')->getAlignment()->setWrapText(true);
        return [];
    }

    public function array(): array
    {
        return [
            ['external_id', 'model_answer', 'keywords', 'min_words', 'max_words'],

            // Q015: Newton's Laws
            [
                'Q015',
                "Newton's three laws of motion form the foundation of classical mechanics:\n\n"
                . "1. First Law (Law of Inertia): An object at rest stays at rest, and an object in motion stays in motion at constant velocity, unless acted upon by an unbalanced force.\n"
                . "Example: A book on a table remains stationary until pushed. A hockey puck on ice keeps sliding until friction or a wall stops it.\n\n"
                . "2. Second Law (F = ma): The acceleration of an object is directly proportional to the net force acting on it and inversely proportional to its mass.\n"
                . "Example: Pushing an empty shopping cart accelerates it more than a full one with the same force.\n\n"
                . "3. Third Law (Action-Reaction): For every action, there is an equal and opposite reaction.\n"
                . "Example: When you push against a wall, the wall pushes back with equal force. A rocket expels gas downward, propelling itself upward.",
                'inertia,F=ma,acceleration,action,reaction,force,mass,velocity,unbalanced force',
                150,
                500,
            ],

            // Q016: Atomic Structure
            [
                'Q016',
                "An atom is the smallest unit of matter that retains the properties of an element. It consists of three subatomic particles:\n\n"
                . "1. Protons: Positively charged particles located in the nucleus. The number of protons defines the atomic number and determines the element.\n\n"
                . "2. Neutrons: Electrically neutral particles also in the nucleus. They contribute to the mass of the atom and help stabilize the nucleus. Isotopes differ in neutron count.\n\n"
                . "3. Electrons: Negatively charged particles orbiting the nucleus in electron shells (energy levels). They are involved in chemical bonding and reactions.\n\n"
                . "The nucleus (protons + neutrons) contains most of the atom's mass. Electrons occupy specific energy levels: the first shell holds 2, the second holds 8, and so on (2n² rule).",
                'proton,neutron,electron,nucleus,atomic number,electron shell,energy level,isotope,charge,mass',
                100,
                400,
            ],
        ];
    }
}