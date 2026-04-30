<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once 'ai_logic.php';
require_once 'groq_api.php';

header('Content-Type: application/json');
requireAdmin();

$input = json_decode(file_get_contents('php://input'), true);

// CSRF Manual para JSON payload
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'error' => 'Erro de segurança CSRF.']);
    exit;
}

$cargo_id = $input['cargo_id'] ?? '';
$versao = $input['versao'] ?? '';
$respostas = $input['respostas'] ?? [];

if (!$cargo_id || !$versao || empty($respostas)) {
    echo json_encode(['success' => false, 'error' => 'Dados incompletos.']);
    exit;
}

try {
    $respostas_json = json_encode($respostas);
    
    $pdo->beginTransaction();
    
    // 1. Salvar Gabarito Oficial
    $stmt = $pdo->prepare("INSERT INTO gabaritos_oficiais (cargo_id, versao, respostas_json) 
                           VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE respostas_json = VALUES(respostas_json)");
    $stmt->execute([$cargo_id, $versao, $respostas_json]);
    
    // 2. Recalcular Notas (Zero Trust Logic)
    $stmt = $pdo->prepare("SELECT id, respostas_json, versao FROM respostas_usuarios WHERE cargo_id = ? AND deleted_at IS NULL");
    $stmt->execute([$cargo_id]);
    $participantes = $stmt->fetchAll();
    
    foreach ($participantes as $p) {
        $resp_user = json_decode($p['respostas_json'], true);
        // Só recalcula se a versão for a mesma ou se o participante for da versão detectada
        if ($p['versao'] == $versao) {
            $nova_nota = calcularNotaEstimada($pdo, $cargo_id, $versao, $resp_user);
            if ($nova_nota !== null) {
                $upd = $pdo->prepare("UPDATE respostas_usuarios SET nota_estimada = ? WHERE id = ?");
                $upd->execute([$nova_nota, $p['id']]);
            }
        }
    }
    
    // 3. Log Auditoria
    logAction($pdo, "AI_PDF_IMPORT_OFFICIAL", "gabaritos_oficiais", $cargo_id, ["versao" => $versao]);

    $pdo->commit();
    
    // Atualizar IA
    atualizarPredicoesIA($pdo, $cargo_id);
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
