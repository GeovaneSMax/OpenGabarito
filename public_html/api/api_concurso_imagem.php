<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
requireLogin();

$action = $_GET['action'] ?? '';
$concurso_id = $_GET['concurso_id'] ?? ($_POST['concurso_id'] ?? '');

if (empty($concurso_id)) {
    echo json_encode(['success' => false, 'error' => 'Concurso não especificado.']);
    exit;
}

try {
    if ($action === 'upload') {
        if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erro no upload da imagem.");
        }

        $file = $_FILES['imagem'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array(strtolower($file['type']), $allowedTypes) && !in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'])) {
            throw new Exception("Tipo de arquivo (" . $file['type'] . ") não permitido. Use JPG, PNG ou WEBP.");
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (empty($ext)) $ext = 'jpg';
        
        $filename = uniqid('concurso_' . $concurso_id . '_') . '.' . $ext;
        $uploadDir = __DIR__ . '/../uploads/concursos/';
        
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0775, true)) {
                throw new Exception("Não foi possível criar o diretório de uploads.");
            }
        }

        if (!is_writable($uploadDir)) {
            throw new Exception("O diretório de uploads não tem permissão de escrita.");
        }

        $destination = $uploadDir . $filename;
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $url = 'uploads/concursos/' . $filename;
            $stmt = $pdo->prepare("INSERT INTO concurso_imagens (concurso_id, usuario_id, url) VALUES (?, ?, ?)");
            $stmt->execute([$concurso_id, $_SESSION['usuario_id'], $url]);
            
            // Sincronizar imagem mais votada (se for a primeira)
            $stmt = $pdo->prepare("UPDATE concursos SET image_url = ? WHERE id = ? AND (image_url IS NULL OR image_url = '')");
            $stmt->execute([$url, $concurso_id]);

            echo json_encode(['success' => true, 'message' => 'Imagem enviada com sucesso!']);
        } else {
            throw new Exception("Falha ao salvar o arquivo no servidor.");
        }

    } elseif ($action === 'listar') {
        $stmt = $pdo->prepare("
            SELECT ci.*, u.nome as autor, 
            (SELECT voto FROM votos_imagens WHERE imagem_id = ci.id AND usuario_id = ?) as meu_voto
            FROM concurso_imagens ci
            JOIN usuarios u ON ci.usuario_id = u.id
            WHERE ci.concurso_id = ?
            ORDER BY (ci.votos_positivos - ci.votos_negativos) DESC, ci.criado_em DESC
        ");
        $stmt->execute([$_SESSION['usuario_id'], $concurso_id]);
        $imagens = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $imagens]);

    } elseif ($action === 'votar') {
        $input = json_decode(file_get_contents('php://input'), true);
        $imagem_id = $input['imagem_id'] ?? '';
        $voto = (int)($input['voto'] ?? 0); // 1 ou -1

        if (!in_array($voto, [1, -1])) {
            throw new Exception("Voto inválido.");
        }

        // Verifica se já votou
        $stmt = $pdo->prepare("SELECT voto FROM votos_imagens WHERE imagem_id = ? AND usuario_id = ?");
        $stmt->execute([$imagem_id, $_SESSION['usuario_id']]);
        $votoExistente = $stmt->fetchColumn();

        $pdo->beginTransaction();
        if ($votoExistente) {
            if ($votoExistente == $voto) {
                // Remove o voto se for igual (toggle)
                $pdo->prepare("DELETE FROM votos_imagens WHERE imagem_id = ? AND usuario_id = ?")->execute([$imagem_id, $_SESSION['usuario_id']]);
                if ($voto == 1) $pdo->prepare("UPDATE concurso_imagens SET votos_positivos = votos_positivos - 1 WHERE id = ?")->execute([$imagem_id]);
                else $pdo->prepare("UPDATE concurso_imagens SET votos_negativos = votos_negativos - 1 WHERE id = ?")->execute([$imagem_id]);
            } else {
                // Muda o voto
                $pdo->prepare("UPDATE votos_imagens SET voto = ? WHERE imagem_id = ? AND usuario_id = ?")->execute([$voto, $imagem_id, $_SESSION['usuario_id']]);
                if ($voto == 1) {
                    $pdo->prepare("UPDATE concurso_imagens SET votos_positivos = votos_positivos + 1, votos_negativos = votos_negativos - 1 WHERE id = ?")->execute([$imagem_id]);
                } else {
                    $pdo->prepare("UPDATE concurso_imagens SET votos_positivos = votos_positivos - 1, votos_negativos = votos_negativos + 1 WHERE id = ?")->execute([$imagem_id]);
                }
            }
        } else {
            // Novo voto
            $pdo->prepare("INSERT INTO votos_imagens (imagem_id, usuario_id, voto) VALUES (?, ?, ?)")->execute([$imagem_id, $_SESSION['usuario_id'], $voto]);
            if ($voto == 1) $pdo->prepare("UPDATE concurso_imagens SET votos_positivos = votos_positivos + 1 WHERE id = ?")->execute([$imagem_id]);
            else $pdo->prepare("UPDATE concurso_imagens SET votos_negativos = votos_negativos + 1 WHERE id = ?")->execute([$imagem_id]);
        }
        // Sincronizar imagem mais votada com a tabela concursos
        $stmt = $pdo->prepare("
            UPDATE concursos c SET image_url = (
                SELECT url FROM concurso_imagens 
                WHERE concurso_id = c.id 
                ORDER BY (votos_positivos - votos_negativos) DESC, criado_em DESC 
                LIMIT 1
            ) WHERE id = ?
        ");
        $stmt->execute([$concurso_id]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Ação não reconhecida.");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

