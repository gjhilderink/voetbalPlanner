<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén document bij een elftal, of bij de hele club als team_id leeg is.
 */
class TeamDocument extends Model
{
    use HasUuids;

    /** De disk schrijft rechtstreeks in public/; zie config/filesystems.php. */
    public const DISK = 'team_documents';

    /** Wat er geüpload mag worden. Alleen documenten, geen uitvoerbare bestanden. */
    public const TOEGESTAAN = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    protected $fillable = [
        'club_id', 'team_id', 'uploaded_by',
        'title', 'description',
        'file_path', 'original_name', 'mime_type', 'size',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'size'       => 'integer',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * De deelbare link.
     *
     * Werkt zonder inloggen, want hij moet in een appbericht of een mail kunnen.
     * De bestandsnaam is willekeurig, dus de link is niet te raden — maar wie
     * hem heeft, kan het document openen. Zet er dus niets in wat niet rond mag.
     */
    public function url(): string
    {
        return $this->file_path ? asset('team_documents/' . $this->file_path) : '';
    }

    /** pdf, docx, xlsx — waar de app een icoon bij kiest. */
    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name ?: $this->file_path, PATHINFO_EXTENSION));
    }

    /** "1,4 MB" of "870 kB"; bytes zeggen een mens niets. */
    public function sizeLabel(): string
    {
        $bytes = (int) $this->size;

        if ($bytes <= 0) {
            return '';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024) . ' kB';
        }

        return str_replace('.', ',', (string) round($bytes / 1024 / 1024, 1)) . ' MB';
    }
}
