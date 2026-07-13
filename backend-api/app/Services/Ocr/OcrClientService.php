<?php

namespace App\Services\Ocr;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

// Client HTTP vers le microservice ocr-service (§11.1 : microservice isole,
// pas d'acces direct au disque de l'autre service - reste stateless,
// backend-api reste seul responsable de la persistance). Couvre les deux
// endpoints : /pretraitement (E3, jamais d'OCR) et /extraction (E6, avec le
// modele actif).
class OcrClientService
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

    /**
     * @return array{champs: array}
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function extraire(UploadedFile $fichier, string $typeGabarit, ?string $modeleOcrVersion): array
    {
        $reponse = Http::baseUrl(config('siarn.ocr_service.url'))
            ->timeout(config('siarn.ocr_service.timeout_secondes'))
            ->attach('fichier', file_get_contents($fichier->getRealPath()), $fichier->getClientOriginalName())
            ->post('/extraction', [
                'type_gabarit' => $typeGabarit,
                'modele_ocr_version' => $modeleOcrVersion,
            ]);

        $reponse->throw();

        return $reponse->json();
    }
}
