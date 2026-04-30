<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once 'ai_logic.php';
require_once 'groq_api.php';
require_once __DIR__ . '/../../includes/ui_helper.php';

requireAdmin();

$cargo_id = $_GET['cargo_id'] ?? '';
$versao = $_GET['versao'] ?? 1;
$sucesso = "";
$erro = "";

if (!$cargo_id) die("Cargo ID necessário.");

// Buscar info do cargo
$stmt = $pdo->prepare("SELECT cg.*, c.nome_orgao FROM cargos cg JOIN concursos c ON cg.concurso_id = c.id WHERE cg.id = ?");
$stmt->execute([$cargo_id]);
$info = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_gabarito'])) {
    // CSRF
    validateCSRF();
    
    $respostas = $_POST['q']; // Array [1 => 'A', 2 => 'B'...]
    $respostas_json = json_encode($respostas);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO gabaritos_oficiais (cargo_id, versao, respostas_json) 
                               VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE respostas_json = VALUES(respostas_json)");
        $stmt->execute([$cargo_id, $versao, $respostas_json]);
        
        // RECALCULAR TODAS AS NOTAS DESTE CARGO AGORA
        $stmt = $pdo->prepare("SELECT id, respostas_json, versao FROM respostas_usuarios WHERE cargo_id = ? AND deleted_at IS NULL");
        $stmt->execute([$cargo_id]);
        $participantes = $stmt->fetchAll();
        
        $pdo->beginTransaction();
        foreach ($participantes as $p) {
            $resp_user = json_decode($p['respostas_json'], true);
            $nova_nota = calcularNotaEstimada($pdo, $cargo_id, $p['versao'], $resp_user);
            
            if ($nova_nota !== null) {
                $upd = $pdo->prepare("UPDATE respostas_usuarios SET nota_estimada = ? WHERE id = ?");
                $upd->execute([$nova_nota, $p['id']]);
            }
        }
        // Log Audit Trail
        logAction($pdo, "UPDATE_OFFICIAL_GABARITO", "gabaritos_oficiais", $cargo_id, ["versao" => $versao], $respostas);

        $pdo->commit();
        
        // Atualizar IA
        atualizarPredicoesIA($pdo, $cargo_id);
        
        $sucesso = "Gabarito Oficial V$versao salvo e todas as notas foram recalculadas!";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erro = "Erro ao salvar: " . $e->getMessage();
    }
}

// Buscar gabarito atual se existir
$stmt = $pdo->prepare("SELECT respostas_json FROM gabaritos_oficiais WHERE cargo_id = ? AND versao = ?");
$stmt->execute([$cargo_id, $versao]);
$gabarito_atual = json_decode($stmt->fetchColumn() ?: '[]', true);

?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <title>Gabarito Oficial | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body class="min-h-screen py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-10">
            <div>
                <h1 class="text-3xl font-black">Gabarito Oficial</h1>
                <p class="text-slate-400"><?php echo htmlspecialchars($info['nome_orgao'] . " - " . $info['nome_cargo']); ?></p>
            </div>
            <a href="edit.php?cargo_id=<?php echo $cargo_id; ?>" class="text-slate-400 hover:text-white transition">Voltar</a>
        </div>

        <?php if ($sucesso): ?>
            <div class="mb-8 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-2xl"><?php echo $sucesso; ?></div>
        <?php endif; ?>

        <div class="flex gap-2 mb-8">
            <?php for($v=1; $v<=4; $v++): ?>
                <a href="?cargo_id=<?php echo $cargo_id; ?>&versao=<?php echo $v; ?>" 
                   class="px-6 py-2 rounded-xl font-bold transition <?php echo $versao == $v ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-400'; ?>">
                    Versão <?php echo $v; ?>
                </a>
            <?php endfor; ?>
        </div>

        <form method="POST" class="glass-panel rounded-3xl p-8 shadow-2xl">
            <?php echo csrfInput(); ?>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <h3 class="text-white font-bold flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-indigo-400"></i> Inserir Respostas para Versão <?php echo $versao; ?>
                </h3>
                <a href="pdf_import.php?cargo_id=<?php echo $cargo_id; ?>" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition flex items-center gap-2">
                    <i class="fa-solid fa-file-pdf"></i> Importar via PDF (IA)
                </a>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                <?php for($i=1; $i<=$info['total_questoes']; $i++): ?>
                    <div class="flex flex-col gap-1">
                        <span class="text-[10px] text-slate-500 font-bold ml-1">Q<?php echo $i; ?></span>
                        <select name="q[<?php echo $i; ?>]" class="bg-slate-800 border border-slate-700 rounded-lg p-2 text-white outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="">-</option>
                            <?php foreach(['A','B','C','D','E','X'] as $alt): ?>
                                <option value="<?php echo $alt; ?>" <?php echo ($gabarito_atual[$i] ?? '') == $alt ? 'selected' : ''; ?>>
                                    <?php echo $alt == 'X' ? 'Anulada' : $alt; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endfor; ?>
            </div>

            <button type="submit" name="salvar_gabarito" class="w-full mt-10 bg-indigo-600 hover:bg-indigo-500 text-white font-black py-4 rounded-2xl transition shadow-xl">
                Salvar Gabarito Oficial e Atualizar Ranking
            </button>
        </form>
    </div>
</body>
</html>
