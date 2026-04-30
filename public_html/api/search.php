<?php
require_once __DIR__ . '/../../includes/db.php';

$query = $_GET['q'] ?? '';

if (strlen($query) >= 2) {
    try {
        $stmt = $pdo->prepare("SELECT c.nome_orgao, c.banca, c.status, c.icon, c.image_url, c.data_prova, cg.nome_cargo, cg.id as cargo_id, cg.pnc_ia,
                               (SELECT COUNT(*) FROM respostas_usuarios ru WHERE ru.cargo_id = cg.id) as total_amostras,
                               (SELECT MAX(nota_estimada) FROM respostas_usuarios ru WHERE ru.cargo_id = cg.id) as nota_maxima
                               FROM concursos c 
                               JOIN cargos cg ON c.id = cg.concurso_id 
                               WHERE c.nome_orgao LIKE ? OR c.banca LIKE ? OR cg.nome_cargo LIKE ?
                               ORDER BY c.criado_em DESC
                               LIMIT 15");
        $stmt->execute(["%$query%", "%$query%", "%$query%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($results);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    // Return all if query is too short (or handle differently)
    echo json_encode([]);
}
?>
