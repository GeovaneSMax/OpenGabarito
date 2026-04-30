<?php
/**
 * Lógica de Inteligência Coletiva e Processamento Estatístico Avançado (v2.0)
 */

/**
 * Calcula o gabarito de consenso usando uma abordagem ponderada pela confiabilidade dos usuários.
 */
function atualizarConsenso($pdo, $cargo_id) {
    // 1. Busca todas as respostas (Filtra deletados e suspeitos para manter a integridade do consenso)
    $stmt = $pdo->prepare("SELECT respostas_json, versao, usuario_id FROM respostas_usuarios WHERE cargo_id = ? AND deleted_at IS NULL AND is_suspicious = 0");
    $stmt->execute([$cargo_id]);
    $todas_respostas = $stmt->fetchAll();

    if (empty($todas_respostas)) return;

    // Se houver apenas 1 resposta, fazemos uma auditoria de verossimilhança
    if (count($todas_respostas) === 1) {
        $resp = $todas_respostas[0];
        $respostas = json_decode($resp['respostas_json'], true);
        $counts = array_count_values($respostas);
        arsort($counts);
        $mais_frequente = current($counts);
        $total = count($respostas);
        
        if ($total > 0 && ($mais_frequente / $total) > 0.85) {
            // Marcou a mesma letra em mais de 85% da prova - Provável troll
            $pdo->prepare("UPDATE respostas_usuarios SET is_suspicious = 1 WHERE usuario_id = ? AND cargo_id = ?")->execute([$resp['usuario_id'], $cargo_id]);
            return;
        }
    }

    // 2. Primeiro Passo: Cálculo de Confiabilidade Base (Alinhamento Simples)
    // Usamos um rascunho de consenso para identificar quem são os "outliers"
    $rascunho = []; // [versao][questao][alternativa] = qtd
    foreach ($todas_respostas as $resp) {
        if ($resp['versao'] == 0) continue;
        $respostas = json_decode($resp['respostas_json'], true);
        foreach ($respostas as $q_num => $alt) {
            $rascunho[$resp['versao']][$q_num][$alt] = ($rascunho[$resp['versao']][$q_num][$alt] ?? 0) + 1;
        }
    }

    $gabarito_rascunho = [];
    foreach ($rascunho as $v => $questoes) {
        foreach ($questoes as $q_num => $alts) {
            arsort($alts);
            $gabarito_rascunho[$v][$q_num] = array_key_first($alts);
        }
    }

    // 3. Segundo Passo: Atribuição de Pesos (IA de Detecção de Anomalias)
    $pesos_usuarios = [];
    foreach ($todas_respostas as $resp) {
        $v = $resp['versao'];
        if ($v == 0 || !isset($gabarito_rascunho[$v])) {
            $pesos_usuarios[$resp['usuario_id']] = 1.0;
            continue;
        }

        $respostas = json_decode($resp['respostas_json'], true);
        $matches = 0;
        $total_q = count($respostas);
        
        foreach ($respostas as $q_num => $alt) {
            if (isset($gabarito_rascunho[$v][$q_num]) && $gabarito_rascunho[$v][$q_num] === $alt) {
                $matches++;
            }
        }

        $taxa_alinhamento = $total_q > 0 ? $matches / $total_q : 0;

        // Lógica de Detecção de Anomalia:
        // Se o usuário erra demais em relação ao grupo (abaixo de 15% de alinhamento), 
        // seu peso cai para quase zero para não poluir o consenso (provável troll ou erro grave).
        if ($taxa_alinhamento < 0.15) {
            $pesos_usuarios[$resp['usuario_id']] = 0.01; 
        } else {
            // Peso aumenta linearmente com o alinhamento
            $pesos_usuarios[$resp['usuario_id']] = 0.5 + ($taxa_alinhamento * 1.5); 
        }
    }

    // 4. PASSO ESPECIAL: Descoberta Automática de Novas Versões (Clustering)
    // Se houver usuários que não batem com nada (V1, V2, V3), vamos ver se eles batem entre si.
    $orfaos = [];
    foreach ($todas_respostas as $resp) {
        $v = $resp['versao'];
        if ($v == 0 || !isset($gabarito_rascunho[$v])) {
            $orfaos[] = $resp;
            continue;
        }
        // Se o alinhamento dele com a versão informada é ridículo (< 20%), ele é um órfão potencial
        $respostas = json_decode($resp['respostas_json'], true);
        $matches = 0; $total = count($respostas);
        foreach($respostas as $q => $a) if(($gabarito_rascunho[$v][$q] ?? '') === $a) $matches++;
        if ($total > 0 && ($matches / $total) < 0.20) {
            $orfaos[] = $resp;
        }
    }

    if (count($orfaos) >= 2) {
        // Compara os órfãos entre si para ver se formam uma nova "família" (ex: V4)
        for ($i = 0; $i < count($orfaos); $i++) {
            for ($j = $i + 1; $j < count($orfaos); $j++) {
                $resp1 = json_decode($orfaos[$i]['respostas_json'], true);
                $resp2 = json_decode($orfaos[$j]['respostas_json'], true);
                $matches = 0; $total = count($resp1);
                foreach($resp1 as $q => $a) if(($resp2[$q] ?? '') === $a) $matches++;
                
                if ($total > 0 && ($matches / $total) > 0.80) {
                    // ACHAMOS UMA NOVA VERSÃO! 
                    // Se eles estavam na V1, mas concordam entre si em 80%, vamos movê-los para a próxima versão livre (V4)
                    $nova_v = 1;
                    while(isset($contagem_final[$nova_v])) $nova_v++;
                    
                    if ($nova_v <= 4) { // Limite usual de 4 versões
                        $pdo->prepare("UPDATE respostas_usuarios SET versao = ? WHERE id IN (?, ?)")->execute([$nova_v, $orfaos[$i]['id'], $orfaos[$j]['id']]);
                        // Recarrega para processar no próximo ciclo ou continua
                    }
                }
            }
        }
    }

    // 5. Terceiro Passo: Consenso Ponderado Final
    $contagem_final = [];
    foreach ($todas_respostas as $resp) {
        $v = $resp['versao'];
        if ($v == 0) continue;
        
        $respostas = json_decode($resp['respostas_json'], true);
        $peso = $pesos_usuarios[$resp['usuario_id']];

        foreach ($respostas as $q_num => $alt) {
            $contagem_final[$v][$q_num][$alt] = ($contagem_final[$v][$q_num][$alt] ?? 0) + $peso;
        }
    }

    // Salva no banco com o grau de confiança da IA
    foreach ($contagem_final as $versao => $questoes) {
        foreach ($questoes as $q_num => $alternativas) {
            arsort($alternativas);
            $mais_votada = array_key_first($alternativas);
            $total_pesos_q = array_sum($alternativas);
            
            // Confiança é o peso da alternativa líder sobre o total de pesos
            $confianca = ($alternativas[$mais_votada] / $total_pesos_q) * 100;

            $stmt = $pdo->prepare("INSERT INTO gabarito_consenso (cargo_id, versao, questao_numero, alternativa_escolhida, confianca) 
                                   VALUES (?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE alternativa_escolhida = VALUES(alternativa_escolhida), confianca = VALUES(confianca)");
            $stmt->execute([$cargo_id, $versao, $q_num, $mais_votada, $confianca]);
        }
    }

    // 6. Sincronização de Notas: Recalcula a nota de todos os participantes com as novas regras e consenso
    $stmt = $pdo->prepare("SELECT id, usuario_id, versao, respostas_json FROM respostas_usuarios WHERE cargo_id = ? AND deleted_at IS NULL");
    $stmt->execute([$cargo_id]);
    $participantes = $stmt->fetchAll();

    $upd = $pdo->prepare("UPDATE respostas_usuarios SET nota_estimada = ?, status_eliminado = ? WHERE id = ?");
    foreach ($participantes as $p) {
        $resultado = calcularNotaEstimada($pdo, $cargo_id, $p['versao'], json_decode($p['respostas_json'], true));
        if ($resultado) {
            $upd->execute([$resultado['nota'], $resultado['eliminado'] ? 1 : 0, $p['id']]);
        }
    }
}

/**
 * Detecta a versão da prova usando correlação de Pearson simplificada.
 */
/**
 * Detecta a versão real da prova com base na semelhança estatística.
 * Skill: Prevenção de Erro de Input (Anti-Troll/Anti-Erro)
 */
function detectarVersao($pdo, $cargo_id, $respostas_usuario, $versao_informada = 0) {
    $padroes = [];
    
    // 1. Prioridade: Gabaritos Oficiais
    $stmt = $pdo->prepare("SELECT versao, respostas_json FROM gabaritos_oficiais WHERE cargo_id = ?");
    $stmt->execute([$cargo_id]);
    while ($row = $stmt->fetch()) {
        $padroes[$row['versao']] = json_decode($row['respostas_json'], true);
    }

    // 2. Secundário: Gabarito de Consenso (Apenas para versões que não possuem gabarito oficial)
    $stmt = $pdo->prepare("SELECT versao, questao_numero, alternativa_escolhida FROM gabarito_consenso WHERE cargo_id = ?");
    $stmt->execute([$cargo_id]);
    while ($row = $stmt->fetch()) {
        if (!isset($padroes[$row['versao']])) {
            $padroes[$row['versao']] = [];
        }
        // Só preenche se a questão não existir (para não sobrescrever oficial, se houver)
        if (!isset($padroes[$row['versao']][$row['questao_numero']])) {
            $padroes[$row['versao']][$row['questao_numero']] = $row['alternativa_escolhida'];
        }
    }

    // Se não há nenhum padrão no sistema, aceita o que o usuário disse ou assume v1
    if (empty($padroes)) return ($versao_informada > 0) ? $versao_informada : 1;

    $scores = [];
    foreach ($padroes as $v => $gabarito) {
        $match = 0;
        $total_comparado = 0;
        foreach ($respostas_usuario as $q_num => $alt) {
            if (isset($gabarito[$q_num])) {
                $total_comparado++;
                if ($gabarito[$q_num] === $alt) $match++;
            }
        }
        $scores[$v] = $total_comparado > 0 ? ($match / $total_comparado) : 0;
    }

    arsort($scores);
    $melhor_v = array_key_first($scores);
    $melhor_score = $scores[$melhor_v];

    // LÓGICA DE CORREÇÃO INTELIGENTE:
    if ($versao_informada > 0) {
        // Se a versão que o usuário ESCOLHEU não tem nenhum dado ainda, 
        // ele é o PIONEIRO dessa versão. Não tentamos corrigir.
        if (!isset($padroes[$versao_informada])) {
            return $versao_informada;
        }

        $score_informado = $scores[$versao_informada] ?? 0;
        
        // Só corrigimos se ele estiver indo MUITO mal na que escolheu (<45%)
        // E MUITO bem em outra (>50%) com uma diferença clara (>30%)
        if ($score_informado < 0.45 && $melhor_score > 0.50 && ($melhor_score - $score_informado) > 0.30) {
            return $melhor_v;
        }
        return $versao_informada;
    }

    return ($melhor_v > 0) ? $melhor_v : 1;
}

/**
 * Calcula a nota baseada no consenso ponderado e regras do edital.
 */
function calcularNotaEstimada($pdo, $cargo_id, $versao, $respostas_usuario) {
    // 1. Buscar Regras do Cargo (Pesos, Negativas, etc)
    $stmt = $pdo->prepare("SELECT pontos_negativos, total_questoes FROM cargos WHERE id = ?");
    $stmt->execute([$cargo_id]);
    $regras = $stmt->fetch();
    
    // 2. Buscar Pesos por Matéria
    $stmt = $pdo->prepare("SELECT * FROM cargo_materias WHERE cargo_id = ?");
    $stmt->execute([$cargo_id]);
    $materias = $stmt->fetchAll();

    // 3. Buscar Gabarito (Oficial ou Consenso)
    $stmt = $pdo->prepare("SELECT respostas_json FROM gabaritos_oficiais WHERE cargo_id = ? AND versao = ?");
    $stmt->execute([$cargo_id, $versao]);
    $oficial = $stmt->fetchColumn();

    if ($oficial) {
        $gabarito = json_decode($oficial, true);
    } else {
        $stmt = $pdo->prepare("SELECT questao_numero, alternativa_escolhida FROM gabarito_consenso WHERE cargo_id = ? AND versao = ?");
        $stmt->execute([$cargo_id, $versao]);
        $gabarito = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    if (empty($gabarito)) return null;

    $nota_total = 0;
    $eliminado = false;
    $detalhe_materias = [];

    // Mapear cada questão para sua matéria e peso
    $mapa_questoes = [];
    foreach ($materias as $m) {
        for ($i = $m['questao_inicio']; $i <= $m['questao_fim']; $i++) {
            $mapa_questoes[$i] = [
                'peso' => (float)$m['peso'],
                'minimo' => (int)$m['minimo_acertos'],
                'materia_id' => $m['id']
            ];
        }
    }

    // Processar cada resposta
    $acertos_por_materia = [];
    foreach ($respostas_usuario as $q_num => $alt) {
        if (!isset($gabarito[$q_num])) continue;

        $peso = $mapa_questoes[$q_num]['peso'] ?? 1.0;
        $m_id = $mapa_questoes[$q_num]['materia_id'] ?? 0;
        
        if (!isset($acertos_por_materia[$m_id])) $acertos_por_materia[$m_id] = 0;

        if ($alt === $gabarito[$q_num]) {
            $nota_total += $peso;
            $acertos_por_materia[$m_id]++;
        } else if ($regras['pontos_negativos'] && $alt !== 'S' && $alt !== '') {
            // Se for estilo CESPE e errou (e não deixou em branco)
            $nota_total -= $peso;
        }
    }

    // Verificar Mínimos por Matéria
    foreach ($materias as $m) {
        $acertos = $acertos_por_materia[$m['id']] ?? 0;
        if ($m['minimo_acertos'] > 0 && $acertos < $m['minimo_acertos']) {
            $eliminado = true;
        }
    }

    // Retorna um array com a nota e status (o banco deve lidar com isso)
    return [
        'nota' => max(0, $nota_total), // Nota não pode ser negativa
        'eliminado' => $eliminado
    ];
}

/**
 * Calcula o desempenho detalhado por matéria.
 */
function calcularDesempenhoMaterias($pdo, $cargo_id, $versao, $respostas_usuario) {
    // 1. Buscar matérias
    $stmt = $pdo->prepare("SELECT * FROM cargo_materias WHERE cargo_id = ? ORDER BY questao_inicio");
    $stmt->execute([$cargo_id]);
    $materias = $stmt->fetchAll();
    
    if (empty($materias)) return [];

    // 2. Buscar gabarito
    $stmt = $pdo->prepare("SELECT respostas_json FROM gabaritos_oficiais WHERE cargo_id = ? AND versao = ?");
    $stmt->execute([$cargo_id, $versao]);
    $oficial = $stmt->fetchColumn();

    if ($oficial) {
        $gabarito = json_decode($oficial, true);
    } else {
        $stmt = $pdo->prepare("SELECT questao_numero, alternativa_escolhida FROM gabarito_consenso WHERE cargo_id = ? AND versao = ?");
        $stmt->execute([$cargo_id, $versao]);
        $gabarito = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    $desempenho = [];
    foreach ($materias as $m) {
        $acertos = 0;
        $total = ($m['questao_fim'] - $m['questao_inicio']) + 1;
        
        for ($i = $m['questao_inicio']; $i <= $m['questao_fim']; $i++) {
            if (isset($respostas_usuario[$i]) && isset($gabarito[$i]) && $respostas_usuario[$i] === $gabarito[$i]) {
                $acertos++;
            }
        }
        
        $desempenho[] = [
            'sigla' => $m['sigla_materia'],
            'nome' => $m['nome_materia'],
            'acertos' => $acertos,
            'total' => $total
        ];
    }
    
    return $desempenho;
}

/**
 * Utiliza IA para verificar se os dados de um concurso/cargo parecem verídicos.
 */
function verificarEdicaoConcursoIA($dados) {
    if (!checkRateLimit('wiki_edit', 20, 3600)) {
        return ['confianca' => 0, 'veredito' => 'suspeito', 'motivo' => 'Limite de requisições atingido. Tente novamente em 1 hora.'];
    }

    $prompt = "Você é um auditor de dados especializado em concursos públicos brasileiros. 
    Analise se os seguintes dados parecem REAIS ou se são SPAM/TROLL.
    
    Órgão: {$dados['nome_orgao']}
    Banca: {$dados['banca']}
    Cargo: {$dados['nome_cargo']}
    Total de Questões: {$dados['total_questoes']}
    
    Regras de Auditoria:
    - Bancas famosas (FGV, FCC, CESPE, VUNESP) raramente fazem provas com menos de 30 ou mais de 120 questões.
    - O nome do cargo deve ser algo profissional.
    - O órgão deve existir ou soar como um órgão público real.
    
    Responda EXCLUSIVAMENTE em formato JSON:
    {
        \"confianca\": 0-100, 
        \"veredito\": \"valido\"|\"suspeito\"|\"invalido\",
        \"motivo\": \"breve explicação em português\"
    }";

    try {
        $response = callGroqAI($prompt, "Você é um auditor rigoroso. Retorne apenas JSON.");
        
        // Limpeza básica se houver markdown
        $json_str = preg_replace('/```json|```/', '', $response);
        $res = json_decode(trim($json_str), true);
        
        return $res ?: ['confianca' => 50, 'veredito' => 'suspeito', 'motivo' => 'Falha na análise técnica.'];
    } catch (Exception $e) {
        return ['confianca' => 50, 'veredito' => 'suspeito', 'motivo' => 'IA indisponível no momento.'];
    }
}

/**
 * Registra o log de edição e ajusta a reputação do usuário.
 */
function registrarEdicaoWiki($pdo, $usuario_id, $tipo, $id, $antigos, $novos, $score_ia) {
    $stmt = $pdo->prepare("INSERT INTO edicoes_log (usuario_id, tipo_objeto, objeto_id, dados_anteriores, dados_novos, score_ia) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $usuario_id, 
        $tipo, 
        $id, 
        json_encode($antigos), 
        json_encode($novos), 
        $score_ia
    ]);

    // Ajusta o Trust Score do usuário
    // Se a IA deu nota alta (>80), ganha 2 pontos. Se deu nota baixa (<40), perde 10 pontos.
    if ($score_ia > 80) {
        $pdo->prepare("UPDATE usuarios SET trust_score = LEAST(trust_score + 2, 100) WHERE id = ?")->execute([$usuario_id]);
    } elseif ($score_ia < 40) {
        $pdo->prepare("UPDATE usuarios SET trust_score = GREATEST(trust_score - 10, 0) WHERE id = ?")->execute([$usuario_id]);
    }
}
?>