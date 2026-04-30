<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

$orgao = $_GET['orgao'] ?? '';
$banca = $_GET['banca'] ?? '';

if (!$orgao || !$banca) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    // Busca concursos similares
    $stmt = $pdo->prepare("SELECT c.id as concurso_id, c.nome_orgao, c.banca, cg.id as cargo_id, cg.nome_cargo 
                           FROM concursos c 
                           LEFT JOIN cargos cg ON c.id = cg.concurso_id 
                           WHERE c.nome_orgao LIKE ? AND c.banca LIKE ? 
                           ORDER BY c.criado_em DESC LIMIT 5");
    $stmt->execute(["%$orgao%", "%$banca%"]);
    $results = $stmt->fetchAll();

    if ($results) {
        echo json_encode(['exists' => true, 'matches' => $results]);
    } else {
        echo json_encode(['exists' => false]);
    }
} catch (PDOException $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
