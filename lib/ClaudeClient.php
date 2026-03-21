<?php

class ClaudeClient {
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private string $baseUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct(?string $apiKey = null, ?string $model = null, ?int $maxTokens = null) {
        $config = require __DIR__ . '/../config.php';
        $this->apiKey = $apiKey ?? $config['anthropic']['api_key'];
        $this->model = $model ?? $config['anthropic']['model'];
        $this->maxTokens = $maxTokens ?? $config['anthropic']['max_tokens'];
    }

    public function chat(string $systemPrompt, array $messages, ?int $maxTokens = null): array {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $maxTokens ?? $this->maxTokens,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("Claude API curl error: $error");
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $msg = $data['error']['message'] ?? 'Unknown API error';
            throw new RuntimeException("Claude API error ($httpCode): $msg");
        }

        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $text .= $block['text'];
            }
        }

        $tokensUsed = ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);

        return [
            'text' => $text,
            'tokens_used' => $tokensUsed,
            'raw' => $data,
        ];
    }
}
