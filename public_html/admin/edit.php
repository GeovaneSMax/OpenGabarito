<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helper.php';

requireAdmin();

$cargo_id = $_GET['cargo_id'] ?? '';
$sucesso = "";
$erro = "";

if (!$cargo_id) {
    header("Location: ../index.php");
    exit;
}

// 1. Fetch Current Info
$stmt = $pdo->prepare("SELECT cg.*, c.nome_orgao, c.banca, c.status as c_status, c.image_url 
                       FROM cargos cg 
                       JOIN concursos c ON cg.concurso_id = c.id 
                       WHERE cg.id = ?");
$stmt->execute([$cargo_id]);
$info = $stmt->fetch();

if (!$info) {
    die("Cargo não encontrado.");
}

// 2. Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cargo'])) {
        $nome_orgao = $_POST['nome_orgao'];
        $banca = $_POST['banca'];
        $c_status = $_POST['status'];
        $nome_cargo = $_POST['nome_cargo'];
        $total_questoes = $_POST['total_questoes'];
        $vagas = $_POST['vagas'];
        $inscritos = $_POST['inscritos'] ?? 0;
        $nota_corte_oficial = $_POST['nota_corte_oficial'] ?? null;
        
        // Handle Secure Upload
        $new_image_url = $info['image_url'];
        if (isset($_FILES['capa_file']) && $_FILES['capa_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedPath = handleSecureUpload($_FILES['capa_file']);
            if ($uploadedPath === false) {
                $erro = "Erro de segurança: Arquivo inválido ou malicioso detectado.";
            } else {
                $new_image_url = $uploadedPath;
            }
        } elseif (!empty($_POST['image_url'])) {
            $new_image_url = $_POST['image_url'];
        }

        if (!$erro) {
            try {
                $pdo->beginTransaction();

                // Update Concurso
                $stmt = $pdo->prepare("UPDATE concursos SET nome_orgao = ?, banca = ?, status = ?, image_url = ? WHERE id = ?");
                $stmt->execute([$nome_orgao, $banca, $c_status, $new_image_url, $info['concurso_id']]);

                // Update Cargo
                $stmt = $pdo->prepare("UPDATE cargos SET nome_cargo = ?, total_questoes = ?, vagas = ?, inscritos = ?, nota_corte_oficial = ? WHERE id = ?");
                $stmt->execute([$nome_cargo, $total_questoes, $vagas, $inscritos, $nota_corte_oficial, $cargo_id]);

                // Log the change
                logAction($pdo, "UPDATE_CONCURSO_CARGO", "concursos/cargos", $cargo_id, $info, $_POST);

                $pdo->commit();
                $sucesso = "Alterações salvas com sucesso!";
                
                // Refresh info
                $stmt = $pdo->prepare("SELECT cg.*, c.nome_orgao, c.banca, c.status as c_status, c.image_url, c.id as concurso_id FROM cargos cg JOIN concursos c ON cg.concurso_id = c.id WHERE cg.id = ?");
                $stmt->execute([$cargo_id]);
                $info = $stmt->fetch();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $erro = "Erro ao atualizar: " . $e->getMessage();
            }
        }
    }

    // Handle Add Subject
    if (isset($_POST['add_materia'])) {
        $nome = $_POST['materia_nome'];
        $sigla = $_POST['materia_sigla'];
        $inicio = (int)$_POST['materia_inicio'];
        $fim = (int)$_POST['materia_fim'];

        try {
            $stmt = $pdo->prepare("INSERT INTO cargo_materias (cargo_id, nome_materia, sigla_materia, questao_inicio, questao_fim) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$cargo_id, $nome, $sigla, $inicio, $fim]);
            $sucesso = "Matéria adicionada com sucesso!";
        } catch (PDOException $e) {
            $erro = "Erro ao adicionar matéria: " . $e->getMessage();
        }
    }

    // Handle Delete Subject
    if (isset($_POST['delete_materia'])) {
        $m_id = $_POST['materia_id'];
        $stmt = $pdo->prepare("DELETE FROM cargo_materias WHERE id = ? AND cargo_id = ?");
        $stmt->execute([$m_id, $cargo_id]);
        $sucesso = "Matéria removida!";
    }
}

// 3. Handle Deletion
if (isset($_POST['delete_concurso_confirm'])) {
    try {
        $concurso_id = $info['concurso_id'];
        $pdo->beginTransaction();
        
        // Soft Delete Answers
        $stmt = $pdo->prepare("UPDATE respostas_usuarios SET deleted_at = NOW() WHERE cargo_id IN (SELECT id FROM cargos WHERE concurso_id = ?)");
        $stmt->execute([$concurso_id]);
        
        // Soft Delete Cargos
        $stmt = $pdo->prepare("UPDATE cargos SET deleted_at = NOW() WHERE concurso_id = ?");
        $stmt->execute([$concurso_id]);
        
        // Soft Delete Concurso
        $stmt = $pdo->prepare("UPDATE concursos SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$concurso_id]);
        
        logAction($pdo, "SOFT_DELETE_CONCURSO", "concursos", $concurso_id, $info);

        $pdo->commit();
        header("Location: dashboard.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $erro = "Erro ao deletar: " . $e->getMessage();
    }
}

// Fetch Materias
$stmt = $pdo->prepare("SELECT * FROM cargo_materias WHERE cargo_id = ? ORDER BY questao_inicio");
$stmt->execute([$cargo_id]);
$materias = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin | Editar Ranking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body class="min-h-screen py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <div class="flex flex-col md:flex-row items-center justify-between mb-10 gap-4">
            <div>
                <h1 class="text-3xl font-black text-white">Editar Ranking</h1>
                <p class="text-slate-400">Administração de dados: ID #<?php echo $cargo_id; ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="../ranking.php?cargo_id=<?php echo $cargo_id; ?>" class="bg-slate-800 hover:bg-slate-700 text-white px-6 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left"></i> Voltar
                </a>
                <a href="gabarito.php?cargo_id=<?php echo $cargo_id; ?>" class="bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2 shadow-lg shadow-indigo-500/20">
                    <i class="fa-solid fa-check-double"></i> Gabarito
                </a>
            </div>
        </div>

        <?php if ($sucesso): ?>
            <div class="mb-8 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-2xl flex items-center gap-3">
                <i class="fa-solid fa-circle-check"></i> <?php echo $sucesso; ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mb-8 bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-2xl flex items-center gap-3">
                <i class="fa-solid fa-circle-xmark"></i> <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <div class="space-y-8">
            <form method="POST" enctype="multipart/form-data" class="space-y-8">
                <div class="glass-panel rounded-3xl p-8 shadow-2xl">
                    <h2 class="text-indigo-400 text-xs font-black uppercase tracking-widest mb-6">Dados do Concurso</h2>
                    <?php echo csrfInput(); ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Órgão</label>
                            <input type="text" name="nome_orgao" value="<?php echo htmlspecialchars($info['nome_orgao']); ?>" class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Banca</label>
                            <input type="text" name="banca" value="<?php echo htmlspecialchars($info['banca']); ?>" class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Status</label>
                            <select name="status" class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none">
                                <option value="aberto" <?php echo $info['c_status'] == 'aberto' ? 'selected' : ''; ?>>Aberto (Recebendo notas)</option>
                                <option value="consolidado" <?php echo $info['c_status'] == 'consolidado' ? 'selected' : ''; ?>>Consolidado (Encerrado)</option>
                                <option value="aguardando" <?php echo $info['c_status'] == 'aguardando' ? 'selected' : ''; ?>>Aguardando Prova</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Capa do Concurso (Upload Seguro)</label>
                            <input type="file" name="capa_file" accept="image/*" class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-3 text-slate-400">
                        </div>
                    </div>
                </div>

                <div class="glass-panel rounded-3xl p-8 shadow-2xl">
                    <h2 class="text-indigo-400 text-xs font-black uppercase tracking-widest mb-6">Dados do Cargo</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Nome do Cargo</label>
                            <input type="text" name="nome_cargo" value="<?php echo htmlspecialchars($info['nome_cargo']); ?>" class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Total Questões</label>
                            <input type="number" name="total_questoes" value="<?php echo $info['total_questoes']; ?>" class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Vagas</label>
                            <input type="number" name="vagas" value="<?php echo $info['vagas']; ?>" class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Inscritos</label>
                            <input type="number" name="inscritos" value="<?php echo $info['inscritos']; ?>" class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none">
                        </div>
                    </div>
                </div>

                <button type="submit" name="update_cargo" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-black py-4 rounded-2xl transition shadow-xl shadow-indigo-500/20 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-save"></i> Salvar Alterações Gerais
                </button>
            </form>

            <div class="glass-panel rounded-3xl p-8 shadow-2xl">
                <h2 class="text-emerald-400 text-xs font-black uppercase tracking-widest mb-6">Mapeamento de Matérias</h2>
                
                <div class="overflow-x-auto mb-8">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] text-slate-500 uppercase font-bold border-b border-slate-700">
                                <th class="pb-3">Sigla</th>
                                <th class="pb-3">Matéria</th>
                                <th class="pb-3">Início</th>
                                <th class="pb-3">Fim</th>
                                <th class="pb-3 text-right">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php if (empty($materias)): ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-500 text-sm">Nenhuma matéria cadastrada.</td>
                                </tr>
                            <?php else: foreach ($materias as $m): ?>
                                <tr>
                                    <td class="py-4 font-bold text-white"><?php echo htmlspecialchars($m['sigla_materia']); ?></td>
                                    <td class="py-4 text-slate-400"><?php echo htmlspecialchars($m['nome_materia']); ?></td>
                                    <td class="py-4"><?php echo $m['questao_inicio']; ?></td>
                                    <td class="py-4"><?php echo $m['questao_fim']; ?></td>
                                    <td class="py-4 text-right">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="materia_id" value="<?php echo $m['id']; ?>">
                                            <?php echo csrfInput(); ?>
                                            <button type="submit" name="delete_materia" class="text-rose-500 hover:text-rose-400 transition">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <form method="POST" class="bg-slate-800/30 p-6 rounded-2xl border border-slate-700/50">
                    <h3 class="text-white font-bold mb-4 text-sm">Adicionar Matéria</h3>
                    <?php echo csrfInput(); ?>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-[9px] font-bold text-slate-500 uppercase mb-1">Nome da Matéria</label>
                            <input type="text" name="materia_nome" required placeholder="Ex: Língua Portuguesa" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white outline-none">
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-slate-500 uppercase mb-1">Sigla</label>
                            <input type="text" name="materia_sigla" required placeholder="Ex: L.P." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white outline-none">
                        </div>
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <label class="block text-[9px] font-bold text-slate-500 uppercase mb-1">Início</label>
                                <input type="number" name="materia_inicio" required placeholder="1" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white outline-none">
                            </div>
                            <div class="flex-1">
                                <label class="block text-[9px] font-bold text-slate-500 uppercase mb-1">Fim</label>
                                <input type="number" name="materia_fim" required placeholder="15" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white outline-none">
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_materia" class="mt-4 w-full bg-emerald-600/20 hover:bg-emerald-600 text-emerald-400 hover:text-white border border-emerald-600/30 font-bold py-2 rounded-xl transition text-xs uppercase tracking-widest">
                        Adicionar Matéria
                    </button>
                </form>
            </div>

            <div class="p-8 border border-rose-500/20 bg-rose-500/5 rounded-3xl">
                <h3 class="text-rose-400 font-bold mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i> Zona de Perigo
                </h3>
                <p class="text-xs text-slate-500 mb-6">Ao deletar este concurso, todos os rankings, cargos e notas de usuários associados serão permanentemente excluídos.</p>
                
                <form method="POST" onsubmit="return confirm('TEM CERTEZA? Isso apagará TODAS as notas de todos os usuários deste concurso!')">
                    <button type="submit" name="delete_concurso_confirm" class="bg-rose-500/10 hover:bg-rose-500 text-rose-500 hover:text-white px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition border border-rose-500/30">
                        Excluir Concurso Permanentemente
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
