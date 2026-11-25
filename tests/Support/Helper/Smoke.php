<?php

declare(strict_types=1);

namespace app\tests\Support\Helper;

use Codeception\Module;
use PDO;

/**
 * Low-level checks for the smoke suite (a Codeception helper module).
 *
 * PhpBrowser covers ordinary pages; these methods check what a page request cannot:
 * the WebSocket handshake through nginx, the Centrifugo HTTP API and the DB schema.
 * Addresses are Docker service names of the test stand.
 */
final class Smoke extends Module
{
    private const NGINX_HOST = 'nginx-test';
    private const CENTRIFUGO_API = 'http://centrifugo-test:8000/api';
    private const TIMEOUT_SECONDS = 5;

    /**
     * Opens a WebSocket handshake through nginx and returns the HTTP status line.
     *
     * A browser does the same first step: an HTTP request with "Upgrade: websocket".
     * "101 Switching Protocols" means nginx passed it to Centrifugo and Centrifugo accepted it.
     *
     * @param string $origin Origin header; Centrifugo rejects origins not in its allow list
     * @return string Status line, e.g. "HTTP/1.1 101 Switching Protocols"
     */
    public function grabWebSocketHandshakeStatus(string $origin): string
    {
        $socket = @stream_socket_client(
            'tcp://' . self::NGINX_HOST . ':80',
            $errorCode,
            $errorMessage,
            self::TIMEOUT_SECONDS,
        );
        $this->assertNotFalse($socket, "Cannot connect to nginx: {$errorMessage}");
        stream_set_timeout($socket, self::TIMEOUT_SECONDS);

        fwrite($socket, implode("\r\n", [
            'GET /connection/websocket HTTP/1.1',
            'Host: ' . self::NGINX_HOST,
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Version: 13',
            'Sec-WebSocket-Key: ' . base64_encode(random_bytes(16)),
            'Origin: ' . $origin,
            '',
            '',
        ]));
        $statusLine = trim((string) fgets($socket));
        fclose($socket);

        return $statusLine;
    }

    /**
     * Calls a method of the Centrifugo server API with the API key, like the app does.
     *
     * @param string $method API method, e.g. "info"
     * @return array{status: int, body: array<string, mixed>} HTTP status and decoded JSON
     */
    public function callCentrifugoApi(string $method): array
    {
        $curl = curl_init(self::CENTRIFUGO_API . '/' . $method);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . getenv('CENTRIFUGO_API_KEY'),
            ],
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        $this->assertIsString($response, 'Centrifugo API is unreachable: ' . curl_error($curl));

        return ['status' => $status, 'body' => (array) json_decode($response, true)];
    }

    /**
     * Sends a GET request and returns only the HTTP status (redirects are not followed).
     *
     * Used for addresses outside the test stand, e.g. the dev app at http://nginx.
     *
     * @param string $url Full URL
     * @param int $timeoutSeconds How long to wait for the answer
     * @return int HTTP status code
     */
    public function grabHttpStatus(string $url, int $timeoutSeconds = self::TIMEOUT_SECONDS): int
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
        ]);
        $response = curl_exec($curl);
        $this->assertIsString($response, "No answer from {$url}: " . curl_error($curl));

        return (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    }

    /**
     * Connects to a database with the app's credentials from .env.
     *
     * @param string $database Database name, e.g. "app" or "app_test"
     * @return PDO
     */
    public function connectToDatabase(string $database): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: 'mysql', $database),
            (string) getenv('DB_USER'),
            (string) getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
