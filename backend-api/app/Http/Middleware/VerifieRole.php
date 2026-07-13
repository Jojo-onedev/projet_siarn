<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// RBAC (§5, §7.1) : verification des droits cote serveur, systematique.
// Usage : ->middleware('role:admin,directeur')
class VerifieRole
{
    public function handle(Request $request, Closure $next, string ...$rolesAutorises): Response
    {
        $utilisateur = $request->user();

        if (! $utilisateur || ! in_array($utilisateur->role->value, $rolesAutorises, true)) {
            return response()->json(['message' => 'Acces refuse pour votre role.'], 403);
        }

        return $next($request);
    }
}
