<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de reconocimiento facial.
 *
 * Comparación: selfie registrada vs fotografía capturada.
 * Por defecto usa AWS Rekognition. Se puede intercambiar con
 * Azure Face API o un servicio propio via face_match_driver config.
 */
class FaceMatchService
{
    public function compare(string $referenceUrl, UploadedFile $capturedPhoto): float
    {
        $driver = config('services.face_match.driver', 'aws_rekognition');

        return match ($driver) {
            'aws_rekognition' => $this->compareWithRekognition($referenceUrl, $capturedPhoto),
            'azure_face'      => $this->compareWithAzure($referenceUrl, $capturedPhoto),
            'mock'            => $this->mockCompare(), // Para testing
            default           => throw new \InvalidArgumentException("Driver desconocido: {$driver}"),
        };
    }

    /**
     * AWS Rekognition CompareFaces
     */
    private function compareWithRekognition(string $referenceUrl, UploadedFile $photo): float
    {
        $client = new \Aws\Rekognition\RekognitionClient([
            'region'  => config('services.aws.region'),
            'version' => 'latest',
            'credentials' => [
                'key'    => config('services.aws.key'),
                'secret' => config('services.aws.secret'),
            ],
        ]);

        try {
            $result = $client->compareFaces([
                'SourceImage' => [
                    'S3Object' => [
                        'Bucket' => config('services.aws.bucket'),
                        'Name'   => $this->urlToS3Key($referenceUrl),
                    ],
                ],
                'TargetImage' => [
                    'Bytes' => file_get_contents($photo->getRealPath()),
                ],
                'SimilarityThreshold' => 70.0,
            ]);

            $faces = $result->get('FaceMatches') ?? [];

            if (empty($faces)) {
                return 0.0;
            }

            return (float) $faces[0]['Similarity'];

        } catch (\Aws\Rekognition\Exception\RekognitionException $e) {
            Log::error('Rekognition error: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Azure Cognitive Services - Face API
     */
    private function compareWithAzure(string $referenceUrl, UploadedFile $photo): float
    {
        $endpoint    = config('services.azure_face.endpoint');
        $apiKey      = config('services.azure_face.key');

        // 1. Detect face in reference photo (from URL)
        $detectRef = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $apiKey,
            'Content-Type'              => 'application/json',
        ])->post("{$endpoint}/face/v1.0/detect", [
            'url'              => $referenceUrl,
            'returnFaceId'     => true,
            'detectionModel'   => 'detection_03',
        ]);

        // 2. Detect face in captured photo (binary)
        $detectCap = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $apiKey,
            'Content-Type'              => 'application/octet-stream',
        ])->withBody(file_get_contents($photo->getRealPath()), 'application/octet-stream')
          ->post("{$endpoint}/face/v1.0/detect?returnFaceId=true&detectionModel=detection_03");

        $refFaces = $detectRef->json();
        $capFaces = $detectCap->json();

        if (empty($refFaces) || empty($capFaces)) {
            return 0.0;
        }

        // 3. Compare faces
        $verify = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $apiKey,
            'Content-Type'              => 'application/json',
        ])->post("{$endpoint}/face/v1.0/verify", [
            'faceId1' => $refFaces[0]['faceId'],
            'faceId2' => $capFaces[0]['faceId'],
        ])->json();

        if ($verify['isIdentical'] ?? false) {
            return (float) (($verify['confidence'] ?? 0) * 100);
        }

        return (float) (($verify['confidence'] ?? 0) * 100);
    }

    private function mockCompare(): float
    {
        // Para tests: retorna 90 (siempre aprobado)
        return 90.0;
    }

    private function urlToS3Key(string $url): string
    {
        $parsed = parse_url($url);
        return ltrim($parsed['path'], '/');
    }
}
