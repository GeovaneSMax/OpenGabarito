<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/groq_api.php';
require_once __DIR__ . '/../../includes/gemini_api.php';

header('Content-Type: application/json');
requireLogin();

$input = json_decode(file_get_contents('php://input'), true);
$text = $input['text'] ?? '';

if (empty($text)) {
    echo json_encode(['success' => false, 'error' => 'Texto vazio.']);
    exit;
}

if (strlen($text) > 120000) {
    $text = substr($text, 0, 120000);
}

try {
    $jsonStructure = '{
      "versions": [
        { "version": "1", "answers": { "1": "A", "2": "B" } },
        { "version": "2", "answers": { "1": "C", "2": "D" } }
      ]
    }';

    $shortText = (strlen($text) > 20000) ? (substr($text, 0, 10000) . "\n[...]\n" . substr($text, -10000)) : $text;
    $promptGroq = "Extraia o JSON das versões de gabarito seguindo esta estrutura: $jsonStructure\n\nTEXTO: $shortText\n\nResponda APENAS o JSON.";
    $promptGemini = "Extraia o JSON das versões de gabarito seguindo esta estrutura: $jsonStructure\n\nTEXTO: $text\n\nResponda APENAS o JSON.";
    
    // 1. Tenta Groq
    $jsonResponse = callGroqAI($promptGroq, "Você é um motor de extração JSON. Responda apenas o JSON puro.");

    // 2. Tenta Gemini se falhar
    if (!$jsonResponse || strpos($jsonResponse, 'Erro') !== false || strlen($jsonResponse) < 50) {
        $jsonResponse = callGeminiAI($promptGemini);
    }
    
    // Limpar markdown e extrair apenas o objeto JSON
    if (preg_match('/({.*})/s', $jsonResponse, $matches)) {
        $jsonResponse = $matches[1];
    }
    $data = json_decode(trim($jsonResponse), true);

    if ($data && isset($data['versions'])) {
        echo json_encode(['success' => true, 'data' => $data['versions']]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'A IA não conseguiu estruturar as versões do gabarito.',
            'details' => substr(trim($jsonResponse), 0, 500)
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
