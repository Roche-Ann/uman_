<?php
/**
 * RoadsIntegrationService
 *
 * Handles the OUTBOUND side of the integration: sending a road inspection
 * request to IPMS when staff clicks "Request Road Inspection".
 *
 * This replaces the old dummy simulation that used to write fake
 * "AUTOMATED SIMULATION" text straight into impact_assessments.
 * Now it only marks the request as 'pending' — the real traffic_flag/
 * traffic_notes only get filled in later, once the poller in
 * ipms_inspection_result.php picks up the result from IPMS.
 */

require_once __DIR__ . '/roads_integration.php';
require_once __DIR__ . '/../core/Database.php';

class RoadsIntegrationService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * @param int   $applicationId
     * @param array $applicationData Accepted keys (all optional except where noted):
     *                                 road_id       (defaults to $applicationId)
     *                                 road_name     required on IPMS's side — falls
     *                                               back to 'address' if not given
     *                                 barangay      required on IPMS's side
     *                                 district      required on IPMS's side
     *                                 road_type     falls back to legacy 'category'
     *                                 road_length   km, falls back to legacy 'length_km'
     *                                 priority      low|medium|high|urgent (default medium)
     *                                 requested_by
     *                                 road_latitude  falls back to legacy 'lat'
     *                                 road_longitude falls back to legacy 'lng'
     * @param int   $requestedBy       user_id of the staff member triggering this
     * @return array{request_id:int, sent:bool, external_ref_id:?string, error:?string}
     */
    public function requestInspection(int $applicationId, array $applicationData, int $requestedBy): array
    {
        // road_id is our own record id — IPMS just stores it and echoes it
        // back later (as external_reference) on the results feed so we can
        // correlate. We reuse the same value for both fields since we don't
        // track a separate "road id" of our own.
        $roadId = (string) ($applicationData['road_id'] ?? $applicationId);

        $roadType   = $applicationData['road_type']   ?? $applicationData['category']  ?? null;
        $roadLength = $applicationData['road_length'] ?? $applicationData['length_km'] ?? null;
        $latitude   = $applicationData['road_latitude']  ?? $applicationData['lat'] ?? null;
        $longitude  = $applicationData['road_longitude'] ?? $applicationData['lng'] ?? null;

        $payload = [
            'road_id'             => mb_substr($roadId, 0, 40),
            'road_name'           => mb_substr((string) ($applicationData['road_name'] ?? $applicationData['address'] ?? ''), 0, 200),
            'barangay'            => mb_substr((string) ($applicationData['barangay'] ?? ''), 0, 100),
            'district'            => mb_substr((string) ($applicationData['district'] ?? ''), 0, 20),
            'road_type'           => $roadType !== null ? mb_substr((string) $roadType, 0, 80) : null,
            'road_length'         => $roadLength !== null && $roadLength !== '' ? (float) $roadLength : null,
            'priority'            => $this->normalizePriority($applicationData['priority'] ?? 'medium'),
            'requested_by'        => mb_substr((string) ($applicationData['requested_by'] ?? 'Urban Planning Office'), 0, 150),
            'request_date'        => date('Y-m-d'),
            'road_latitude'       => $latitude !== null && $latitude !== '' ? (float) $latitude : null,
            'road_longitude'      => $longitude !== null && $longitude !== '' ? (float) $longitude : null,
            'external_reference'  => mb_substr($roadId, 0, 64),
        ];

        // 1. Save the request as 'pending' FIRST, so we never lose track of
        //    it even if IPMS's API is down or unreachable.
        $stmt = $this->db->prepare(
            "INSERT INTO road_inspection_requests
                (application_id, status, request_payload, requested_by, requested_at)
             VALUES (?, 'pending', ?, ?, NOW())"
        );
        $stmt->execute([$applicationId, json_encode($payload), $requestedBy]);
        $requestId = (int) $this->db->lastInsertId();

        // 2. Mirror a 'pending' state into impact_assessments so the
        //    Technical Assessment tab shows "awaiting result" instead of
        //    blank or fake data.
        $this->db->prepare(
            "INSERT INTO impact_assessments (application_id, traffic_flag, traffic_notes, checked_at)
             VALUES (?, 'pending', 'Inspection request sent to IPMS (Infrastructure Project Management System). Awaiting result.', NOW())
             ON DUPLICATE KEY UPDATE
                traffic_flag  = 'pending',
                traffic_notes = 'Inspection request sent to IPMS (Infrastructure Project Management System). Awaiting result.',
                checked_at    = NOW()"
        )->execute([$applicationId]);

        // 3. Call out to IPMS.
        [$sent, $externalRefId, $error] = $this->sendToIpms($payload);

        $this->db->prepare(
            "UPDATE road_inspection_requests
                SET status = ?, external_ref_id = ?, response_payload = ?
              WHERE id = ?"
        )->execute([
            $sent ? 'sent' : 'failed',
            $externalRefId,
            $error ? json_encode(['error' => $error]) : null,
            $requestId,
        ]);

        return [
            'request_id'      => $requestId,
            'sent'            => $sent,
            'external_ref_id' => $externalRefId,
            'error'           => $error,
        ];
    }

    private function normalizePriority($priority): string
    {
        $priority = strtolower(trim((string) $priority));
        return in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : 'medium';
    }

    /**
     * POST /integrations/urban-planning/inspection-requests.php on IPMS.
     *
     * @return array{0: bool, 1: ?string, 2: ?string}  [sent, externalRefId, error]
     */
    private function sendToIpms(array $payload): array
    {
        if (URBAN_PLANNING_API_KEY === '' || URBAN_PLANNING_API_KEY === null) {
            return [false, null, 'URBAN_PLANNING_API_KEY is not configured (see ipms-integration/.env.example)'];
        }

        $ch = curl_init(rtrim(IPMS_BASE_URL, '/') . '/integrations/urban-planning/inspection-requests.php');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-API-Key: ' . URBAN_PLANNING_API_KEY,
            ],
            CURLOPT_TIMEOUT        => 10,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [false, null, "cURL error: $curlError"];
        }

        $decoded = json_decode($responseBody ?: '', true);

        if ($httpCode === 200 && !empty($decoded['success'])) {
            $externalRefId = $decoded['inspection_request_id'] ?? null;
            return [true, $externalRefId !== null ? (string) $externalRefId : null, null];
        }

        // IPMS's own error shape isn't consistent: 401/405 send {success:false,
        // message:...}, but 422 validation failures go through a shared
        // responder that sends {error:..., errors:{...}} with no "message" or
        // "success" key at all — check both.
        $message = $decoded['message'] ?? $decoded['error'] ?? null;
        if ($httpCode === 422 && !empty($decoded['errors'])) {
            $message = 'Validation failed: ' . json_encode($decoded['errors']);
        } elseif ($httpCode === 401) {
            $message = $message ?? 'Invalid or missing X-API-Key';
        } elseif ($httpCode === 405) {
            $message = $message ?? 'Wrong HTTP method';
        }

        if (!$message) {
            $cleanBody = trim(strip_tags($responseBody ?: ''));
            $cleanBody = preg_replace('/\s+/', ' ', $cleanBody);
            $message   = mb_substr($cleanBody, 0, 200);
        }

        return [false, null, "IPMS responded with HTTP $httpCode: $message"];
    }
}
