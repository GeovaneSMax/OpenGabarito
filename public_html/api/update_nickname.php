<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nickname'])) {
    try {
        validateCSRF();
        
        $nickname = trim($_POST['nickname']);
        $usuario_id = $_SESSION['usuario_id'];

        // Validações básicas
        if (strlen($nickname) < 3 || strlen($nickname) > 20) {
            header("Location: ../minha_area.php?error=tamanho");
            exit;
        }

        // Sanitização extra se necessário, mas prepared statements resolvem SQL injection
        // Remove caracteres que podem quebrar o layout
        $nickname = htmlspecialchars($nickname);

        $stmt = $pdo->prepare("UPDATE usuarios SET nickname = ? WHERE id = ?");
        $stmt->execute([$nickname, $usuario_id]);

        // Atualiza a sessão
        $_SESSION['usuario_nickname'] = $nickname;

        header("Location: ../minha_area.php?success=nickname");
        exit;
    } catch (Exception $e) {
        header("Location: ../minha_area.php?error=sistema");
        exit;
    }
}

header("Location: ../minha_area.php");
