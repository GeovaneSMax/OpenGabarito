<?php
require_once __DIR__ . '/config.php';

/**
 * Handler para requisições à API do Groq com Failover Automático
 */
function callGroqAI($prompt, $system_message = "Você é um analista estatístico especialista em concursos públicos.") {
    $apiKey = GROQ_API_KEY;
    if (empty($apiKey)) {
        return "Erro AI: Chave da API Groq não configurada no arquivo .env";
    }
    $models = GROQ_MODELS;
    $lastError = "";

    foreach ($models as $model) {
        try {
            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
            
            if (strlen($prompt) > 1000) {
                error_log("Groq Large Prompt: " . strlen($prompt) . " chars. Model: $model");
            }

            $data = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system_message],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1, // Menor temperatura para resultados mais consistentes em dados
                'max_tokens' => 8192
            ];

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); 

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                $content = $result['choices'][0]['message']['content'] ?? null;
                
                if ($content && !empty(trim($content))) {
                    return $content;
                } else {
                    $lastError = "Conteúdo vazio do modelo $model";
                    continue;
                }
            } else {
                $lastError = "HTTP $httpCode no modelo $model";
                continue; 
            }
        } catch (Exception $e) {
            $lastError = $e->getMessage();
            continue;
        }
    }

    return "Erro AI: " . $lastError;
}

/**
 * Gera uma análise estatística dos resultados usando IA
 */
function gerarAnaliseResultados($info_cargo, $ranking, $media, $pnc, $user_rank = null) {
    if (!checkRateLimit('ai_analysis', 30, 3600)) {
        return "Limite de análises atingido por hoje. Tente novamente mais tarde.";
    }
    $total = count($ranking);
    $user_pos = $user_rank ? $user_rank . "º" : "Não cadastrada";
    
    $prompt = "Aja como um oráculo de dados. Concurso: '{$info_cargo['nome_cargo']}'.
    
    Responda em APENAS 4 LINHAS:
    1. Nota de Corte Prevista: {$pnc}
    2. Sua Posição: {$user_pos} de {$total}
    3. Vagas Totais: {$info_cargo['vagas']}
    4. Inscritos Estimados: " . number_format($info_cargo['inscritos'], 0, ',', '.') . "
    
    NÃO escreva introduções ou conclusões. APENAS as 4 linhas.";

    require_once __DIR__ . '/gemini_api.php';
    $jsonResponse = callGroqAI($prompt);
    
    if (strpos($jsonResponse, 'Erro AI') !== false) {
        $jsonResponse = callGeminiAI($prompt, "Você é um oráculo de dados. Responda em 4 linhas.");
    }

    return $jsonResponse;
}

/**
 * Calcula Predições de IA (PNC e Acurácia) e salva no banco
 */
function atualizarPredicoesIA($pdo, $cargo_id) {
    if (!checkRateLimit('ai_prediction', 50, 3600)) {
        return;
    }
    try {
        // 1. Busca dados consolidados
        $stmt = $pdo->prepare("SELECT cg.*, c.nome_orgao FROM cargos cg JOIN concursos c ON cg.concurso_id = c.id WHERE cg.id = ?");
        $stmt->execute([$cargo_id]);
        $info = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT nota_estimada FROM respostas_usuarios WHERE cargo_id = ? ORDER BY nota_estimada DESC");
        $stmt->execute([$cargo_id]);
        $ranking = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($ranking)) return;

        $total = count($ranking);
        
        // 2. Solicita predição à IA com contexto de pontuação máxima
        $max_pontos = $info['total_questoes'];
        
        $prompt = "Aja como um cientista de dados especialista em concursos. 
        Contexto do cargo '{$info['nome_cargo']}':
        - Pontuação Máxima Possível: {$max_pontos} pontos
        - Total de Participantes Atuais: {$total}
        - Vagas Totais: {$info['vagas']}
        - Inscritos Estimados: {$info['inscritos']}
        
        Notas Atuais (Top 30): " . implode(", ", array_slice($ranking, 0, 30)) . "
        
        Lógica de Estimativa:
        1. Se as notas de topo estiverem muito próximas de {$max_pontos}, a concorrência é altíssima e o PNC deve ser elevado.
        2. Use a densidade de notas atuais para projetar a curva de Gauss para o total de {$info['inscritos']} inscritos.
        3. Mesmo com poucos participantes ({$total}), estabeleça um PNC realista baseado na dificuldade sugerida pelas notas atuais em relação ao máximo de {$max_pontos}.
        
        Responda APENAS em JSON:
        {
          \"pnc\": valor_decimal,
          \"accuracy\": valor_0_a_100,
          \"insight_curto\": \"frase de 10 palavras\"
        }";

        $response = callGroqAI($prompt, "Você é um motor de predição estatística que responde apenas em JSON.");
        
        // Extrai o JSON da resposta (caso venha com markdown)
        if (preg_match('/({.*})/s', $response, $matches)) {
            $json = json_decode($matches[1], true);
            if ($json && isset($json['pnc'], $json['accuracy'])) {
                // 3. Atualiza o banco
                $stmt = $pdo->prepare("UPDATE cargos SET pnc_ia = ? WHERE id = ?");
                $stmt->execute([$json['pnc'], $cargo_id]);
                
                $stmt = $pdo->prepare("UPDATE site_stats SET accuracy = ? WHERE id = 1");
                $stmt->execute([$json['accuracy']]);
            }
        }
    } catch (Exception $e) {
        error_log("Erro atualizarPredicoesIA: " . $e->getMessage());
    }
}
?>
