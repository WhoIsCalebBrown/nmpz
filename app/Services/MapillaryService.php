<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MapillaryService
{
    public function hasImageAt(float $lat, float $lng): bool
    {
        $token = config('services.mapillary.access_token');

        if (! $token) {
            return true;
        }

        $delta = config('services.mapillary.bbox_delta', 0.0005);
        $bbox = implode(',', [
            $lng - $delta,
            $lat - $delta,
            $lng + $delta,
            $lat + $delta,
        ]);

        try {
            $response = Http::get('https://graph.mapillary.com/images', [
                'access_token' => $token,
                'fields' => 'id',
                'bbox' => $bbox,
                'limit' => 1,
            ]);

            if (! $response->ok()) {
                return true;
            }

            $data = $response->json('data', []);

            return count($data) > 0;
        } catch (\Throwable) {
            return true;
        }
    }
}
