<?php

namespace App\Utilities\Repositories\Clients;

use Http;
use App\Enums\RepositoryType;
use Illuminate\Support\Facades\Log;
use App\Contracts\RepositoryClientInterface;
use Exception;

class DataverseClient implements RepositoryClientInterface
{
    protected $baseUrl;
    protected $timeout;
    protected $headers = [];
    protected $retries;
    protected $resourceMetadata;

    public function __construct($baseUrl, $secret, $timeout = 30)
    {
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
        $this->secret = $secret;
        $this->setHeaders();
    }

    private function setHeaders()
    {
        $this->headers = ['X-Dataverse-key' => $this->secret];
    }

    public function setSecret($secret)
    {
        $this->secret = $secret;
        $this->setHeaders();
    }

    public function setResourceMetadata($resourceMetadata)
    {
        $this->resourceMetadata = $resourceMetadata;
    }

    public function createResource($data, $collection = 'root')
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers)
            ->asJson()
            ->acceptJson()
            ->post($this->baseUrl . "/api/dataverses/$collection/datasets", $data)
            ->throw();

        return $response->json();
    }

    public function deleteResource($resourceID, $collection): bool
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers)
            ->asJson()
            ->acceptJson()
            ->delete($this->baseUrl . "/api/datasets/$resourceID/versions/:draft");

        return $response->ok();
    }

    public function updateResource($persistentID, $data)
    {
        $response = Http::timeout($this->timeout)
            ->asJson()
            ->withHeaders($this->headers)
            ->put($this->baseUrl . "/api/datasets/:persistentId/versions/:draft?persistentId=$persistentID", $data)
            ->throw();

        return $response->json();
    }

    public function getType(): string
    {
        return RepositoryType::Dataverse;
    }

    public function verifyConnection(): bool
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers)
            ->asJson()
            ->acceptJson()
            ->get($this->baseUrl . "/api/users/token");

        return $response->ok();
    }

    public function uploadNewFile(
        $persistentID,
        $filename,
        $mimeType,
        $contents,
        $size,
        $checksum
    ): bool {
        // Get the upload URL.
        Log::info('Creating upload URL for ' . $filename, ['url' => "$this->baseUrl/api/datasets/:persistentId/uploadurls?persistentId=$persistentID&size=$size"]);
        $uploadUrl = Http::timeout($this->timeout)
            ->withHeaders($this->headers)
            ->get(
                "$this->baseUrl/api/datasets/:persistentId/uploadurls?persistentId=$persistentID&size=$size"
            )
            ->throw();

        $presignedUrl = $uploadUrl->json('data.url');
        $storageIdentifier = $uploadUrl->json('data.storageIdentifier');

        // Failed to create new files for resource.HTTP request returned status code 415: {"status":"ERROR","code":415,"message":"HTTP 415 Unsupported Media Type"}

        Log::info('Data response from upload:', ['response' => $uploadUrl->json()]);
        Log::info('Uploading the file to AWS S3.', [
            'filename' => $filename,
            'mimeType' => $mimeType,
            'size' => $size,
            'checksum' => $checksum,
        ]);

        // Upload the file to S3.
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $presignedUrl);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $contents);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['x-amz-tagging:dv-state=temp']);

        curl_exec($curl);

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpcode != 200) {
            throw new Exception("Failed to upload file $filename to s3.");
        }

        Log::info("Uploaded file $filename to resource $persistentID with status code $httpcode.");

        Log::info("Encoded form data to url: $this->baseUrl/api/datasets/:persistentId/add?persistentId=$persistentID" . json_encode([
            'restrict' => false,
            'storageIdentifier' => $storageIdentifier,
            'fileName' => $filename,
            'mimeType' => $mimeType,
            'checksum' => [
                '@type' => 'SHA-1',
                '@value' => $checksum,
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Attach the file to the resource using the persistent ID.
        Http::timeout($this->timeout)
            ->withHeaders($this->headers)
            ->asMultipart()
            ->post($this->baseUrl . "/api/datasets/:persistentId/add?persistentId=$persistentID", [
                'jsonData' => json_encode([
                    'restrict' => false,
                    'storageIdentifier' => $storageIdentifier,
                    'fileName' => $filename,
                    'mimeType' => $mimeType,
                    'checksum' => [
                        '@type' => 'SHA-1',
                        '@value' => $checksum,
                    ]
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ])->throw();

        Log::info("Attached file $filename to resource $persistentID.");

        return true;
    }

    public function getDatasetByPersistentID($persistentID): mixed
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers)
            ->asJson()
            ->acceptJson()
            ->get($this->baseUrl . "/api/datasets/:persistentId/?persistentId=$persistentID")
            ->throw();

        return $response->json();
    }

    public function deleteDatasetFiles($persistentID): bool
    {
        $files = $this->getDatasetByPersistentID($persistentID)["data"]["latestVersion"]["files"];

        foreach ($files as $file) {
            $this->deleteDatasetFileByID($file["dataFile"]["id"]);
        }

        return true;
    }

    public function deleteDatasetFileByID($fileID): bool
    {
        Http::timeout($this->timeout)
            ->withBasicAuth($this->secret, '')
            ->withHeaders($this->headers)
            ->delete($this->baseUrl . "/dvn/api/data-deposit/v1.1/swordv2/edit-media/file/$fileID")
            ->throw();

        return true;
    }
}
