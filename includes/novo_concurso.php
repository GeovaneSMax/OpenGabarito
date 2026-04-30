<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui_helper.php';

$sucesso = "";
$erro = "";

// Lógica de salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_concurso'])) {
    $nome_orgao = trim($_POST['nome_orgao']);
    $banca = trim($_POST['banca']);
    $nome_cargo = trim($_POST['nome_cargo']);
    $total_questoes = intval($_POST['total_questoes']);
    $vagas = intval($_POST['vagas']);
    $inscritos = intval($_POST['inscritos']);
    $confirmado_duplicata = isset($_POST['confirmado_duplicata']) && $_POST['confirmado_duplicata'] == '1';

    if ($nome_orgao && $banca && $nome_cargo) {
        try {
            // Verificação de duplicidade no servidor (Double Check)
            $stmt = $pdo->prepare("SELECT id FROM concursos WHERE nome_orgao = ? AND banca = ?");
            $stmt->execute([$nome_orgao, $banca]);
            $concurso_existente = $stmt->fetch();

            if ($concurso_existente && !$confirmado_duplicata) {
                $erro = "Um concurso para este órgão e banca já existe. Por favor, confirme se é realmente um novo concurso.";
            } else {
                $pdo->beginTransaction();
                
                if ($concurso_existente) {
                    $concurso_id = $concurso_existente['id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO concursos (nome_orgao, banca, status, icon) VALUES (?, ?, 'aberto', 'fa-pen-to-square')");
                    $stmt->execute([$nome_orgao, $banca]);
                    $concurso_id = $pdo->lastInsertId();
                }

                $stmt = $pdo->prepare("INSERT INTO cargos (concurso_id, nome_cargo, total_questoes, vagas, inscritos) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$concurso_id, $nome_cargo, $total_questoes, $vagas, $inscritos]);
                
                $pdo->commit();
                $sucesso = "Concurso e Cargo criados com sucesso! Redirecionando...";
                header("Refresh: 2; url=../index.php");
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = "Erro ao criar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha todos os campos obrigatórios.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Concurso | OpenGabarito</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="ranking.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #f8fafc; font-family: 'Inter', sans-serif; }
        .glass-panel { background: rgba(30, 41, 59, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body class="min-h-screen flex flex-col pb-20">
    
    <nav class="border-b border-slate-800 bg-slate-900/50 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="../index.php" class="flex items-center gap-3 group">
                <div class="h-10 w-10 group-hover:scale-110 transition-transform">
                    <?php echo getLogoSVG(40); ?>
                </div>
                <span class="font-bold text-xl tracking-tight text-white">Open<span class="text-indigo-400">Gabarito</span></span>
            </a>
            <div class="flex items-center gap-4">
                <a href="../index.php" class="text-slate-400 hover:text-white text-sm font-medium transition">Voltar</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-2xl mx-auto px-4 py-12 w-full">
        <div class="text-center mb-10">
            <h1 class="text-3xl font-black text-white mb-2">Cadastrar Novo Concurso</h1>
            <p class="text-slate-400">Ajude a comunidade expandindo nossa base de dados.</p>
        </div>

        <?php if ($sucesso): ?>
            <div class="mb-8 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-2xl flex items-center gap-3 animate-fade-in">
                <i class="fa-solid fa-circle-check"></i> <?php echo $sucesso; ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mb-8 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-2xl flex items-center gap-3 animate-fade-in">
                <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="form-concurso" class="space-y-6">
            <div class="glass-panel rounded-3xl p-8 shadow-2xl">
                <div class="space-y-6">
                    <!-- Órgão -->
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Órgão / Instituição</label>
                        <input type="text" name="nome_orgao" id="nome_orgao" required placeholder="Ex: Polícia Federal, TRT 2, Banco do Brasil" 
                               class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-indigo-500 outline-none transition">
                    </div>

                    <!-- Banca -->
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Banca Examinadora</label>
                        <input type="text" name="banca" id="banca" required placeholder="Ex: CEBRASPE, FGV, VUNESP" 
                               class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-indigo-500 outline-none transition">
                    </div>

                    <!-- Check de Duplicidade (Invisível por padrão) -->
                    <div id="duplicidade-alerta" class="hidden bg-amber-500/10 border border-amber-500/20 p-4 rounded-xl space-y-3">
                        <p class="text-amber-400 text-xs font-bold flex items-center gap-2">
                            <i class="fa-solid fa-eye"></i> Encontramos concursos similares:
                        </p>
                        <div id="lista-similares" class="space-y-2">
                            <!-- JS preencherá aqui -->
                        </div>
                        <label class="flex items-center gap-3 cursor-pointer group mt-4">
                            <input type="checkbox" name="confirmado_duplicata" value="1" class="w-4 h-4 rounded border-slate-700 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs text-slate-300 group-hover:text-white transition">Sim, eu confirmo que este é um concurso DIFERENTE dos listados acima.</span>
                        </label>
                    </div>

                    <div class="border-t border-slate-700/50 pt-6 mt-6">
                        <h3 class="text-indigo-400 text-xs font-black uppercase tracking-widest mb-4">Informações do Cargo</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Nome do Cargo</label>
                                <input type="text" name="nome_cargo" required placeholder="Ex: Agente Administrativo" class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-2 text-white outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Total de Questões</label>
                                <input type="number" name="total_questoes" value="60" required class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-2 text-white outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Vagas Totais</label>
                                <input type="number" name="vagas" value="10" required class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-2 text-white outline-none">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Total de Inscritos (Estimado)</label>
                                <input type="number" name="inscritos" placeholder="Opcional" class="w-full bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-2 text-white outline-none">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" name="criar_concurso" class="w-full mt-10 bg-indigo-600 hover:bg-indigo-500 text-white font-black py-4 rounded-2xl transition shadow-xl shadow-indigo-500/20 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus-circle"></i> Criar Concurso e Iniciar Ranking
                </button>
            </div>
        </form>
    </main>

    <script>
        const orgaoInput = document.getElementById('nome_orgao');
        const bancaInput = document.getElementById('banca');
        const alertaDuplicidade = document.getElementById('duplicidade-alerta');
        const listaSimilares = document.getElementById('lista-similares');

        async function checkDuplicate() {
            const orgao = orgaoInput.value;
            const banca = bancaInput.value;

            if (orgao.length > 3) {
                try {
                    const response = await fetch(`api_concursos.php?search=${encodeURIComponent(orgao)}`);
                    const data = await response.json();

                    if (data.length > 0) {
                        alertaDuplicidade.classList.remove('hidden');
                        listaSimilares.innerHTML = data.map(c => `
                            <div class="flex items-center justify-between bg-slate-900/60 p-3 rounded-lg border border-slate-800">
                                <div>
                                    <div class="text-white text-xs font-bold">${c.nome_orgao}</div>
                                    <div class="text-[10px] text-slate-500">${c.banca} - ${c.status}</div>
                                </div>
                                <a href="../ranking.php?cargo_id=${c.cargo_id}" target="_blank" class="text-indigo-400 text-[10px] font-bold hover:underline">Ver Ranking</a>
                            </div>
                        `).join('');
                    } else {
                        alertaDuplicidade.classList.add('hidden');
                    }
                } catch (e) {}
            }
        }

        orgaoInput.addEventListener('blur', checkDuplicate);
        bancaInput.addEventListener('blur', checkDuplicate);
    </script>
</body>
</html>
