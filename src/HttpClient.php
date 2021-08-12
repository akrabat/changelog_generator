<?php

declare(strict_types=1);

namespace App;

/**
 * Simple HTTP client specifically for GitHub that uses curl
 */
class HttpClient
{
    protected $ch;

    public function __construct(string $token)
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36',
            'Authorization: token ' . $token,
        ]);
    }

    public function send(string $url, array $curlOptions = []): Response
    {
        $defaults = [
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
        ];
        curl_setopt_array($this->ch, $defaults);

        curl_setopt_array($this->ch, $curlOptions);

        $output = curl_exec($this->ch);
        if ($output === false) {
            throw new RuntimeException(curl_error($this->ch));
        }

        $statusCode = curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);

        $output = preg_split('/(\r?\n){2}/', $output, 2);
        $headersList = $output[0];
        $body = $output[1] ?? '';

        // parse headers
        $headersList = preg_split('/\r?\n/', $headersList);
        $headersList = array_map(static function ($h) {
            return preg_split('/:\s+/', $h, 2);
        }, $headersList);

        $headers = [];
        foreach ($headersList as $h) {
            $headers[strtolower($h[0])] = $h[1] ?? $h[0];
        }

        return new Response($statusCode, $headers, $body);
    }
}
