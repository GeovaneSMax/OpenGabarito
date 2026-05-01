<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helper.php';

requireAdmin();

// Busca edições pendentes
$stmt = $pdo->query("SELECT el.*, u.nome as usuario_nome, u.email, u.trust_score 
                     FROM edicoes_log el 
                     JOIN usuarios u ON el.usuario_id = u.id 
                     WHERE el.status = 'pendente' 
                     ORDER BY el.criado_em DESC");
$pendentes = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <title>Moderação Wiki | OpenGabarito Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .diff-added { background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 2px 4px; border-radius: 4px; }
        .diff-removed { background: rgba(244, 63, 94, 0.1); color: #f43f5e; padding: 2px 4px; border-radius: 4px; text-decoration: line-through; }
    </style>
</head>
<body class="min-h-screen py-12 px-4">
    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-10">
            <div>
                <h1 class="text-4xl font-black text-white tracking-tight">Fila de Moderação</h1>
                <p class="text-slate-400">Edições da Wiki aguardando revisão manual</p>
            </div>
            <a href="dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-white px-6 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Painel Admin
            </a>
        </div>

        <?php if (empty($pendentes)): ?>
            <div class="glass-panel rounded-3xl p-20 text-center">
                <i class="fa-solid fa-circle-check text-6xl text-emerald-500 mb-6"></i>
                <h2 class="text-2xl font-bold text-white mb-2">Tudo limpo!</h2>
                <p class="text-slate-500">Não há edições pendentes para revisão no momento.</p>
            </div>
        <?php else: foreach ($pendentes as $p): 
            $antigos = json_decode($p['dados_anteriores'], true);
            $novos = json_decode($p['dados_novos'], true);
        ?>
            <div class="glass-panel rounded-3xl p-8 mb-8 border-l-4 border-amber-500">
                <div class="flex flex-col md:flex-row justify-between gap-8">
                    <div class="flex-grow">
                        <!-- Cabeçalho do Colaborador -->
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 rounded-full bg-indigo-500 flex items-center justify-center text-white font-black">
                                <?php echo substr($p['usuario_nome'], 0, 1); ?>
                            </div>
                            <div>
                                <div class="text-lg font-bold text-white"><?php echo htmlspecialchars($p['usuario_nome']); ?></div>
                                <div class="text-xs text-slate-400 font-medium">
                                    Trust Score: <span class="text-amber-500 font-bold"><?php echo $p['trust_score']; ?></span> • 
                                    IA Confidence: <span class="text-emerald-500 font-bold"><?php echo $p['score_ia']; ?>%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Comparativo -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-900/50 rounded-2xl p-6 border border-slate-800">
                            <div>
                                <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4">Dados Atuais no Site</h4>
                                <div class="space-y-2 text-sm">
                                    <p><span class="text-slate-500">Órgão:</span> <?php echo htmlspecialchars($antigos['nome_orgao'] ?? 'N/A'); ?></p>
                                    <p><span class="text-slate-500">Banca:</span> <?php echo htmlspecialchars($antigos['banca'] ?? 'N/A'); ?></p>
                                    <p><span class="text-slate-500">Cargo:</span> <?php echo htmlspecialchars($antigos['nome_cargo'] ?? 'N/A'); ?></p>
                                    <p><span class="text-slate-500">Questões:</span> <?php echo $antigos['total_questoes'] ?? 'N/A'; ?></p>
                                </div>
                            </div>
                            <div class="border-l border-slate-800 pl-6">
                                <h4 class="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-4">Proposta de Alteração</h4>
                                <div class="space-y-2 text-sm">
                                    <p><span class="text-slate-500">Órgão:</span> <span class="<?php echo ($novos['nome_orgao'] != ($antigos['nome_orgao'] ?? '')) ? 'diff-added' : ''; ?>"><?php echo htmlspecialchars($novos['nome_orgao']); ?></span></p>
                                    <p><span class="text-slate-500">Banca:</span> <span class="<?php echo ($novos['banca'] != ($antigos['banca'] ?? '')) ? 'diff-added' : ''; ?>"><?php echo htmlspecialchars($novos['banca']); ?></span></p>
                                    <p><span class="text-slate-500">Cargo:</span> <span class="<?php echo ($novos['nome_cargo'] != ($antigos['nome_cargo'] ?? '')) ? 'diff-added' : ''; ?>"><?php echo htmlspecialchars($novos['nome_cargo']); ?></span></p>
                                    <p><span class="text-slate-500">Questões:</span> <span class="<?php echo ($novos['total_questoes'] != ($antigos['total_questoes'] ?? '')) ? 'diff-added' : ''; ?>"><?php echo $novos['total_questoes']; ?></span></p>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($p['justificativa'])): ?>
                            <div class="mt-4 text-xs text-slate-400 italic">
                                <strong>Justificativa do usuário:</strong> "<?php echo htmlspecialchars($p['justificativa']); ?>"
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Ações -->
                    <div class="flex flex-col gap-3 shrink-0 justify-center">
                        <form action="approve_edit.php" method="POST">
                            <input type="hidden" name="log_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" name="acao" value="aprovar" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white px-8 py-3 rounded-xl font-bold transition flex items-center justify-center gap-2">
                                <i class="fa-solid fa-check"></i> Aprovar
                            </button>
                        </form>
                        <form action="approve_edit.php" method="POST">
                            <input type="hidden" name="log_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" name="acao" value="rejeitar" class="w-full bg-rose-600/10 hover:bg-rose-600/20 text-rose-500 px-8 py-3 rounded-xl font-bold transition border border-rose-500/20 flex items-center justify-center gap-2">
                                <i class="fa-solid fa-xmark"></i> Rejeitar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</body>
</html>
