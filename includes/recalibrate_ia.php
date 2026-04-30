<?php
require_once dirname(__FILE__) . '/db.php';
require_once dirname(__FILE__) . '/groq_api.php';
require_once dirname(__FILE__) . '/ai_logic.php';

echo "Recalibrando predições de IA para todos os cargos...\n";

try {
    $stmt = $pdo->query("SELECT id FROM cargos WHERE deleted_at IS NULL");
    $cargos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($cargos as $cargo_id) {
        echo "Atualizando Cargo ID: $cargo_id...\n";
        atualizarConsenso($pdo, $cargo_id);
        atualizarPredicoesIA($pdo, $cargo_id);
    }

    echo "Concluído!\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
