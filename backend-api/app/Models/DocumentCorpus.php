<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Correspond a documents_corpus (0006_corpus_ocr.sql) - §8, E4. Isole des
// proces_verbaux de production : jamais de FK croisee (precision de
// conception §10), l'alimentation du corpus depuis un PV se fait via un
// export applicatif explicite (boucle de retroaction §8.4, E7 - pas encore construit).
class DocumentCorpus extends Model
{
    use HasUuids;

    protected $table = 'documents_corpus';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'nom_fichier',
        'chemin_fichier',
        'type_gabarit',
        'jeu',
        'anonymise',
        'date_annotation',
        'importe_par_id',
    ];

    protected $casts = [
        'anonymise' => 'boolean',
        'date_annotation' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function annotations(): HasMany
    {
        return $this->hasMany(Annotation::class, 'document_id');
    }

    public function importePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'importe_par_id');
    }
}
