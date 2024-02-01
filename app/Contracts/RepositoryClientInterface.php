<?php

namespace App\Contracts;

interface RepositoryClientInterface
{
    public function createResource($data, $collection);
    public function updateResource($persistentID, $data);
    public function getDatasetByPersistentID($persistentID): mixed;
    public function verifyConnection(): bool;
    public function uploadNewFile($resourceID, $filename, $mimeType, $contents, $size, $sha256checksum): bool;
    public function deleteResource($resourceID, $collection): bool;
    public function getType(): string;
}
