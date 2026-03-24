<?php

// ─── app/Exports/TemplateSheet.php ──────────────────────────

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithTitle, WithStyles};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TemplateSheet implements FromArray, WithTitle, WithStyles
{
    public function __construct(
        private array $headers,
        private string $title = ''
    ) {}

    public function array(): array
    {
        return [$this->headers]; // just headers, no data
    }

    public function title(): string
    {
        return $this->title ?: 'Sheet';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
