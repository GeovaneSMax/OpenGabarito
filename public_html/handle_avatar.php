<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    validateCSRF();
    
    $caminho = handleSecureUpload($_FILES['avatar']);
    
    if ($caminho) {
        $stmt = $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
        $stmt->execute([$caminho, $_SESSION['usuario_id']]);
        
        // Atualiza a sessão
        $_SESSION['usuario_foto'] = $caminho;
        
        header("Location: index.php?upload_success=1");
        exit;
    } else {
        header("Location: index.php?upload_error=1");
        exit;
    }
}
header("Location: index.php");
