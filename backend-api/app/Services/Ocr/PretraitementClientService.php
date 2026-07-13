<?php

namespace App\Services\Ocr;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

// Client HTTP vers le microservice ocr-service (§11.1 : microservice isole,
// pas d'acces direct au disque de l'autre service - le pretraitement est
// stateless, backend-api reste seul responsable de la persistance).
class PretraitementClientService
{
    /**
     * @return array{zones: array, image_pretraitee_base64: ?string}
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function pretraiter(UploadedFile $fichier, string $typeGabarit): array
    {
        $reponse = Http::baseUrl(config('siarn.ocr_service.url'))
            ->timeout(config('siarn.ocr_service.timeout_secondes'))
            ->attach('fichier', file_get_contents($fichier->getRealPath()), $fichier->getClientOriginalName())
            ->post('/pretraitement', ['type_gabarit' => $typeGabarit]);

        $reponse->throw();

        return $reponse->json();
    }
}
