<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\AuthentificationException;
use App\Http\Controllers\Controller;
use App\Services\Auth\AuthentificationService;
use Illuminate\Http\Request;

class MfaController extends Controller
{
    public function __construct(private readonly AuthentificationService $authentificationService) {}

    public function activer(Request $request)
    {
        $resultat = $this->authentificationService->activerMfa($request->user());

        return response()->json($resultat);
    }

    public function confirmer(Request $request)
    {
        $donnees = $request->validate(['code' => ['required', 'string']]);

        try {
            $this->authentificationService->confirmerMfa($request->user(), $donnees['code']);
        } catch (AuthentificationException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statutHttp);
        }

        return response()->json(['message' => 'MFA active avec succes.']);
    }
}
