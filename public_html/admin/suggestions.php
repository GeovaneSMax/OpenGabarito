<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helper.php';

requireAdmin();

// Busca sugestões com os dados do usuário e concurso
$stmt = $pdo->query("SELECT s.*, u.nome as usuario_nome, u.email, u.foto_perfil, c.nome_orgao 
                     FROM sugestoes s 
                     JOIN usuarios u ON s.usuario_id = u.id 
                     LEFT JOIN concursos c ON s.concurso_id = c.id
                     ORDER BY s.criado_em DESC");
$sugestoes = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sugestões e Atualizações | OpenGabarito Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .badge { font-size: 8px; font-weight: 900; text-transform: uppercase; padding: 2px 6px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body class="min-h-screen py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-10">
            <div>
                <h1 class="text-4xl font-black text-white tracking-tight">Feedback & Listas</h1>
                <p class="text-slate-400">Sugestões gerais e atualizações de concursos</p>
            </div>
            <a href="dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-white px-6 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Painel Admin
            </a>
        </div>

        <div class="space-y-4">
            <?php if (empty($sugestoes)): ?>
                <div class="glass-panel rounded-3xl p-10 text-center">
                    <i class="fa-solid fa-face-meh text-5xl text-slate-600 mb-4"></i>
                    <p class="text-slate-500">Nenhuma sugestão ou atualização enviada.</p>
                </div>
            <?php else: foreach ($sugestoes as $s): 
                $tipo_labels = [
                    'geral' => ['label' => 'Sugestão Geral', 'color' => 'bg-slate-800 text-slate-400'],
                    'nomeacao' => ['label' => 'Nomeação', 'color' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'],
                    'lista_atualizada' => ['label' => 'Lista Atualizada', 'color' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20'],
                    'homologacao' => ['label' => 'Homologação', 'color' => 'bg-amber-500/10 text-amber-400 border-amber-500/20'],
                    'outro' => ['label' => 'Outro', 'color' => 'bg-slate-700 text-slate-300']
                ];
                $tipo_info = $tipo_labels[$s['tipo']] ?? $tipo_labels['outro'];
            ?>
                <div class="glass-panel rounded-3xl p-6 border-l-4 <?php echo ($s['tipo'] == 'geral' ? 'border-slate-700' : 'border-indigo-500'); ?> hover:scale-[1.01] transition-all">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-slate-800 border border-slate-700 overflow-hidden flex items-center justify-center text-xs font-black text-white uppercase">
                                <?php if (!empty($s['foto_perfil'])): ?>
                                    <img src="../<?php echo $s['foto_perfil']; ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?php echo substr($s['usuario_nome'], 0, 1); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-white flex items-center gap-2">
                                    <?php echo htmlspecialchars($s['usuario_nome']); ?>
                                    <span class="badge <?php echo $tipo_info['color']; ?>"><?php echo $tipo_info['label']; ?></span>
                                </div>
                                <div class="text-[10px] text-slate-500 uppercase font-black"><?php echo date('d/m/Y H:i', strtotime($s['criado_em'])); ?></div>
                            </div>
                        </div>

                        <?php if ($s['nome_orgao']): ?>
                            <div class="bg-indigo-500/5 px-4 py-2 rounded-xl border border-indigo-500/10 text-right">
                                <span class="text-[8px] text-indigo-400 font-black uppercase tracking-widest block">Concurso Relacionado</span>
                                <span class="text-xs font-bold text-white"><?php echo htmlspecialchars($s['nome_orgao']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-slate-900/50 rounded-2xl p-4 border border-slate-800 text-slate-300 text-sm leading-relaxed whitespace-pre-wrap">
                        <?php echo htmlspecialchars($s['mensagem']); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</body>
</html>
