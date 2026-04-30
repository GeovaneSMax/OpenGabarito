<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui_helper.php';
require_once __DIR__ . '/../includes/ai_logic.php';
require_once __DIR__ . '/../includes/groq_api.php';

requireLogin();

$cargo_id = $_GET['cargo_id'] ?? '';
$sucesso = "";
$erro = "";
$info = null;

// Busca dados se for edição
if ($cargo_id) {
    $stmt = $pdo->prepare("SELECT cg.*, c.nome_orgao, c.banca, c.status as c_status, c.data_prova, c.link_oficial 
                           FROM cargos cg 
                           JOIN concursos c ON cg.concurso_id = c.id 
                           WHERE cg.id = ? AND cg.deleted_at IS NULL");
    $stmt->execute([$cargo_id]);
    $info = $stmt->fetch();

    if ($info) {
        // Modalidades
        $stmt = $pdo->prepare("SELECT * FROM cargo_modalidades WHERE cargo_id = ?");
        $stmt->execute([$cargo_id]);
        $existing_mods = [];
        foreach ($stmt->fetchAll() as $row) {
            $existing_mods[$row['nome_modalidade']] = $row;
        }

        // Busca matérias
        $stmt = $pdo->prepare("SELECT * FROM cargo_materias WHERE cargo_id = ? ORDER BY questao_inicio");
        $stmt->execute([$cargo_id]);
        $cargo_materias = $stmt->fetchAll();

        // Histórico de Edições
        $stmt = $pdo->prepare("SELECT el.*, u.nome, u.foto_perfil 
                               FROM edicoes_log el 
                               JOIN usuarios u ON el.usuario_id = u.id 
                               WHERE el.objeto_id = ? AND el.tipo_objeto = 'cargo' 
                               ORDER BY el.criado_em DESC LIMIT 10");
        $stmt->execute([$cargo_id]);
        $historico = $stmt->fetchAll();

        // Top Colaboradores (Tier List)
        $stmt = $pdo->prepare("SELECT u.nome, u.foto_perfil, u.trust_score, COUNT(el.id) as total 
                               FROM edicoes_log el 
                               JOIN usuarios u ON el.usuario_id = u.id 
                               WHERE el.objeto_id = ? AND el.tipo_objeto = 'cargo' 
                               GROUP BY u.id 
                               ORDER BY total DESC, u.trust_score DESC LIMIT 5");
        // Gabaritos Colaborativos (Versões)
        $stmt = $pdo->prepare("SELECT gc.*, u.nome, u.foto_perfil, 
                               (SELECT COUNT(*) FROM votos_gabaritos WHERE gabarito_colab_id = gc.id) as upvotes
                               FROM gabaritos_colaborativos gc 
                               JOIN usuarios u ON gc.usuario_id = u.id 
                               WHERE gc.cargo_id = ? 
                               ORDER BY gc.criado_em DESC LIMIT 5");
        $stmt->execute([$cargo_id]);
        $versoes_gabarito = $stmt->fetchAll();

        // Buscar gabaritos oficiais (Oficiais)
        $gabaritos_oficiais = [];
        $stmt = $pdo->prepare("SELECT versao, respostas_json FROM gabaritos_oficiais WHERE cargo_id = ?");
        $stmt->execute([$cargo_id]);
        while ($row = $stmt->fetch()) {
            $gabaritos_oficiais[$row['versao']] = json_decode($row['respostas_json'], true);
        }
    }
}

// Processamento do POST (Simplificado para o Onboarding)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validateCSRF();
        $pdo->beginTransaction();
        
        $nome_orgao = $_POST['nome_orgao'] ?? '';
        $banca = $_POST['banca'] ?? '';
        $nome_cargo = $_POST['nome_cargo'] ?? '';
        $total_questoes = (int)$_POST['total_questoes'];
        
        // 1. Salvar/Atualizar Concurso
        $status = $_POST['status'] ?? 'aberto';
        $data_prova = !empty($_POST['data_prova']) ? $_POST['data_prova'] : null;
        $link_oficial = $_POST['link_oficial'] ?? '';

        if ($info) {
            $stmt = $pdo->prepare("UPDATE concursos SET nome_orgao = ?, banca = ?, data_prova = ?, link_oficial = ?, status = ? WHERE id = ?");
            $stmt->execute([$nome_orgao, $banca, $data_prova, $link_oficial, $status, $info['concurso_id']]);
            $concurso_id = $info['concurso_id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO concursos (nome_orgao, banca, data_prova, link_oficial, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nome_orgao, $banca, $data_prova, $link_oficial, $status]);
            $concurso_id = $pdo->lastInsertId();
        }

        // 2. Salvar/Atualizar Cargo
        $regras = [
            'tem_discursiva' => isset($_POST['tem_discursiva']) ? 1 : 0,
            'tem_titulos' => isset($_POST['tem_titulos']) ? 1 : 0,
            'pontos_negativos' => isset($_POST['pontos_negativos']) ? 1 : 0,
            'nota_padronizada' => isset($_POST['nota_padronizada']) ? 1 : 0,
            'por_genero' => isset($_POST['por_genero']) ? 1 : 0
        ];
        $nota_corte = !empty($_POST['nota_corte_oficial']) ? $_POST['nota_corte_oficial'] : null;

        if ($info) {
            $stmt = $pdo->prepare("UPDATE cargos SET nome_cargo = ?, total_questoes = ?, tem_discursiva = ?, tem_titulos = ?, pontos_negativos = ?, nota_padronizada = ?, por_genero = ?, nota_corte_oficial = ?, editado_por = ? WHERE id = ?");
            $stmt->execute([$nome_cargo, $total_questoes, $regras['tem_discursiva'], $regras['tem_titulos'], $regras['pontos_negativos'], $regras['nota_padronizada'], $regras['por_genero'], $nota_corte, $_SESSION['usuario_id'], $cargo_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cargos (concurso_id, nome_cargo, total_questoes, tem_discursiva, tem_titulos, pontos_negativos, nota_padronizada, por_genero, nota_corte_oficial, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$concurso_id, $nome_cargo, $total_questoes, $regras['tem_discursiva'], $regras['tem_titulos'], $regras['pontos_negativos'], $regras['nota_padronizada'], $regras['por_genero'], $nota_corte, $_SESSION['usuario_id']]);
            $cargo_id = $pdo->lastInsertId();
        }

        // 3. Salvar Modalidades (Cotas)
        $modalidades = ['ampla', 'pcd', 'ppp', 'hipossuficiente', 'indigena', 'trans', 'quilombola'];
        foreach ($modalidades as $mod) {
            $inscritos = (int)($_POST["inscritos_$mod"] ?? 0);
            $vagas = (int)($_POST["vagas_$mod"] ?? 0);
            $vagas_2e = (int)($_POST["v2e_$mod"] ?? 0);
            if ($inscritos > 0 || $vagas > 0) {
                $stmt = $pdo->prepare("INSERT INTO cargo_modalidades (cargo_id, nome_modalidade, inscritos, vagas, vagas_2etapa) 
                                       VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE 
                                       inscritos = VALUES(inscritos), vagas = VALUES(vagas), vagas_2etapa = VALUES(vagas_2etapa)");
                $stmt->execute([$cargo_id, $mod, $inscritos, $vagas, $vagas_2e]);
            }
        }

        // 4. Salvar Matérias
        if (isset($_POST['materia_nome']) && is_array($_POST['materia_nome'])) {
            // Limpa matérias antigas se for edição
            if ($info) {
                $pdo->prepare("DELETE FROM cargo_materias WHERE cargo_id = ?")->execute([$cargo_id]);
            }
            
            $stmt = $pdo->prepare("INSERT INTO cargo_materias (cargo_id, nome_materia, sigla_materia, questao_inicio, questao_fim, peso, minimo_acertos, usuario_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($_POST['materia_nome'] as $idx => $nome) {
                if (empty($nome)) continue;
                $sigla = $_POST['materia_sigla'][$idx] ?? substr($nome, 0, 3);
                $inicio = (int)$_POST['materia_inicio'][$idx];
                $fim = (int)$_POST['materia_fim'][$idx];
                $peso = (float)($_POST['materia_peso'][$idx] ?? 1.0);
                $minimo = (int)($_POST['materia_minimo'][$idx] ?? 0);
                
                $stmt->execute([$cargo_id, $nome, $sigla, $inicio, $fim, $peso, $minimo, $_SESSION['usuario_id']]);
            }
        }

        // 5. Salvar Gabarito Oficial (Com Camada de Proteção)
        if (!empty($_POST['gabarito_oficial_json'])) {
            $trust_score = $_SESSION['trust_score'] ?? 0;
            $is_trusted = ($trust_score >= 80 || isAdmin());
            
            if ($is_trusted) {
                // Usuário confiável: Aplica direto no banco oficial
                $all_versions = json_decode($_POST['gabarito_oficial_json'], true);
                if (is_array($all_versions)) {
                    foreach ($all_versions as $versao => $respostas) {
                        $res_json = json_encode($respostas);
                        $stmt = $pdo->prepare("INSERT INTO gabaritos_oficiais (cargo_id, versao, respostas_json) 
                                               VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE respostas_json = VALUES(respostas_json)");
                        $stmt->execute([$cargo_id, $versao, $res_json]);
                    }
                }
            } else {
                // Usuário comum: Salva apenas como sugestão no log para auditoria
                $status_gabarito = 'pendente_auditoria';
                // (Será salvo no edicoes_log no passo 7)
            }
        }

        // 6. Recalcular Ranking e Gabarito de Consenso (Skill: Live Updates)
        require_once __DIR__ . '/../includes/ai_logic.php';
        atualizarConsenso($pdo, $cargo_id);

        // 7. Log da Contribuição (Wiki)
        $trust_score = $_SESSION['trust_score'] ?? 0;
        $status_edicao = ($trust_score >= 80 || isAdmin()) ? 'aprovado' : 'pendente';
        $justificativa = $_POST['justificativa'] ?? '';

        $stmt = $pdo->prepare("INSERT INTO edicoes_log (usuario_id, tipo_objeto, objeto_id, dados_novos, score_ia, status, justificativa) 
                               VALUES (?, 'cargo', ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['usuario_id'], $cargo_id, json_encode($_POST), 100, $status_edicao, $justificativa]);

        $pdo->commit();
        
        $sucesso = "Wiki atualizada com sucesso!";
        if (isset($status_gabarito) && $status_gabarito === 'pendente_auditoria') {
            $sucesso = "Sua contribuição foi enviada! O gabarito oficial passará por auditoria da IA antes de atualizar os rankings.";
        }
        
        header("Location: ranking.php?cargo_id=$cargo_id&wiki_success=1&msg=" . urlencode($sucesso));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $erro = "Erro ao salvar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colaborar Wiki | OpenGabarito</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="assets/js/toasts.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;900&display=swap');
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; color: #0f172a; }
        .glass-panel { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(226, 232, 240, 0.8); }
        .section-card { margin-bottom: 2rem; background: white; border-radius: 2rem; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.5s ease-out forwards; }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
        .animate-shake { animation: shake 0.3s ease-in-out infinite; }
        .bg-mesh {
            background-image: radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.03) 0px, transparent 50%),
                              radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.03) 0px, transparent 50%);
        }
    </style>
    <style>
        .line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(99, 102, 241, 0.1); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(99, 102, 241, 0.3); }
    </style>
</head>
<body class="bg-mesh min-h-screen font-sans w-full overflow-x-hidden">

    <nav class="glass-panel sticky top-0 z-50 shadow-sm border-b-0">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2">
                <div class="h-8 w-8"><?php echo getLogoSVG(32); ?></div>
                <span class="font-black text-xl tracking-tighter text-slate-900">Open<span class="text-indigo-600">Gabarito</span></span>
            </a>
            <div class="text-[10px] font-black uppercase tracking-widest text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full border border-indigo-100">Edição Global Wiki</div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 py-8">
        <?php if (!empty($erro)): ?>
            <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 flex items-center gap-3">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span class="font-bold"><?php echo $erro; ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($sucesso)): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 flex items-center gap-3">
                <i class="fa-solid fa-check"></i>
                <span class="font-bold"><?php echo $sucesso; ?></span>
            </div>
        <?php endif; ?>
        <!-- Banner Motivacional: Comunidade e Assertividade -->
        <div class="mb-8 p-6 sm:p-8 rounded-[32px] bg-indigo-600 text-white shadow-xl shadow-indigo-500/20 relative overflow-hidden group border-none">
            <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:scale-110 transition-transform duration-700">
                <i class="fa-solid fa-users-rays text-7xl"></i>
            </div>
            <div class="relative z-10 flex flex-col md:flex-row items-center gap-4 md:gap-6">
                <div class="w-12 h-12 md:w-16 md:h-16 bg-white/20 rounded-2xl flex items-center justify-center text-2xl md:text-3xl text-white shadow-inner shrink-0">
                    <i class="fa-solid fa-heart-pulse animate-pulse"></i>
                </div>
                <div class="flex-grow text-center md:text-left">
                    <h3 class="text-lg md:text-xl font-black mb-1 leading-tight tracking-tight">O OpenGabarito é 100% Gratuito.</h3>
                    <p class="text-indigo-100 text-[10px] md:text-xs leading-relaxed max-w-xl font-medium">
                        Quanto mais pessoas usam e colaboram, mais <span class="text-white font-bold italic underline decoration-white/30">assertiva</span> se torna a nossa IA. Ajude a democratizar a informação: compartilhe este ranking com outros candidatos!
                    </p>
                </div>
                <div class="shrink-0 w-full md:w-auto">
                    <button type="button" onclick="shareWiki()" class="w-full md:w-auto px-6 py-4 bg-white text-indigo-600 font-black text-[10px] md:text-xs uppercase tracking-widest rounded-2xl hover:scale-105 transition-all shadow-xl flex items-center justify-center gap-2 border-none">
                        <i class="fa-solid fa-share-nodes"></i> Compartilhar
                    </button>
                </div>
            </div>
        </div>

        <script>
            function shareWiki() {
                const shareData = {
                    title: 'Colabore no OpenGabarito',
                    text: 'Ajude a completar os dados deste concurso no OpenGabarito!',
                    url: window.location.href
                };

                if (navigator.share) {
                    navigator.share(shareData).catch(err => console.log('Erro ao compartilhar', err));
                } else {
                    // Fallback: Copiar para o clipboard
                    navigator.clipboard.writeText(window.location.href).then(() => {
                        alert('Link copiado para a área de transferência!');
                    });
                }
            }
        </script>

        <form method="POST" id="onboarding-form" class="space-y-8">
            <?php echo csrfInput(); ?>
            
            <div id="global-editor" class="animate-fade-in space-y-8">
                <!-- Cabeçalho -->
                <div class="text-center">
                    <h1 class="text-4xl font-black text-slate-900 mb-2 tracking-tighter italic">WIKI<span class="text-indigo-600">MOD</span> EDITOR</h1>
                    <p class="text-slate-500 text-sm max-w-xl mx-auto font-medium">Colabore com a comunidade atualizando os dados deste concurso.</p>
                </div>

                <!-- Mágica IA (Centralizada) -->
                <div class="bg-white rounded-[32px] p-8 border-indigo-200 bg-indigo-50/30 border-2 border-dashed shadow-sm">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 bg-indigo-600 rounded-2xl flex items-center justify-center text-white text-2xl shadow-xl shadow-indigo-500/20 animate-pulse">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                            </div>
                            <div>
                                <h3 class="text-slate-900 font-black text-xl tracking-tight">Importar do Edital (IA)</h3>
                                <p class="text-slate-400 text-[10px] uppercase font-black tracking-widest mt-1">Extração automática de dados</p>
                            </div>
                        </div>
                        <button type="button" onclick="document.getElementById('edital-upload').click()" id="ai-import-btn" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-4 rounded-2xl text-xs font-black transition-all flex items-center justify-center gap-3 shadow-lg shadow-indigo-500/10">
                            <i class="fa-solid fa-file-pdf"></i> SELECIONAR PDF
                        </button>
                        <input type="file" id="edital-upload" accept="application/pdf" class="hidden" onchange="handleEditalUpload(this)">
                    </div>

                    <!-- Status da IA -->
                    <div id="ai-status-bar" class="hidden mt-8 bg-white rounded-2xl p-6 border-emerald-200 border border-dashed">
                        <div class="flex items-center justify-between mb-3">
                            <span id="ai-status-text" class="text-emerald-600 text-xs font-black uppercase tracking-widest">Iniciando processamento...</span>
                            <span id="ai-status-percent" class="text-emerald-600 text-xs font-black">0%</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                            <div id="ai-status-progress" class="bg-emerald-500 h-full w-0 transition-all duration-500 shadow-[0_0_15px_rgba(16,185,129,0.2)]"></div>
                        </div>
                    </div>
                </div>

                <!-- Community Hub: Tier List, Histórico e Versões -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 animate-fade-in">
                    <!-- Tier List (Colaboradores) -->
                    <div class="bg-white rounded-[32px] p-6 border border-amber-100 bg-amber-50/20 shadow-sm">
                        <h3 class="text-amber-600 text-[10px] font-black uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-trophy"></i> Top Colaboradores (Tier List)
                        </h3>
                        <div class="space-y-4">
                            <?php if (empty($colaboradores)): ?>
                                <p class="text-slate-400 text-[10px] italic font-medium">Nenhuma contribuição ainda.</p>
                            <?php else: foreach ($colaboradores as $idx => $c): ?>
                                <div class="flex items-center justify-between group">
                                    <div class="flex items-center gap-3">
                                        <div class="relative">
                                            <img src="<?php echo $c['foto_perfil'] ?: 'https://ui-avatars.com/api/?name='.urlencode($c['nome']); ?>" class="w-8 h-8 rounded-lg border border-slate-200">
                                            <div class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-white border border-slate-100 flex items-center justify-center text-[8px] font-black text-amber-600 shadow-sm"><?php echo $idx + 1; ?></div>
                                        </div>
                                        <div>
                                            <span class="block text-xs font-bold text-slate-900 group-hover:text-amber-600 transition-colors"><?php echo e($c['nome']); ?></span>
                                            <span class="text-[8px] text-slate-400 uppercase font-black"><?php echo $c['total']; ?> Edições</span>
                                        </div>
                                    </div>
                                    <div class="text-[10px] font-black text-amber-600/50 italic">TSR <?php echo $c['trust_score']; ?></div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- Histórico de Edições -->
                    <div class="bg-white rounded-[32px] p-6 border border-indigo-100 bg-indigo-50/20 shadow-sm">
                        <h3 class="text-indigo-600 text-[10px] font-black uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left"></i> Histórico de Versões
                        </h3>
                        <div class="space-y-4 max-h-[180px] overflow-y-auto pr-2 custom-scrollbar">
                            <?php if (empty($historico)): ?>
                                <p class="text-slate-400 text-[10px] italic font-medium">Sem histórico disponível.</p>
                            <?php else: foreach ($historico as $h): ?>
                                <div class="border-l-2 border-indigo-500/30 pl-4 py-2 hover:bg-white rounded-r-xl transition-colors cursor-help" title="<?php echo htmlspecialchars($h['justificativa'] ?: 'Sem justificativa'); ?>">
                                    <span class="block text-[10px] text-slate-900 font-bold"><?php echo e($h['nome']); ?></span>
                                    <span class="text-[8px] text-slate-400 uppercase font-black"><?php echo date('d/m H:i', strtotime($h['criado_em'])); ?> • <span class="text-indigo-600"><?php echo $h['status']; ?></span></span>
                                    <?php if($h['justificativa']): ?>
                                        <p class="text-[9px] text-slate-500 mt-1 italic line-clamp-1">"<?php echo e($h['justificativa']); ?>"</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- Versões de Gabarito -->
                    <div class="bg-white rounded-[32px] p-6 border border-emerald-100 bg-emerald-50/20 shadow-sm">
                        <h3 class="text-emerald-600 text-[10px] font-black uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-check-double"></i> Versões de Gabarito
                        </h3>
                        <div class="space-y-4">
                            <?php if (empty($versoes_gabarito)): ?>
                                <p class="text-slate-400 text-[10px] italic font-medium">Nenhuma versão sugerida.</p>
                            <?php else: foreach ($versoes_gabarito as $v): ?>
                                <div class="flex items-center justify-between p-3 bg-white rounded-xl border border-slate-100 hover:border-emerald-500/30 transition-all cursor-pointer shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center text-[10px] font-black">V<?php echo $v['versao_prova']; ?></div>
                                        <div>
                                            <span class="block text-[10px] text-slate-900 font-bold"><?php echo e($v['nome_fonte']); ?></span>
                                            <span class="text-[8px] text-slate-400 uppercase font-black"><?php echo $v['upvotes']; ?> Votos</span>
                                        </div>
                                    </div>
                                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-300"></i>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Seção 1: Identidade -->
                <!-- Aviso de Beta/IA -->
                <div class="mb-8 bg-amber-50 rounded-2xl p-6 border-amber-200 border border-dashed animate-pulse">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-amber-500 text-white rounded-2xl flex items-center justify-center text-xl shadow-lg shadow-amber-500/20">
                            <i class="fa-solid fa-flask-vial"></i>
                        </div>
                        <div>
                            <h4 class="text-amber-700 text-xs font-black uppercase tracking-widest">Recurso em Fase Beta</h4>
                            <p class="text-amber-600/80 text-[10px] leading-relaxed font-medium">A importação de editais via IA e a colaboração automatizada estão em testes. Podem ocorrer falhas na extração de matérias ou vagas. Revise sempre os dados gerados.</p>
                        </div>
                    </div>
                </div>

                <div class="section-card p-8">
                    <h2 class="text-2xl font-black text-slate-900 mb-6 flex items-center gap-4 tracking-tight">
                        <span class="w-10 h-10 bg-indigo-600 text-white rounded-2xl flex items-center justify-center text-sm shadow-xl shadow-indigo-500/20">01</span>
                        Identidade do Concurso
                    </h2>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1 tracking-widest">Órgão Público</label>
                                <input type="text" id="nome_orgao" name="nome_orgao" required value="<?php echo $info['nome_orgao'] ?? ''; ?>" onchange="checkDuplicates()" placeholder="Ex: Correios, INSS, Polícia Federal" class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm font-medium shadow-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1 tracking-widest">Banca Organizadora</label>
                                <input type="text" id="banca" name="banca" required value="<?php echo $info['banca'] ?? ''; ?>" onchange="checkDuplicates()" placeholder="Ex: Cebraspe, FGV, FCC" class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm font-medium shadow-sm">
                            </div>
                            <div class="md:col-span-2">
                                <div id="duplicate-alert" class="hidden mb-6 p-6 bg-indigo-50 border border-indigo-100 rounded-3xl animate-fade-in">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 bg-indigo-600 text-white rounded-2xl flex items-center justify-center text-sm shadow-lg shadow-indigo-500/20">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                        </div>
                                        <div class="flex-grow">
                                            <p class="text-sm text-slate-900 font-black tracking-tight">Concurso já cadastrado encontrado!</p>
                                            <p class="text-[11px] text-slate-500 font-medium">Encontramos dados que batem com este concurso. Deseja editar o existente ou continuar criando um novo?</p>
                                        </div>
                                        <button type="button" onclick="document.getElementById('duplicate-alert').classList.add('hidden')" class="px-4 py-2 bg-white hover:bg-slate-50 text-slate-600 text-[10px] font-black rounded-xl transition-all border border-slate-200 uppercase tracking-widest">
                                            Ignorar
                                        </button>
                                    </div>
                                    <div id="duplicate-matches" class="mt-4 space-y-3">
                                        <!-- Matches loop via JS -->
                                    </div>
                                </div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1 tracking-widest">Nome do Cargo</label>
                                <input type="text" id="nome_cargo" name="nome_cargo" required value="<?php echo $info['nome_cargo'] ?? ''; ?>" onchange="checkDuplicates()" placeholder="Ex: Técnico Administrativo" class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm font-medium shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1 tracking-widest">Status do Concurso</label>
                                <select name="status" class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm font-bold shadow-sm appearance-none">
                                    <option value="aberto" <?php echo ($info['c_status'] ?? '') == 'aberto' ? 'selected' : ''; ?>>Aberto (Recebendo notas)</option>
                                    <option value="consolidado" <?php echo ($info['c_status'] ?? '') == 'consolidado' ? 'selected' : ''; ?>>Consolidado (Encerrado)</option>
                                    <option value="aguardando" <?php echo ($info['c_status'] ?? '') == 'aguardando' ? 'selected' : ''; ?>>Aguardando Prova</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1 tracking-widest">Data da Prova</label>
                                <input type="date" name="data_prova" value="<?php echo $info['data_prova'] ?? ''; ?>" class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm font-bold shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1 tracking-widest">Corte Oficial</label>
                                <input type="number" step="0.01" name="nota_corte_oficial" value="<?php echo $info['nota_corte_oficial'] ?? ''; ?>" placeholder="Ex: 85.50" class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm font-bold shadow-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1 tracking-widest">Link Oficial do Edital</label>
                                <input type="url" name="link_oficial" value="<?php echo $info['link_oficial'] ?? ''; ?>" placeholder="https://..." class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm font-medium shadow-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção 2: Regras -->
                <div class="section-card p-8">
                    <h2 class="text-2xl font-black text-slate-900 mb-6 flex items-center gap-4 tracking-tight">
                        <span class="w-10 h-10 bg-indigo-600 text-white rounded-2xl flex items-center justify-center text-sm shadow-xl shadow-indigo-500/20">02</span>
                        Regras e Engenharia
                    </h2>
                    <div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="flex items-center gap-4 p-5 bg-white rounded-2xl border border-slate-100 hover:border-indigo-200 cursor-pointer transition-all group shadow-sm">
                                <input type="checkbox" name="tem_discursiva" <?php echo ($info['tem_discursiva'] ?? 0) ? 'checked' : ''; ?> class="w-6 h-6 rounded-lg border-slate-200 text-indigo-600 focus:ring-4 focus:ring-indigo-500/10 bg-slate-50 transition-all">
                                <div>
                                    <span class="block text-sm font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">Prova Discursiva?</span>
                                    <span class="text-[10px] text-slate-400 uppercase font-black tracking-widest">Redação, Estudo de Caso</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-4 p-5 bg-white rounded-2xl border border-slate-100 hover:border-rose-200 cursor-pointer transition-all group shadow-sm">
                                <input type="checkbox" name="pontos_negativos" <?php echo ($info['pontos_negativos'] ?? 0) ? 'checked' : ''; ?> class="w-6 h-6 rounded-lg border-slate-200 text-rose-600 focus:ring-4 focus:ring-rose-500/10 bg-slate-50 transition-all">
                                <div>
                                    <span class="block text-sm font-bold text-slate-900 group-hover:text-rose-600 transition-colors">Errada anula certa?</span>
                                    <span class="text-[10px] text-slate-400 uppercase font-black tracking-widest">Padrão Cebraspe</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-4 p-5 bg-white rounded-2xl border border-slate-100 hover:border-indigo-200 cursor-pointer transition-all group shadow-sm">
                                <input type="checkbox" name="tem_titulos" <?php echo ($info['tem_titulos'] ?? 0) ? 'checked' : ''; ?> class="w-6 h-6 rounded-lg border-slate-200 text-indigo-600 focus:ring-4 focus:ring-indigo-500/10 bg-slate-50 transition-all">
                                <div>
                                    <span class="block text-sm font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">Avaliação de Títulos?</span>
                                    <span class="text-[10px] text-slate-400 uppercase font-black tracking-widest">Mestrado, Doutorado</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-4 p-5 bg-white rounded-2xl border border-slate-100 hover:border-indigo-200 cursor-pointer transition-all group shadow-sm">
                                <input type="checkbox" name="por_genero" <?php echo ($info['por_genero'] ?? 0) ? 'checked' : ''; ?> class="w-6 h-6 rounded-lg border-slate-200 text-indigo-600 focus:ring-4 focus:ring-indigo-500/10 bg-slate-50 transition-all">
                                <div>
                                    <span class="block text-sm font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">Vagas por Gênero?</span>
                                    <span class="text-[10px] text-slate-400 uppercase font-black tracking-widest">Masc ♂ / Fem ♀</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-4 p-5 bg-white rounded-2xl border border-slate-100 hover:border-indigo-200 cursor-pointer transition-all group shadow-sm md:col-span-2">
                                <input type="checkbox" name="nota_padronizada" <?php echo ($info['nota_padronizada'] ?? 0) ? 'checked' : ''; ?> class="w-6 h-6 rounded-lg border-slate-200 text-indigo-600 focus:ring-4 focus:ring-indigo-500/10 bg-slate-50 transition-all">
                                <div>
                                    <span class="block text-sm font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">Nota Padronizada?</span>
                                    <span class="text-[10px] text-slate-400 uppercase font-black tracking-widest">Cálculo FCC / VUNESP (Média + Desvio)</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Seção 3: Vagas -->
                <div class="section-card p-8">
                    <h2 class="text-2xl font-black text-slate-900 mb-6 flex items-center gap-4 tracking-tight">
                        <span class="w-10 h-10 bg-indigo-600 text-white rounded-2xl flex items-center justify-center text-sm shadow-xl shadow-indigo-500/20">03</span>
                        Vagas e Inscritos
                    </h2>
                    <div class="bg-white rounded-[32px] border border-slate-100 overflow-hidden shadow-sm">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="text-[10px] font-black text-slate-400 uppercase border-b border-slate-50 bg-slate-50/50">
                                        <th class="py-5 px-6 tracking-widest">Modalidade</th>
                                        <th class="py-5 text-center tracking-widest">Inscritos</th>
                                        <th class="py-5 text-center tracking-widest">Vagas</th>
                                        <th class="py-5 text-center tracking-widest">Vagas 2ª Etapa</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php 
                                    $mods = [
                                        'ampla' => 'Ampla Concorrência',
                                        'pcd' => 'PCD',
                                        'ppp' => 'Pretos/Pardos',
                                        'hipossuficiente' => 'Hipossuficiente',
                                        'indigena' => 'Indígena',
                                        'trans' => 'Transexual',
                                        'quilombola' => 'Quilombola'
                                    ];
                                    foreach ($mods as $key => $label): 
                                    ?>
                                    <tr class="hover:bg-slate-50 transition-colors group">
                                        <td class="py-4 px-6 text-sm font-bold text-slate-700 group-hover:text-indigo-600"><?php echo $label; ?></td>
                                        <td class="py-4"><input type="number" name="inscritos_<?php echo $key; ?>" value="<?php echo $existing_mods[$key]['inscritos'] ?? ''; ?>" placeholder="0" class="w-24 mx-auto block bg-white border border-slate-100 rounded-xl py-2 px-3 text-center text-sm text-slate-900 font-bold focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none shadow-sm transition-all"></td>
                                        <td class="py-4"><input type="number" name="vagas_<?php echo $key; ?>" value="<?php echo $existing_mods[$key]['vagas'] ?? ''; ?>" placeholder="0" class="w-24 mx-auto block bg-white border border-slate-100 rounded-xl py-2 px-3 text-center text-sm text-slate-900 font-bold focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none shadow-sm transition-all"></td>
                                        <td class="py-4"><input type="number" name="v2e_<?php echo $key; ?>" value="<?php echo $existing_mods[$key]['vagas_2etapa'] ?? ''; ?>" placeholder="0" class="w-24 mx-auto block bg-white border border-slate-100 rounded-xl py-2 px-3 text-center text-sm text-slate-900 font-bold focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none shadow-sm transition-all"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Seção Especial: Gabarito Oficial (Estilo Admin) -->
                <div class="section-card p-8 bg-white border-2 border-emerald-100 shadow-xl shadow-emerald-500/5">
                    <h2 class="text-2xl font-black text-slate-900 mb-6 flex items-center gap-4 tracking-tight">
                        <span class="w-10 h-10 bg-emerald-500 text-white rounded-2xl flex items-center justify-center text-sm shadow-xl shadow-emerald-500/20">
                            <i class="fa-solid fa-file-invoice"></i>
                        </span>
                        Gabarito Oficial
                    </h2>
                    <div>
                        <div class="flex flex-col md:flex-row items-center justify-between gap-6 mb-8 pb-8 border-b border-slate-100">
                            <div>
                                <h3 class="text-slate-900 font-black tracking-tight text-lg">Importação via PDF ou Manual</h3>
                                <p class="text-slate-500 text-sm mt-1 font-medium">Insira as respostas oficiais para recalcular o ranking. Você pode adicionar quantas versões quiser.</p>
                                <?php if (($_SESSION['trust_score'] ?? 0) < 80 && !isAdmin()): ?>
                                    <div class="mt-4 flex items-center gap-3 text-[10px] text-indigo-600 font-black bg-indigo-50 px-4 py-2 rounded-xl border border-indigo-100 uppercase tracking-widest">
                                        <i class="fa-solid fa-shield-halved"></i> SUA EDIÇÃO PASSARÁ POR AUDITORIA DA IA
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-3">
                                <button type="button" onclick="document.getElementById('gabarito-upload').click()" id="ai-gabarito-btn" class="bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-3 rounded-2xl text-[10px] font-black transition-all flex items-center gap-2 shadow-lg shadow-emerald-500/10 uppercase tracking-widest">
                                    <i class="fa-solid fa-cloud-arrow-up"></i> IMPORTAR PDF (IA)
                                </button>
                                <button type="button" onclick="addGabaritoVersion()" class="bg-white hover:bg-slate-50 text-indigo-600 px-6 py-3 rounded-2xl text-[10px] font-black transition-all flex items-center gap-2 border border-slate-200 uppercase tracking-widest shadow-sm">
                                    <i class="fa-solid fa-plus text-indigo-600"></i> NOVA VERSÃO
                                </button>
                            </div>
                            <input type="file" id="gabarito-upload" accept="application/pdf" class="hidden" onchange="handleGabaritoUpload(this)">
                        </div>

                        <!-- Tabs de Versões -->
                        <div id="gabarito-tabs" class="flex gap-3 mb-8 overflow-x-auto pb-2 custom-scrollbar">
                            <!-- Gerado via JS -->
                        </div>

                        <div id="ai-gabarito-status" class="hidden mb-8 p-6 bg-slate-50 rounded-2xl border border-slate-200 border-dashed">
                            <div class="flex items-center gap-4">
                                <i class="fa-solid fa-circle-notch animate-spin text-emerald-500"></i>
                                <span class="text-sm text-slate-700 font-bold" id="gabarito-status-text">Lendo PDF...</span>
                            </div>
                        </div>

                        <div id="gabarito-container" class="space-y-6">
                            <!-- Grid de Respostas será gerado aqui para a versão ativa -->
                            <div id="respostas-grid-container" class="p-8 bg-white rounded-[32px] border border-slate-100 shadow-inner overflow-x-auto">
                                <div id="gabarito-respostas-grid" class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-8 lg:grid-cols-10 gap-4">
                                    <!-- Selects via JS -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dados estruturados para o POST -->
                        <input type="hidden" name="gabarito_oficial_json" id="gabarito-oficial-json">
                    </div>
                </div>

                <!-- Seção 4: Matérias -->
                <div class="section-card p-8">
                    <h2 class="text-2xl font-black text-slate-900 mb-6 flex items-center gap-4 tracking-tight">
                        <span class="w-10 h-10 bg-indigo-600 text-white rounded-2xl flex items-center justify-center text-sm shadow-xl shadow-indigo-500/20">04</span>
                        Engenharia da Prova
                    </h2>
                    <div class="space-y-8">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-3 ml-1 tracking-widest">Total de Questões da Prova</label>
                            <input type="number" name="total_questoes" required value="<?php echo $info['total_questoes'] ?? 60; ?>" class="w-full bg-white border border-slate-200 rounded-[32px] px-8 py-6 text-slate-900 text-4xl font-black focus:ring-8 focus:ring-indigo-500/5 focus:border-indigo-500 outline-none transition-all shadow-sm">
                        </div>

                        <div id="materias-container" class="space-y-6">
                            <div class="flex items-center justify-between">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Matérias cadastradas</p>
                                <button type="button" onclick="addMateriaRow()" class="text-indigo-600 hover:bg-indigo-600 hover:text-white text-[10px] font-black uppercase tracking-widest bg-indigo-50 px-5 py-3 rounded-2xl border border-indigo-100 transition-all shadow-sm">
                                    <i class="fa-solid fa-plus mr-1"></i> Adicionar Matéria
                                </button>
                            </div>
                            
                            <div id="materias-list" class="space-y-4">
                                <!-- JS Generated Rows -->
                            </div>

                            <div id="materias-empty" class="bg-slate-50 border-2 border-slate-100 p-12 rounded-[40px] text-center border-dashed">
                                <div class="text-slate-200 text-5xl mb-4"><i class="fa-solid fa-layer-group"></i></div>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">Nenhuma matéria cadastrada ainda.</p>
                            </div>
                        </div>

                        <div class="pt-8 space-y-4">
                            <label class="block">
                                <span class="text-[10px] font-black text-slate-400 uppercase mb-3 ml-1 block tracking-widest">Justificativa da Edição (Opcional)</span>
                                <textarea name="justificativa" rows="3" placeholder="Explique por que está sugerindo estas alterações (ex: Retificação do Edital nº 02...)" class="w-full bg-white border border-slate-200 rounded-[32px] px-8 py-6 text-slate-900 outline-none focus:ring-8 focus:ring-indigo-500/5 focus:border-indigo-500 transition-all text-sm font-medium shadow-sm"></textarea>
                            </label>
                            <p class="text-[10px] text-slate-400 font-medium italic ml-1">* Suas edições serão analisadas pela comunidade e pela IA. Usuários com alto Trust Score têm aprovação imediata.</p>
                        </div>

                        <div class="pt-8 border-t border-slate-100">
                            <input type="hidden" name="finalizar" value="1">
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black py-6 rounded-[32px] transition-all shadow-xl shadow-emerald-500/20 text-lg flex items-center justify-center gap-3 group uppercase tracking-widest">
                                <i class="fa-solid fa-cloud-arrow-up group-hover:scale-110 transition-transform"></i> Finalizar & Publicar Wiki
                            </button>
                            <p class="text-center text-[10px] text-indigo-600 mt-6 uppercase font-black tracking-widest flex items-center justify-center gap-2">
                                <i class="fa-solid fa-people-group"></i> A força da nossa Wiki é a sua participação.
                            </p>
                            <p class="text-center text-[10px] text-slate-400 mt-2 font-medium">É de graça hoje, amanhã e sempre. Quanto mais você ajuda, mais o OpenGabarito acerta para todos.</p>
                        </div>
                    </div>
                </div>

        </form>
    </main>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        async function handleEditalUpload(input) {
            const file = input.files[0];
            if (!file) return;

            const btn = document.getElementById('ai-import-btn');
            const statusBar = document.getElementById('ai-status-bar');
            const statusText = document.getElementById('ai-status-text');
            const statusPercent = document.getElementById('ai-status-percent');
            const statusProgress = document.getElementById('ai-status-progress');
            
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin"></i> PROCESSANDO...';
            
            statusBar.classList.remove('hidden');
            updateStatus(20, "Lendo PDF...");

            try {
                // 1. Extrair texto do PDF
                const reader = new FileReader();
                reader.onload = async function() {
                    try {
                        const typedarray = new Uint8Array(this.result);
                        const pdf = await pdfjsLib.getDocument(typedarray).promise;
                        let fullText = "";
                        
                        updateStatus(40, `Analisando ${pdf.numPages} páginas...`);

                        const maxPages = 50;
                        for (let i = 1; i <= Math.min(pdf.numPages, maxPages); i++) {
                            const page = await pdf.getPage(i);
                            const content = await page.getTextContent();
                            fullText += content.items.map(item => item.str).join(" ");
                        }
                        fullText = fullText.replace(/\s+/g, ' ').trim();
                        if (pdf.numPages > maxPages) {
                            // Pega as últimas 5 páginas também
                            for (let i = Math.max(maxPages + 1, pdf.numPages - 5); i <= pdf.numPages; i++) {
                                const page = await pdf.getPage(i);
                                const content = await page.getTextContent();
                                fullText += content.items.map(item => item.str).join(" ");
                            }
                        }
                        fullText = fullText.replace(/\s+/g, ' ').trim();

                        updateStatus(60, "IA: Extraindo dados estruturados...");

                        // 2. Enviar para API
                        const response = await fetch('api/api_ai_parse_edital.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ text: fullText })
                        });
                        
                        updateStatus(80, "IA: Validando informações...");
                        const res = await response.json();

                        if (res.success) {
                            updateStatus(100, "Concluído!");
                            fillForm(res.data);
                            Toast.show("Edital processado com sucesso!", "success");
                            setTimeout(() => statusBar.classList.add('hidden'), 3000);
                        } else {
                            const detail = res.details ? `\nDetalhes: ${res.details}` : '';
                            throw new Error(res.error + detail);
                        }
                    } catch (err) {
                        handleError(err);
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    }
                };
                reader.readAsArrayBuffer(file);
            } catch (err) {
                handleError(err);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }

            function updateStatus(pct, text) {
                statusProgress.style.width = pct + '%';
                statusPercent.innerText = pct + '%';
                statusText.innerText = text;
            }

            function handleError(err) {
                console.error(err);
                Toast.show(err.message || "Erro ao processar PDF.", "error");
                statusBar.classList.add('hidden');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        function fillForm(data) {
            const setVal = (name, val, isCheck = false) => {
                const el = document.getElementsByName(name)[0];
                if (el) {
                    if (isCheck) el.checked = !!val;
                    else el.value = val;
                }
            };

            // Passo 1
            if (data.nome_orgao) setVal('nome_orgao', data.nome_orgao);
            if (data.banca) setVal('banca', data.banca);
            if (data.nome_cargo) setVal('nome_cargo', data.nome_cargo);

            // Passo 2
            if (data.regras) {
                setVal('tem_discursiva', data.regras.tem_discursiva, true);
                setVal('pontos_negativos', data.regras.pontos_negativos, true);
                setVal('tem_titulos', data.regras.tem_titulos, true);
                setVal('por_genero', data.regras.por_genero, true);
                setVal('nota_padronizada', data.regras.nota_padronizada, true);
            }

            // Passo 4
            if (data.total_questoes) setVal('total_questoes', data.total_questoes);
            
            // Vagas (Cotas)
            if (data.vagas) {
                Object.keys(data.vagas).forEach(key => {
                    let fieldKey = key;
                    if (key === 'ppd') fieldKey = 'pcd'; // Mapeia PPD para PCD se a IA retornar assim
                    setVal(`vagas_${fieldKey}`, data.vagas[key]);
                });
            }
            
            // Indicação visual de campos preenchidos
            document.querySelectorAll('input').forEach(input => {
                if (input.value && input.type !== 'hidden' && input.type !== 'checkbox') {
                    input.classList.add('ring-2', 'ring-emerald-500/50');
                    setTimeout(() => input.classList.remove('ring-2', 'ring-emerald-500/50'), 3000);
                }
            });

            // Preencher Matérias (Passo 4)
                    data.materias.forEach(m => addMateriaRow(m));
                }
            }

            // Verificar Duplicidade após preenchimento da IA
            checkDuplicates();
        }

        function addMateriaRow(m = {}) {
            const list = document.getElementById('materias-list');
            document.getElementById('materias-empty').classList.add('hidden');
            
            const div = document.createElement('div');
            div.className = "bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm animate-fade-in";
            div.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <div class="md:col-span-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase mb-2 ml-1 block tracking-widest">Nome da Matéria</label>
                        <input type="text" name="materia_nome[]" value="${m.nome || ''}" required placeholder="Ex: Português" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-xs text-slate-900 font-bold focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none">
                    </div>
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase mb-2 ml-1 block tracking-widest">Sigla</label>
                        <input type="text" name="materia_sigla[]" value="${m.sigla || ''}" placeholder="PT" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-xs text-slate-900 font-bold focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none">
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="text-[9px] font-black text-slate-400 uppercase mb-2 block text-center tracking-widest">Início</label>
                            <input type="number" name="materia_inicio[]" value="${m.inicio || ''}" required class="w-full bg-white border border-slate-200 rounded-xl px-2 py-3 text-xs text-slate-900 font-bold focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none text-center">
                        </div>
                        <div class="flex-1">
                            <label class="text-[9px] font-black text-slate-400 uppercase mb-2 block text-center tracking-widest">Fim</label>
                            <input type="number" name="materia_fim[]" value="${m.fim || ''}" required class="w-full bg-white border border-slate-200 rounded-xl px-2 py-3 text-xs text-slate-900 font-bold focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none text-center">
                        </div>
                    </div>
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase mb-2 ml-1 block text-center tracking-widest">Peso</label>
                        <input type="number" step="0.1" name="materia_peso[]" value="${m.peso || '1.0'}" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-xs text-slate-900 font-bold focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none text-center">
                    </div>
                    <div class="flex items-end">
                        <button type="button" onclick="this.parentElement.parentElement.parentElement.remove()" class="w-full bg-rose-50 hover:bg-rose-500 text-rose-500 hover:text-white border border-rose-100 h-[46px] rounded-xl transition-all shadow-sm group">
                            <i class="fa-solid fa-trash-can group-hover:scale-110 transition-transform"></i>
                        </button>
                    </div>
                </div>
            `;
            list.appendChild(div);
        }i>
                        </button>
                    </div>
                </div>
            `;
            list.appendChild(div);
        }

        // Se for edição, carregar matérias existentes
        <?php if ($cargo_id && !empty($cargo_materias)): ?>
            document.addEventListener('DOMContentLoaded', () => {
                <?php foreach ($cargo_materias as $m): ?>
                    addMateriaRow({
                        nome: '<?php echo addslashes($m['nome_materia']); ?>',
                        sigla: '<?php echo addslashes($m['sigla_materia']); ?>',
                        inicio: <?php echo $m['questao_inicio']; ?>,
                        fim: <?php echo $m['questao_fim']; ?>,
                        peso: <?php echo $m['peso']; ?>
                    });
                <?php endforeach; ?>
            });
        <?php endif; ?>
        let gabaritoData = <?php echo json_encode($gabaritos_oficiais ?: new stdClass()); ?>; 
        let currentGabaritoVersion = 1;

        document.addEventListener('DOMContentLoaded', () => {
            renderGabaritoTabs();
            
            // Listener para total de questões
            const totalInput = document.querySelector('input[name="total_questoes"]');
            if (totalInput) {
                totalInput.addEventListener('input', () => {
                    renderGabaritoGrid();
                });
            }
        });

        function renderGabaritoTabs() {
            const tabs = document.getElementById('gabarito-tabs');
            const versions = Object.keys(gabaritoData).map(v => parseInt(v)).sort((a,b) => a-b);
            
            // Se não houver versões, cria a V1
            if (versions.length === 0) {
                gabaritoData[1] = {};
                versions.push(1);
            }

            tabs.innerHTML = versions.map(v => `
                <button type="button" onclick="switchGabaritoVersion(${v})" 
                        class="px-8 py-3 rounded-2xl font-black text-[10px] uppercase tracking-widest transition-all whitespace-nowrap shadow-sm border ${currentGabaritoVersion == v ? 'bg-indigo-600 text-white border-indigo-600 shadow-indigo-500/20' : 'bg-white text-slate-400 border-slate-100 hover:bg-slate-50'}">
                    Versão ${v}
                </button>
            `).join('');
            
            renderGabaritoGrid();
        }

        function renderGabaritoGrid() {
            const grid = document.getElementById('gabarito-respostas-grid');
            const total = parseInt(document.querySelector('input[name="total_questoes"]').value) || 0;
            const currentAnswers = gabaritoData[currentGabaritoVersion] || {};
            
            if (total === 0) {
                grid.innerHTML = '<div class="col-span-full text-center p-4 text-slate-500 italic text-xs">Defina o total de questões para liberar o gabarito.</div>';
                return;
            }

            let html = '';
            for (let i = 1; i <= total; i++) {
                const val = currentAnswers[i] || '';
                html += `
                    <div class="flex flex-col gap-2">
                        <span class="text-[10px] text-slate-400 font-black ml-1 tracking-tighter">Q${i}</span>
                        <select onchange="updateGabaritoAnswer(${i}, this.value)" class="bg-white border border-slate-100 rounded-xl p-3 text-sm text-slate-900 font-bold outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 shadow-sm transition-all appearance-none text-center">
                            <option value="">-</option>
                            ${['A','B','C','D','E','X'].map(alt => `
                                <option value="${alt}" ${val === alt ? 'selected' : ''}>${alt === 'X' ? 'ANUL' : alt}</option>
                            `).join('')}
                        </select>
                    </div>
                `;
            }
            grid.innerHTML = html;
            updateGabaritoJson();
        }

        function updateGabaritoAnswer(q, val) {
            if (!gabaritoData[currentGabaritoVersion]) gabaritoData[currentGabaritoVersion] = {};
            gabaritoData[currentGabaritoVersion][q] = val;
            updateGabaritoJson();
        }

        function addGabaritoVersion() {
            const versions = Object.keys(gabaritoData).map(v => parseInt(v));
            const nextV = versions.length > 0 ? Math.max(...versions) + 1 : 1;
            gabaritoData[nextV] = {};
            currentGabaritoVersion = nextV;
            renderGabaritoTabs();
        }

        function switchGabaritoVersion(v) {
            currentGabaritoVersion = v;
            renderGabaritoTabs();
        }

        function updateGabaritoJson() {
            document.getElementById('gabarito-oficial-json').value = JSON.stringify(gabaritoData);
        }

        // Listener para total de questões
        document.querySelector('input[name="total_questoes"]').addEventListener('input', () => {
            renderGabaritoGrid();
        });

        async function handleGabaritoUpload(input) {
            const file = input.files[0];
            if (!file) return;

            const statusBox = document.getElementById('ai-gabarito-status');
            const statusText = document.getElementById('gabarito-status-text');
            
            statusBox.classList.remove('hidden');
            
            try {
                const reader = new FileReader();
                reader.onload = async function() {
                    try {
                        const typedarray = new Uint8Array(this.result);
                        const pdf = await pdfjsLib.getDocument(typedarray).promise;
                        let fullText = "";
                        
                        for (let i = 1; i <= Math.min(pdf.numPages, 10); i++) {
                            const page = await pdf.getPage(i);
                            const content = await page.getTextContent();
                            fullText += content.items.map(item => item.str).join(" ");
                        }

                        statusText.innerText = "IA extraindo respostas...";

                        const response = await fetch('api/api_ai_parse_gabarito.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ text: fullText })
                        });
                        
                        const res = await response.json();
                        if (res.success) {
                            const versions = res.data; // Array de { version, answers }
                            versions.forEach(v => {
                                gabaritoData[v.version] = v.answers;
                            });
                            
                            currentGabaritoVersion = versions[0].version;
                            renderGabaritoTabs();
                            Toast.show(`${versions.length} versões extraídas!`, "success");
                        } else {
                            throw new Error(res.error || "IA não reconheceu o gabarito.");
                        }
                    } catch (err) {
                        Toast.show(err.message, "error");
                    } finally {
                        statusBox.classList.add('hidden');
                    }
                };
                reader.readAsArrayBuffer(file);
            } catch (err) {
                Toast.show("Erro ao ler arquivo.", "error");
                statusBox.classList.add('hidden');
            }
        }

        async function checkDuplicates() {
            const orgao = document.getElementById('nome_orgao').value;
            const banca = document.getElementById('banca').value;
            
            if (orgao.length < 3 || banca.length < 2) return;
            
            const alertBox = document.getElementById('duplicate-alert');
            const matchesBox = document.getElementById('duplicate-matches');
            
            try {
                const response = await fetch(`includes/api_check_duplicate.php?orgao=${encodeURIComponent(orgao)}&banca=${encodeURIComponent(banca)}`);
                const res = await response.json();
                
                if (res.exists && res.matches.length > 0) {
                    alertBox.classList.remove('hidden');
                    matchesBox.innerHTML = res.matches.map(m => `
                        <a href="colaborar.php?cargo_id=${m.cargo_id}" class="flex items-center justify-between p-4 bg-white hover:bg-indigo-50 rounded-[24px] border border-slate-100 transition-all group shadow-sm">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-[10px] font-black shadow-inner">WIKI</div>
                                <div>
                                    <span class="block text-sm font-black text-slate-900 tracking-tight">${m.nome_orgao}</span>
                                    <span class="text-[10px] text-indigo-600 font-black uppercase tracking-widest">${m.nome_cargo || 'Cargo Geral'} • ${m.banca}</span>
                                </div>
                            </div>
                            <span class="text-[10px] font-black text-indigo-400 uppercase tracking-widest group-hover:text-indigo-600 transition-colors">EDITAR EXISTENTE <i class="fa-solid fa-chevron-right ml-1"></i></span>
                        </a>
                    `).join('');
                } else {
                    alertBox.classList.add('hidden');
                }
            } catch (err) {
                console.error("Erro ao verificar duplicidade:", err);
            }
        }
    </script>

    <?php echo getFooter(); ?>
</body>
</html>
