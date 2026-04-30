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
    echo json_encode(['success' => false, 'error' => 'Texto do edital não recebido.']);
    exit;
}

// Para o Gemini, aumentamos o limite. Para o Groq, ele cortará internamente se necessário.
if (strlen($text) > 150000) {
    $text = substr($text, 0, 75000) . "\n\n[...] \n\n" . substr($text, -75000);
}

try {
    // Melhoramos a captura de texto: início, meio e fim do documento
    $length = strlen($text);
    if ($length > 60000) {
        $shortText = substr($text, 0, 20000) . 
                     "\n[... MEIO DO DOCUMENTO ...]\n" . 
                     substr($text, ($length / 2) - 10000, 20000) . 
                     "\n[... FIM DO DOCUMENTO ...]\n" . 
                     substr($text, -20000);
    } else {
        $shortText = $text;
    }
    
    $jsonStructure = '{
      "nome_orgao": "...",
      "banca": "...",
      "nome_cargo": "...",
      "total_questoes": 0,
      "vagas": { "ampla": 0, "pcd": 0, "ppp": 0 },
      "regras": { "tem_discursiva": false, "pontos_negativos": false, "tem_titulos": false, "por_genero": false, "nota_padronizada": false },
      "materias": [ { "nome": "...", "sigla": "...", "inicio": 1, "fim": 10, "peso": 1.0 } ]
    }';

    $promptGroq = "Extraia os dados do edital em JSON seguindo RIGOROSAMENTE esta estrutura: $jsonStructure\n\nTEXTO: $shortText\n\nResponda APENAS o JSON puro.";
    $promptGemini = "Extraia os dados do edital abaixo. Analise o documento INTEIRO e responda seguindo esta estrutura JSON: $jsonStructure\n\nTEXTO: $text\n\nResponda APENAS o JSON puro.";

    // 1. Tenta Groq Primeiro
    $jsonResponse = callGroqAI($promptGroq, "Você é um motor de extração JSON. Responda apenas o objeto JSON puro.");
    
    // 2. Se a Groq falhar (ex: chave ausente ou erro), tenta o Gemini (Failover)
    if (!$jsonResponse || strpos($jsonResponse, 'Erro') !== false || strlen($jsonResponse) < 50) {
        $jsonResponse = callGeminiAI($promptGemini);
    }
    
    // Limpar markdown e extrair apenas o objeto JSON
    if (preg_match('/({.*})/s', $jsonResponse, $matches)) {
        $jsonResponse = $matches[1];
    }
    $trimmedResponse = trim($jsonResponse);
    
    $data = json_decode($trimmedResponse, true);

    if ($data && isset($data['nome_orgao'])) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        $errorMsg = 'A IA não conseguiu estruturar os dados do edital.';
        
        // Verifica se o erro é por falta de configuração
        if (strpos($jsonResponse, 'Chave da API') !== false) {
            $errorMsg = "Configuração Necessária: " . $jsonResponse;
        } elseif (strpos($jsonResponse, 'Erro AI') !== false || strpos($jsonResponse, 'Erro Gemini') !== false) {
            $errorMsg = $jsonResponse;
        }

        echo json_encode([
            'success' => false, 
            'error' => $errorMsg, 
            'details' => substr(trim($jsonResponse), 0, 500)
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
