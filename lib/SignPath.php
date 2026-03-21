<?php

class SignPath {
    private string $apiToken;
    private string $organizationId;
    private string $projectSlug;
    private string $signingPolicySlug;
    private string $artifactConfigSlug;
    private string $baseUrl = 'https://app.signpath.io/API/v1';

    public function __construct() {
        $config = require __DIR__ . '/../config.php';
        $sp = $config['signpath'] ?? [];
        $this->apiToken = $sp['api_token'] ?? '';
        $this->organizationId = $sp['organization_id'] ?? '';
        $this->projectSlug = $sp['project_slug'] ?? 'SentientBrewer';
        $this->signingPolicySlug = $sp['signing_policy_slug'] ?? 'SentientBrewer-Signing';
        $this->artifactConfigSlug = $sp['artifact_config_slug'] ?? 'initial';
    }

    /**
     * Submit an exe for signing. Returns the signing request ID.
     */
    public function submitForSigning(string $exePath): ?string {
        if (!file_exists($exePath)) {
            throw new RuntimeException("File not found: {$exePath}");
        }

        $url = "{$this->baseUrl}/{$this->organizationId}/SigningRequests";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiToken,
                'Content-Type: multipart/form-data',
            ],
            CURLOPT_POSTFIELDS => [
                'ProjectSlug' => $this->projectSlug,
                'SigningPolicySlug' => $this->signingPolicySlug,
                'ArtifactConfigurationSlug' => $this->artifactConfigSlug,
                'Artifact' => new CURLFile($exePath, 'application/octet-stream', basename($exePath)),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("SignPath API curl error: {$error}");
        }

        if ($httpCode === 200 || $httpCode === 201) {
            $data = json_decode($response, true);
            return $data['signingRequestId'] ?? $data['id'] ?? null;
        }

        // Check for redirect — SignPath returns 303 with location header
        if ($httpCode === 303) {
            // The signing request URL is in the Location header
            // Re-do with CURLOPT_HEADER to capture it
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiToken,
                ],
                CURLOPT_POSTFIELDS => [
                    'ProjectSlug' => $this->projectSlug,
                    'SigningPolicySlug' => $this->signingPolicySlug,
                    'ArtifactConfigurationSlug' => $this->artifactConfigSlug,
                    'Artifact' => new CURLFile($exePath, 'application/octet-stream', basename($exePath)),
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 60,
            ]);
            $fullResponse = curl_exec($ch);
            curl_close($ch);

            if (preg_match('/Location:\s*(.+)/i', $fullResponse, $m)) {
                $location = trim($m[1]);
                // Extract signing request ID from URL
                if (preg_match('/SigningRequests\/([a-f0-9-]+)/i', $location, $idMatch)) {
                    return $idMatch[1];
                }
            }
        }

        throw new RuntimeException("SignPath API error ({$httpCode}): {$response}");
    }

    /**
     * Check the status of a signing request.
     * Returns: ['status' => 'Completed|InProgress|Failed', 'downloadUrl' => '...']
     */
    public function getStatus(string $signingRequestId): array {
        $url = "{$this->baseUrl}/{$this->organizationId}/SigningRequests/{$signingRequestId}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiToken,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException("SignPath status error ({$httpCode}): {$response}");
        }

        $data = json_decode($response, true);
        return [
            'status' => $data['status'] ?? 'Unknown',
            'downloadUrl' => $data['signedArtifactLink'] ?? null,
        ];
    }

    /**
     * Download the signed artifact.
     */
    public function downloadSigned(string $signingRequestId, string $outputPath): bool {
        $url = "{$this->baseUrl}/{$this->organizationId}/SigningRequests/{$signingRequestId}/SignedArtifact";

        $ch = curl_init($url);
        $fp = fopen($outputPath, 'w');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiToken,
            ],
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        return $httpCode === 200;
    }

    /**
     * Submit, wait for completion, and download signed exe.
     * Returns path to signed file, or null on failure.
     */
    public function signExe(string $exePath, int $maxWaitSeconds = 300): ?string {
        $requestId = $this->submitForSigning($exePath);
        if (!$requestId) return null;

        $signedPath = preg_replace('/\.exe$/i', '_signed.exe', $exePath);
        $waited = 0;
        $interval = 5;

        while ($waited < $maxWaitSeconds) {
            sleep($interval);
            $waited += $interval;

            $status = $this->getStatus($requestId);

            if ($status['status'] === 'Completed') {
                if ($this->downloadSigned($requestId, $signedPath)) {
                    // Replace original with signed version
                    rename($signedPath, $exePath);
                    return $exePath;
                }
                return null;
            }

            if ($status['status'] === 'Failed' || $status['status'] === 'Denied') {
                return null;
            }
        }

        return null; // Timed out
    }
}
