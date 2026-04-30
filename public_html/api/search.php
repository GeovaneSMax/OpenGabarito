<?php
require_once __DIR__ . '/../../includes/db.php';

$query = $_GET['q'] ?? '';
$filter = $_GET['filter'] ?? 'populares';

try {
    $sql = "SELECT c.nome_orgao, c.banca, c.status, c.icon, c.image_url, c.data_prova, cg.nome_cargo, cg.id as cargo_id, cg.slug, cg.pnc_ia,
            (SELECT COUNT(*) FROM respostas_usuarios ru WHERE ru.cargo_id = cg.id AND ru.deleted_at IS NULL) as total_amostras,
            (SELECT MAX(nota_estimada) FROM respostas_usuarios ru WHERE ru.cargo_id = cg.id AND ru.deleted_at IS NULL) as nota_maxima
            FROM concursos c 
            JOIN cargos cg ON c.id = cg.concurso_id 
            WHERE c.deleted_at IS NULL AND cg.deleted_at IS NULL ";
    
    $params = [];
    
    if (strlen($query) >= 2) {
        $sql .= " AND (c.nome_orgao LIKE ? OR c.banca LIKE ? OR cg.nome_cargo LIKE ?) ";
        $params[] = "%$query%";
        $params[] = "%$query%";
        $params[] = "%$query%";
    }

    if ($filter === 'abertos') {
        $sql .= " AND c.status = 'aberto' ";
    } elseif ($filter === 'encerrados') {
        $sql .= " AND c.status = 'consolidado' ";
    }

    // Ordenação
    if ($filter === 'recentes') {
        $sql .= " ORDER BY c.criado_em DESC, total_amostras DESC ";
    } else {
        // Padrão: Populares
        $sql .= " ORDER BY total_amostras DESC, c.criado_em DESC ";
    }

    $sql .= " LIMIT 30";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($results);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
