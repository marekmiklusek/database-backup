<?php

declare(strict_types=1);

namespace MarekMiklusek\DatabaseBackup\Services;

use Exception;
use Illuminate\Support\Facades\Http;

final class GoogleService
{
    private ConfigService $service;

    public function __construct()
    {
        $this->service = new ConfigService;
    }

    public function uploadBackup(string $localBackupPath): void
    {
        $accessToken = $this->getAccessToken();
        $backupData = file_get_contents($localBackupPath);

        // A boundary is a unique string that serves as a separator between different parts of data
        // in a single HTTP request, typically when using the content type multipart/related or multipart/form-data.
        $boundary = '-------0123456789';
        $delimiter = "\r\n--{$boundary}\r\n";
        $closeDelimiter = "\r\n--{$boundary}--";

        $folderId = $this->service->googleDisk('folder_id');

        $metadata = json_encode([
            'name' => basename($localBackupPath),
            'parents' => [$folderId],
        ]);

        $body = $delimiter
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            ."{$metadata}\r\n"
            .$delimiter
            ."Content-Type: application/zip\r\n"
            ."Content-Transfer-Encoding: base64\r\n\r\n"
            .base64_encode($backupData)."\r\n"
            .$closeDelimiter;

        $headers = ['Authorization' => "Bearer {$accessToken}"];

        $response = Http::withHeaders($headers)
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');

        if ($response->failed()) {
            throw new Exception('Failed to upload backup to Google Drive: '.$response->body());
        }
    }

    public function listBackups(): array
    {
        $accessToken = $this->getAccessToken();
        $folderId = $this->service->googleDisk('folder_id');

        $response = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/drive/v3/files', [
                'q' => "'{$folderId}' in parents and trashed = false",
                'fields' => 'files(id, name, mimeType, createdTime, modifiedTime)',
                'orderBy' => 'modifiedTime desc',
                'pageSize' => 100,
            ]);

        if ($response->failed()) {
            throw new Exception('Failed to list backups from Google Drive: '.$response->body());
        }

        return $response->json('files');
    }

    public function deleteFile(string $fileId): void
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->delete("https://www.googleapis.com/drive/v3/files/{$fileId}");

        if ($response->failed()) {
            throw new Exception('Failed to delete file from Google Drive: '.$response->body());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private functions
    |--------------------------------------------------------------------------
    */

    private function getAccessToken(): string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->service->googleDisk('client_id'),
            'client_secret' => $this->service->googleDisk('client_secret'),
            'refresh_token' => $this->service->googleDisk('refresh_token'),
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            throw new Exception('Failed to fetch access token using refresh token: '.$response->body());
        }

        return $response->json('access_token');
    }
}
