<?php


// ============================================================
// ─── app/Exports/ImportErrorExport.php ──────────────────────
// ============================================================

namespace App\Exports;

use App\Models\ImportBatch;
use Maatwebsite\Excel\Concerns\{FromArray, WithHeadings, WithTitle};

class ImportErrorExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(private ImportBatch $batch) {}

    public function headings(): array
    {
        return ['Row', 'External ID', 'Field', 'Error', 'Severity'];
    }

    public function array(): array
    {
        return collect($this->batch->error_log ?? [])->map(fn($e) => [
            $e['row'] ?? '',
            $e['external_id'] ?? '',
            $e['field'] ?? '',
            $e['error'] ?? '',
            $e['severity'] ?? 'error',
        ])->toArray();
    }

    public function title(): string
    {
        return 'Errors';
    }
}