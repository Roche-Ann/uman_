<?php
/**
 * RoadGeometryClient
 *
 * Read-only client for IPMS's road alignment feed — the road geometry
 * drawn during their Project Registration for the "Roads and Bridges"
 * category. Meant to power the Urban Planning tab that shows road data on
 * the map (this project already uses Leaflet elsewhere, e.g. gis/map.php —
 * polyline_coordinates below is already [lat, lng] pairs Leaflet's
 * L.polyline() can consume directly).
 *
 * GET {IPMS_BASE_URL}/integrations/urban-planning/road-geometry-feed.php
 * Header: X-API-Key: {URBAN_PLANNING_API_KEY}
 *
 * Not a consume-once queue like the inspection results feed — this always
 * returns the current live state of every Roads and Bridges project that
 * has a drawn alignment, so callers should just re-pull and replace their
 * view on each poll. No sync-marking, and no write path exists on this
 * endpoint at all — fetch-and-remap only.
 */

require_once __DIR__ . '/roads_integration.php';

class RoadGeometryClient
{
    /**
     * @return array{success:bool, roads:array, error:?string}
     */
    public function fetchRoads(): array
    {
        if (URBAN_PLANNING_API_KEY === '' || URBAN_PLANNING_API_KEY === null) {
            return [
                'success' => false,
                'roads'   => [],
                'error'   => 'URBAN_PLANNING_API_KEY is not configured (see ipms-integration/.env.example)',
            ];
        }

        $ch = curl_init(rtrim(IPMS_BASE_URL, '/') . '/integrations/urban-planning/road-geometry-feed.php');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => ['X-API-Key: ' . URBAN_PLANNING_API_KEY],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'roads' => [], 'error' => "cURL error: $curlError"];
        }

        $decoded = json_decode($responseBody ?: '', true);

        if ($httpCode !== 200 || empty($decoded['success'])) {
            $cleanBody = trim(strip_tags($responseBody ?: ''));
            $cleanBody = preg_replace('/\s+/', ' ', $cleanBody);
            return [
                'success' => false,
                'roads'   => [],
                'error'   => "IPMS responded with HTTP $httpCode: " . mb_substr($cleanBody, 0, 200),
            ];
        }

        return ['success' => true, 'roads' => $decoded['roads'] ?? [], 'error' => null];
    }
}
