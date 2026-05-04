<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$usuario_id = $_SESSION['usuario_id'] ?? null;

if (!$usuario_id && $action !== 'list') {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado.']);
    exit;
}

switch ($action) {
    case 'list':
        $cargo_id = (int)($_GET['cargo_id'] ?? 0);
        $page = (int)($_GET['page'] ?? 1);
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
        $offset = ($page - 1) * $limit;

        if (!$cargo_id) {
            echo json_encode(['success' => false, 'message' => 'Cargo ID inválido.']);
            exit;
        }

        try {
            // Contar total de mensagens para paginação
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM chat_mensagens WHERE cargo_id = ?");
            $stmtCount->execute([$cargo_id]);
            $total_mensagens = $stmtCount->fetchColumn();
            $total_paginas = ceil($total_mensagens / $limit);

            // Buscar mensagens e informações do usuário com limite e offset
            // Ordenamos por criado_em DESC para pegar as mais recentes, depois invertemos no JS ou mantemos assim
            // Geralmente chats mostram as recentes embaixo, mas com paginação costuma ser as recentes na página 1.
            // O usuário pediu "página 1, 2 etc", então vamos ordenar por criado_em DESC.
            $stmt = $pdo->prepare("
            SELECT m.*, u.nickname as nome, u.foto_perfil, u.trust_score,
                   p.nickname as pai_nome
            FROM chat_mensagens m
            JOIN usuarios u ON m.usuario_id = u.id
            LEFT JOIN chat_mensagens pm ON m.pai_id = pm.id
            LEFT JOIN usuarios p ON pm.usuario_id = p.id
            WHERE m.cargo_id = ?
            ORDER BY m.criado_em DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $cargo_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $mensagens = $stmt->fetchAll();

            // Buscar reações para estas mensagens
            $ids = array_column($mensagens, 'id');
            $reacoes = [];
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmtR = $pdo->prepare("
                    SELECT r.*, u.nickname as nome
                    FROM chat_reacoes r
                    JOIN usuarios u ON r.usuario_id = u.id
                    WHERE r.mensagem_id IN ($placeholders)
                ");
                $stmtR->execute($ids);
                $todas_reacoes = $stmtR->fetchAll();

                foreach ($todas_reacoes as $r) {
                    $reacoes[$r['mensagem_id']][] = $r;
                }
            }

            // Formatar saída
            foreach ($mensagens as &$m) {
                $m['reacoes'] = $reacoes[$m['id']] ?? [];
                $m['mensagem'] = htmlspecialchars($m['mensagem']);
                $m['is_me'] = ($m['usuario_id'] == $usuario_id);
            }

            echo json_encode([
                'success' => true, 
                'data' => $mensagens, 
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_paginas,
                    'total_items' => (int)$total_mensagens
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao carregar chat: ' . $e->getMessage()]);
        }
        break;

    case 'send':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;
        
        try {
            validateCSRF();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }

        $cargo_id = (int)($_POST['cargo_id'] ?? 0);
        $concurso_id = (int)($_POST['concurso_id'] ?? 0);
        $pai_id = !empty($_POST['pai_id']) ? (int)$_POST['pai_id'] : null;
        $mensagem = trim($_POST['mensagem'] ?? '');

        if (!$cargo_id || !$concurso_id || empty($mensagem)) {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
            exit;
        }

        if (mb_strlen($mensagem) > 1000) {
            echo json_encode(['success' => false, 'message' => 'Mensagem muito longa (máx 1000 caracteres).']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO chat_mensagens (usuario_id, cargo_id, concurso_id, pai_id, mensagem) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$usuario_id, $cargo_id, $concurso_id, $pai_id, $mensagem]);
            echo json_encode(['success' => true, 'message' => 'Mensagem enviada!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao enviar: ' . $e->getMessage()]);
        }
        break;

    case 'react':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

        $mensagem_id = (int)($_POST['mensagem_id'] ?? 0);
        $emoji = trim($_POST['emoji'] ?? '');

        if (!$mensagem_id || empty($emoji)) {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
            exit;
        }

        try {
            // Verificar se já tem QUALQUER reação nesta mensagem
            $stmt = $pdo->prepare("SELECT id, emoji FROM chat_reacoes WHERE mensagem_id = ? AND usuario_id = ?");
            $stmt->execute([$mensagem_id, $usuario_id]);
            $existing = $stmt->fetch();

            if ($existing) {
                if ($existing['emoji'] === $emoji) {
                    // Mesmo emoji: Remover (toggle off)
                    $stmt = $pdo->prepare("DELETE FROM chat_reacoes WHERE id = ?");
                    $stmt->execute([$existing['id']]);
                    echo json_encode(['success' => true, 'action' => 'removed']);
                } else {
                    // Emoji diferente: Atualizar
                    $stmt = $pdo->prepare("UPDATE chat_reacoes SET emoji = ? WHERE id = ?");
                    $stmt->execute([$emoji, $existing['id']]);
                    echo json_encode(['success' => true, 'action' => 'updated']);
                }
            } else {
                // Nenhuma reação: Inserir
                $stmt = $pdo->prepare("INSERT INTO chat_reacoes (mensagem_id, usuario_id, emoji) VALUES (?, ?, ?)");
                $stmt->execute([$mensagem_id, $usuario_id, $emoji]);
                echo json_encode(['success' => true, 'action' => 'added']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro na reação: ' . $e->getMessage()]);
        }
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) exit;

        try {
            $stmt = $pdo->prepare("DELETE FROM chat_mensagens WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id, $usuario_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
        break;
}
