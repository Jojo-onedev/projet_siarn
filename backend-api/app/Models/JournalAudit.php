<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Correspond a journal_audit (0007_audit.sql) - §7.9, §13.5. Append-only en
// base (trigger interdire_modification_audit) : ce modele n'est jamais
// utilise pour ecrire (JournalAuditService::enregistrer utilise DB::table()
// directement), uniquement pour la consultation (AuditController, §5 :
// Admin + Directeur uniquement).
class JournalAudit extends Model
{
    use HasUuids;

    protected $table = 'journal_audit';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'details_json' => 'array',
        'date_heure' => 'datetime',
    ];

    public function acteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'acteur_id');
    }
}
