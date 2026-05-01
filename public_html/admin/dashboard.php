<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

// Conta edições pendentes
$stmt_pendentes = $pdo->query("SELECT COUNT(*) FROM edicoes_log WHERE status = 'pendente'");
$total_pendentes = $stmt_pendentes->fetchColumn();

$concursos = $pdo->query("SELECT c.*, 
                         (SELECT COUNT(*) FROM cargos cg WHERE cg.concurso_id = c.id) as total_cargos,
                         (SELECT SUM((SELECT COUNT(*) FROM respostas_usuarios ru WHERE ru.cargo_id = cg2.id)) FROM cargos cg2 WHERE cg2.concurso_id = c.id) as total_participantes
                         FROM concursos c 
                         ORDER BY c.criado_em DESC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Geral Admin | OpenGabarito</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body class="min-h-screen py-12 px-4">
    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-10">
            <div>
                <h1 class="text-4xl font-black text-white tracking-tight">Painel de Controle</h1>
                <p class="text-slate-400">Gestão centralizada de concursos e rankings</p>
            </div>
            <div class="flex gap-4">
                <a href="review_edits.php" class="relative bg-amber-600 hover:bg-amber-500 text-white px-6 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved"></i> Revisar Wiki
                    <?php if ($total_pendentes > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-white text-amber-600 text-[10px] font-black w-5 h-5 rounded-full flex items-center justify-center shadow-lg animate-bounce">
                            <?php echo $total_pendentes; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="suggestions.php" class="bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2">
                    <i class="fa-solid fa-lightbulb"></i> Ver Sugestões
                </a>
                <a href="../index.php" class="bg-slate-800 hover:bg-slate-700 text-white px-6 py-2 rounded-xl text-sm font-bold transition">Voltar ao Site</a>
            </div>
        </div>


        <div class="grid grid-cols-1 gap-6">
            <?php foreach ($concursos as $c): ?>
                <div class="glass-panel rounded-3xl p-6 md:p-8 flex flex-col md:flex-row justify-between items-center gap-8">
                    <div class="flex items-center gap-6 w-full md:w-auto">
                        <div class="h-16 w-16 rounded-2xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 flex items-center justify-center text-2xl shrink-0">
                            <i class="fa-solid <?php echo $c['icon']; ?>"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-white"><?php echo htmlspecialchars($c['nome_orgao']); ?></h2>
                            <p class="text-sm text-slate-500 font-medium"><?php echo htmlspecialchars($c['banca']); ?> • <?php echo $c['total_cargos']; ?> Cargos</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 w-full md:w-auto justify-end">
                        <?php 
                        $cargos = $pdo->prepare("SELECT id, nome_cargo FROM cargos WHERE concurso_id = ?");
                        $cargos->execute([$c['id']]);
                        foreach ($cargos->fetchAll() as $cargo):
                        ?>
                            <div class="flex flex-col gap-2 bg-slate-900/50 p-4 rounded-2xl border border-slate-800">
                                <span class="text-xs font-bold text-slate-300"><?php echo htmlspecialchars($cargo['nome_cargo']); ?></span>
                                <div class="flex gap-2">
                                    <a href="edit.php?cargo_id=<?php echo $cargo['id']; ?>" class="text-[10px] bg-slate-800 hover:bg-slate-700 text-white px-3 py-1 rounded-lg font-bold transition">Editar</a>
                                    <a href="../ranking.php?cargo_id=<?php echo $cargo['id']; ?>" target="_blank" class="text-[10px] text-slate-500 hover:text-white transition py-1">Ver</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
