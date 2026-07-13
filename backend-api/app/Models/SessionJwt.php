<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

// Correspond a sessions_jwt (0002_utilisateurs_auth.sql) : permet la
// revocation immediate d'un JWT (§13.1), impossible avec un JWT seul.
class SessionJwt extends Model
{
    use HasUuids;

    protected $table = 'sessions_jwt';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'utilisateur_id',
        'jti',
        'expire_a',
        'revoque',
        'ip_creation',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'expire_a' => 'datetime',
        'revoque' => 'boolean',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }
}
