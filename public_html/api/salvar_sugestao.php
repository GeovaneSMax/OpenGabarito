<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado para enviar atualizações.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mensagem = $_POST['mensagem'] ?? '';
    $tipo = $_POST['tipo'] ?? 'geral';
    $concurso_id = !empty($_POST['concurso_id']) ? (int)$_POST['concurso_id'] : null;
    $usuario_id = $_SESSION['usuario_id'];

    if (!empty($mensagem)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO sugestoes (usuario_id, mensagem, tipo, concurso_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$usuario_id, $mensagem, $tipo, $concurso_id]);
            echo json_encode(['success' => true, 'message' => 'Recebido! Obrigado por ajudar a manter a lista atualizada.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'A mensagem não pode estar vazia.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
}
