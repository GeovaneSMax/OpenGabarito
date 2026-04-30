<?php
require_once __DIR__ . '/config.php';

/**
 * Handler para requisições à API do Gemini (Google AI Studio)
 */
function callGeminiAI($prompt, $system_message = "Você é um analista especialista em editais e gabaritos de concursos.") {
    $apiKey = GEMINI_API_KEY;
    if (empty($apiKey)) {
        return "Erro Gemini: Chave da API Gemini não configurada no arquivo .env";
    }
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

    $data = [
        "system_instruction" => [
            "parts" => [
                ["text" => $system_message]
            ]
        ],
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.1,
            "maxOutputTokens" => 8192,
            "responseMimeType" => "application/json"
        ]
    ];

    $json_data = json_encode($data);
    if ($json_data === false) {
        // Tenta limpar caracteres não-UTF8 se o json_encode falhar
        $data['contents'][0]['parts'][0]['text'] = mb_convert_encoding($data['contents'][0]['parts'][0]['text'], 'UTF-8', 'UTF-8');
        $json_data = json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
    } else {
        return "Erro Gemini: HTTP $httpCode - " . $response;
    }
}
