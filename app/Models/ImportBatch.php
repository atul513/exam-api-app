<?php

// ============================================================
// ─── app/Models/ImportBatch.php ─────────────────────────────
// ============================================================

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class ImportBatch extends Model
{
    protected $fillable = [
        'file_name', 'file_path', 'file_size_bytes', 'status',
        'total_rows', 'processed_rows', 'success_count', 'error_count',
        'error_log', 'summary', 'imported_by', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'status'       => ImportStatus::class,
        'error_log'    => 'array',
        'summary'      => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function progressPercent(): float
    {
        if ($this->total_rows === 0) return 0;
        return round(($this->processed_rows / $this->total_rows) * 100, 1);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status'       => $this->error_count > 0
                ? ImportStatus::Partial
                : ImportStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}

