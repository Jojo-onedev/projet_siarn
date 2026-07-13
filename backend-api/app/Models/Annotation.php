<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Correspond a annotations (0006_corpus_ocr.sql) - §8.1 etape 3 : double
// annotation avec recoupement (ordre_annotation 1/2 pour le meme champ).
class Annotation extends Model
{
    use HasUuids;

    protected $table = 'annotations';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'champ',
        'valeur_verite_terrain',
        'coordonnees_zone',
        'annotateur_id',
        'ordre_annotation',
        'statut_verification',
    ];

    protected $casts = [
        'coordonnees_zone' => 'array',
        'created_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(DocumentCorpus::class, 'document_id');
    }

    public function annotateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'annotateur_id');
    }
}
