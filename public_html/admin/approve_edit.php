<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ai_logic.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log_id = $_POST['log_id'] ?? '';
    $acao = $_POST['acao'] ?? ''; // 'aprovar' ou 'rejeitar'

    if (!$log_id) die("ID Inválido");

    try {
        $pdo->beginTransaction();

        // 1. Busca o log pendente
        $stmt = $pdo->prepare("SELECT * FROM edicoes_log WHERE id = ? AND status = 'pendente'");
        $stmt->execute([$log_id]);
        $log = $stmt->fetch();

        if (!$log) throw new Exception("Edição não encontrada ou já processada.");

        if ($acao === 'aprovar') {
            $dados = json_decode($log['dados_novos'], true);
            $cargo_id = $log['objeto_id'];

            // 2. Replicar a lógica de salvamento do colaborar.php
            // Nota: Como os dados já foram auditados pela IA no envio, aqui apenas aplicamos.
            
            // Buscar concurso_id
            $stmt = $pdo->prepare("SELECT concurso_id FROM cargos WHERE id = ?");
            $stmt->execute([$cargo_id]);
            $concurso_id = $stmt->fetchColumn();

            // A. Atualizar Concurso
            $stmt = $pdo->prepare("UPDATE concursos SET nome_orgao = ?, banca = ?, data_prova = ?, link_oficial = ?, status = ? WHERE id = ?");
            $stmt->execute([
                $dados['nome_orgao'], 
                $dados['banca'], 
                !empty($dados['data_prova']) ? $dados['data_prova'] : null, 
                $dados['link_oficial'], 
                $dados['status'] ?? 'aberto',
                $concurso_id
            ]);

            // B. Atualizar Cargo
            $stmt = $pdo->prepare("UPDATE cargos SET nome_cargo = ?, total_questoes = ?, tem_discursiva = ?, tem_titulos = ?, pontos_negativos = ?, nota_padronizada = ?, por_genero = ?, nota_corte_oficial = ? WHERE id = ?");
            $stmt->execute([
                $dados['nome_cargo'],
                (int)$dados['total_questoes'],
                isset($dados['tem_discursiva']) ? 1 : 0,
                isset($dados['tem_titulos']) ? 1 : 0,
                isset($dados['pontos_negativos']) ? 1 : 0,
                isset($dados['nota_padronizada']) ? 1 : 0,
                isset($dados['por_genero']) ? 1 : 0,
                !empty($dados['nota_corte_oficial']) ? $dados['nota_corte_oficial'] : null,
                $cargo_id
            ]);

            // C. Atualizar Modalidades
            $modalidades = ['ampla', 'pcd', 'ppp', 'hipossuficiente', 'indigena', 'trans', 'quilombola'];
            foreach ($modalidades as $mod) {
                $inscritos = (int)($dados["inscritos_$mod"] ?? 0);
                $vagas = (int)($dados["vagas_$mod"] ?? 0);
                $vagas_2e = (int)($dados["v2e_$mod"] ?? 0);
                if ($inscritos > 0 || $vagas > 0) {
                    $stmt = $pdo->prepare("INSERT INTO cargo_modalidades (cargo_id, nome_modalidade, inscritos, vagas, vagas_2etapa) 
                                           VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE 
                                           inscritos = VALUES(inscritos), vagas = VALUES(vagas), vagas_2etapa = VALUES(vagas_2etapa)");
                    $stmt->execute([$cargo_id, $mod, $inscritos, $vagas, $vagas_2e]);
                }
            }

            // D. Atualizar Matérias
            if (isset($dados['materia_nome']) && is_array($dados['materia_nome'])) {
                $pdo->prepare("DELETE FROM cargo_materias WHERE cargo_id = ?")->execute([$cargo_id]);
                $stmt = $pdo->prepare("INSERT INTO cargo_materias (cargo_id, nome_materia, sigla_materia, questao_inicio, questao_fim, peso, minimo_acertos, usuario_id) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($dados['materia_nome'] as $idx => $nome) {
                    if (empty($nome)) continue;
                    $sigla = $dados['materia_sigla'][$idx] ?? substr($nome, 0, 3);
                    $inicio = (int)$dados['materia_inicio'][$idx];
                    $fim = (int)$dados['materia_fim'][$idx];
                    $peso = (float)($dados['materia_peso'][$idx] ?? 1.0);
                    $minimo = (int)($dados['materia_minimo'][$idx] ?? 0);
                    $stmt->execute([$cargo_id, $nome, $sigla, $inicio, $fim, $peso, $minimo, $log['usuario_id']]);
                }
            }

            // E. Gabarito Oficial (Se enviado)
            if (!empty($dados['gabarito_oficial_json'])) {
                $all_versions = json_decode($dados['gabarito_oficial_json'], true);
                if (is_array($all_versions)) {
                    foreach ($all_versions as $versao => $respostas) {
                        $res_json = json_encode($respostas);
                        $stmt = $pdo->prepare("INSERT INTO gabaritos_oficiais (cargo_id, versao, respostas_json) 
                                               VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE respostas_json = VALUES(respostas_json)");
                        $stmt->execute([$cargo_id, $versao, $res_json]);
                    }
                }
            }

            atualizarConsenso($pdo, $cargo_id);
            
            // Marcar como aprovado e dar bônus de reputação extra por aprovação manual
            $stmt = $pdo->prepare("UPDATE edicoes_log SET status = 'aprovado' WHERE id = ?");
            $stmt->execute([$log_id]);
            $pdo->prepare("UPDATE usuarios SET trust_score = LEAST(trust_score + 5, 100) WHERE id = ?")->execute([$log['usuario_id']]);

        } else {
            // Rejeitar
            $stmt = $pdo->prepare("UPDATE edicoes_log SET status = 'rejeitado' WHERE id = ?");
            $stmt->execute([$log_id]);
            // Penalidade por ter a edição rejeitada por um humano
            $pdo->prepare("UPDATE usuarios SET trust_score = GREATEST(trust_score - 15, 0) WHERE id = ?")->execute([$log['usuario_id']]);
        }

        $pdo->commit();
        header("Location: review_edits.php?success=1");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Erro ao processar: " . $e->getMessage());
    }
}
