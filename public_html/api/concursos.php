<?php
require_once __DIR__ . '/../../includes/db.php';

$search = $_GET['search'] ?? '';

if (strlen($search) > 2) {
    try {
        $stmt = $pdo->prepare("SELECT c.nome_orgao, c.banca, c.status, cg.nome_cargo, cg.id as cargo_id 
                               FROM concursos c 
                               JOIN cargos cg ON c.id = cg.concurso_id 
                               WHERE c.nome_orgao LIKE ? 
                               LIMIT 5");
        $stmt->execute(["%$search%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($results);
    } catch (PDOException $e) {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>
