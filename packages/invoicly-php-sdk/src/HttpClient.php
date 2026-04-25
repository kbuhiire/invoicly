<?php

namespace Invoicly;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Invoicly\Exceptions\InvoiclyException;

class HttpClient
{
    private Client $guzzle;

    /**
     * @param  array<string, mixed>  $options  Extra Guzzle options (e.g. ['timeout' => 30])
     */
    public function __construct(string $baseUrl, string $apiToken, array $options = [])
    {
        $this->guzzle = new Client(array_merge([
            'base_uri' => rtrim($baseUrl, '/').'/api/v1/',
            'headers' => [
                'Authorization' => 'Bearer '.$apiToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'http_errors' => false,
            'timeout' => 30,
        ], $options));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     * @throws InvoiclyException
     */
    public function get(string $path, array $query = []): array
    {
        $response = $this->guzzle->get($path, ['query' => $query]);
        return $this->decode($response);
    }

    /**
     * Retrieve raw (binary) response body — used for PDF downloads.
     *
     * @throws InvoiclyException
     */
    public function getRaw(string $path): string
    {
        $response = $this->guzzle->get($path);
        $status = $response->getStatusCode();
        if ($status >= 400) {
            throw new InvoiclyException(
                "HTTP {$status} received for GET {$path}",
                $status,
            );
        }

        return (string) $response->getBody();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     * @throws InvoiclyException
     */
    public function post(string $path, array $body = []): array
    {
        $response = $this->guzzle->post($path, ['json' => $body]);
        return $this->decode($response);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     * @throws InvoiclyException
     */
    public function put(string $path, array $body = []): array
    {
        $response = $this->guzzle->put($path, ['json' => $body]);
        return $this->decode($response);
    }

    /**
     * @throws InvoiclyException
     */
    public function delete(string $path): void
    {
        $response = $this->guzzle->delete($path);
        $this->decode($response);
    }

    /**
     * @return array<string, mixed>
     * @throws InvoiclyException
     */
    private function decode(\Psr\Http\Message\ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $json = json_decode($body, true);

        if ($status >= 400) {
            $message = $json['message'] ?? "HTTP {$status} error";
            $errors = $json['errors'] ?? [];
            throw new InvoiclyException($message, $status, $errors);
        }

        return is_array($json) ? $json : [];
    }
}
