<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

// Correspond a modeles_ocr (0006_corpus_ocr.sql) - §8.3. Ecrit par le
// pipeline d'entrainement (ocr-service/training, Python) via versionnement.py ;
// cote backend-api, lecture seule (consultation des versions, §7.8 dashboards).
class ModeleOcr extends Model
{
    use HasUuids;

    protected $table = 'modeles_ocr';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'cer' => 'float',
        'wer' => 'float',
        'date_entrainement' => 'datetime',
        'created_at' => 'datetime',
    ];
}
