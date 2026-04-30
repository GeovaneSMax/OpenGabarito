<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_logic.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/groq_api.php';
require_once __DIR__ . '/../includes/ui_helper.php';

$cargo_id = $_GET['cargo_id'] ?? null;
$slug = $_GET['slug'] ?? null;

if (!$cargo_id && !$slug) {
    header("Location: index.php");
    exit;
}

$modalidade_filtro = $_GET['modalidade'] ?? 'geral';

if (!in_array($modalidade_filtro, ['geral', 'ampla', 'pcd', 'ppp', 'hipossuficiente', 'indigena', 'trans', 'quilombola'])) {
    $modalidade_filtro = 'geral';
}

$ranking = [];
$info = null;

try {
    // 1. Fetch Cargo/Concurso Info
    if ($slug) {
        $stmt = $pdo->prepare("SELECT cg.*, c.nome_orgao, c.banca, c.status, c.image_url 
                               FROM cargos cg 
                               JOIN concursos c ON cg.concurso_id = c.id 
                               WHERE cg.slug = ? AND cg.deleted_at IS NULL AND c.deleted_at IS NULL");
        $stmt->execute([$slug]);
    } else {
        $stmt = $pdo->prepare("SELECT cg.*, c.nome_orgao, c.banca, c.status, c.image_url 
                               FROM cargos cg 
                               JOIN concursos c ON cg.concurso_id = c.id 
                               WHERE cg.id = ? AND cg.deleted_at IS NULL AND c.deleted_at IS NULL");
        $stmt->execute([$cargo_id]);
    }
    $info = $stmt->fetch();

    if (!$info) {
        header("Location: index.php");
        exit;
    }
    $cargo_id = $info['id'];
    $concurso_id = $info['concurso_id'];

    // Busca Top Moderadores deste concurso (Wiki)
    $stmt = $pdo->prepare("SELECT u.nome, u.trust_score, u.foto_perfil, COUNT(el.id) as contribuicoes
                           FROM edicoes_log el
                           JOIN usuarios u ON el.usuario_id = u.id
                           WHERE (el.tipo_objeto = 'cargo' AND el.objeto_id = ?) 
                              OR (el.tipo_objeto = 'concurso' AND el.objeto_id = ?)
                           GROUP BY u.id
                           ORDER BY contribuicoes DESC, u.trust_score DESC
                           LIMIT 3");
    $stmt->execute([$cargo_id, $concurso_id]);
    $moderadores_concurso = $stmt->fetchAll();

    // Média de confiabilidade da IA para estes dados
    $stmt = $pdo->prepare("SELECT AVG(score_ia) FROM edicoes_log WHERE objeto_id = ? AND tipo_objeto = 'cargo'");
    $stmt->execute([$cargo_id]);
    $confianca_wiki = round($stmt->fetchColumn() ?: 98);

    // Fetch Materias Mapping
    $stmt = $pdo->prepare("SELECT * FROM cargo_materias WHERE cargo_id = ? ORDER BY questao_inicio");
    $stmt->execute([$cargo_id]);
    $cargo_materias = $stmt->fetchAll();

    // 2. Fetch User Ranking (Filtered by Modality)
    $sql = "SELECT ru.*, u.nome, u.trust_score, u.foto_perfil,
            (SELECT COUNT(*) FROM respostas_usuarios ru2 WHERE ru2.cargo_id = ru.cargo_id AND ru2.versao = ru.versao AND ru2.deleted_at IS NULL) as amostras_versao
            FROM respostas_usuarios ru 
            JOIN usuarios u ON ru.usuario_id = u.id 
            WHERE ru.cargo_id = ? AND ru.deleted_at IS NULL";
    
    $params = [$cargo_id];
    
    if ($modalidade_filtro !== 'geral') {
        $sql .= " AND ru.modalidade = ?";
        $params[] = $modalidade_filtro;
    }
    
    // Ordenação Profissional: Primeiro os NÃO eliminados, depois por nota
    $sql .= " ORDER BY ru.status_eliminado ASC, ru.nota_estimada DESC, ru.criado_em ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ranking = $stmt->fetchAll();

    // Encontrar ranking do usuário logado
    $logged_in_user_rank = null;
    foreach ($ranking as $idx => $r) {
        if ($r['usuario_id'] == ($_SESSION['usuario_id'] ?? null)) {
            $logged_in_user_rank = $idx + 1;
            break;
        }
    }

    // 3. Advanced Stats & Dynamic AI Logic
    $quorum_minimo = 1; // Estimativas dinâmicas desde o primeiro participante!
    $total_participantes = count($ranking);
    $media_nota = $total_participantes > 0 ? array_sum(array_column($ranking, 'nota_estimada')) / $total_participantes : 0;
    
    // Cálculo de Desvio Padrão Global para Projeções
    $desvio_padrao = 0;
    if ($total_participantes > 0) {
        $soma_quadrados = 0;
        foreach($ranking as $r) {
            $soma_quadrados += pow($r['nota_estimada'] - $media_nota, 2);
        }
        $desvio_padrao = sqrt($soma_quadrados / $total_participantes);
    }
    
    // Simple projection for PNC (Possible Note of Corte)
    $vagas_finais = $info['vagas'] ?: 0;
    $vagas_2etapa = $vagas_finais * 5; // Assuming 5x for next stage

    $pnc_final = "--";
    $pnc_2etapa = "--";
    $quorum_atingido = ($total_participantes >= $quorum_minimo);

    if ($total_participantes > 0 && $info['inscritos'] > 0) {
        $sample_ratio = $total_participantes / $info['inscritos'];
        
        if ($total_participantes >= 10) {
            // Modelo de Projeção Linear (Para amostras estáveis)
            $pos_final_adj = floor($vagas_finais * $sample_ratio);
            $pnc_final = isset($ranking[$pos_final_adj]) ? $ranking[$pos_final_adj]['nota_estimada'] : ($ranking[count($ranking)-1]['nota_estimada'] ?? "--");
            
            $pos_2etapa_adj = floor($vagas_2etapa * $sample_ratio);
            $pnc_2etapa = isset($ranking[$pos_2etapa_adj]) ? $ranking[$pos_2etapa_adj]['nota_estimada'] : ($ranking[count($ranking)-1]['nota_estimada'] ?? "--");
        } else {
            // Modelo de Extrapolação IA/Estatística (Para amostras pequenas)
            // Assumimos que os primeiros a cadastrar são os mais dedicados (Top 20% da curva)
            $melhor_nota = $ranking[0]['nota_estimada'];
            $media_amostra = $media_nota;
            
            // Estimativa conservadora baseada na dispersão típica de concursos
            $pnc_final = round($melhor_nota * 0.85, 1); 
            $pnc_2etapa = round($melhor_nota * 0.75, 1);
        }

        $inscritos_reais = $info['inscritos'] * 0.7;
        $expansion_factor = ($total_participantes > 0) ? ($inscritos_reais * 0.15) / $total_participantes : 1;
        $nota_maxima = $info['total_questoes'];
        $score_onv_geral = ($nota_maxima > 0) ? ($media_nota / $nota_maxima) * 100 : 0;
        
        $meu_score_onv = 0;
        $minha_posicao_estimada = "--";
        
        if ($logged_in_user_rank) {
            $minha_nota = $ranking[$logged_in_user_rank - 1]['nota_estimada'];
            $meu_score_onv = ($minha_nota / $nota_maxima) * 100;
            
            if ($sample_ratio > 0) {
                $z_score = ($desvio_padrao > 0) ? ($minha_nota - $media_nota) / $desvio_padrao : 0;
                
                // 3. Projeção por Curva Sigmóide (Aproximação da Distribuição Normal)
                // Usamos uma logística para mapear o Z-Score em um percentil contínuo
                $percentil_estimado = 1 / (1 + exp(1.7 * $z_score)); 
                
                // 4. Ajuste de Elite: O ranking representa os 15% melhores
                $posicao_na_elite = $percentil_estimado * ($inscritos_reais * 0.15);
                
                if ($total_participantes < 100) {
                    $minha_posicao_estimada = floor($posicao_na_elite);
                } else {
                    $minha_posicao_estimada = floor($logged_in_user_rank / ($total_participantes / ($inscritos_reais * 0.15)));
                }
                
                // Garantir limites lógicos
                if ($minha_posicao_estimada < $logged_in_user_rank) $minha_posicao_estimada = $logged_in_user_rank;
                if ($minha_posicao_estimada > $inscritos_reais) $minha_posicao_estimada = floor($inscritos_reais);
            }
        }
    }
    
    // Análise de IA removida a pedido do usuário
    $ai_analysis = "";

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Dinâmico -->
    <title><?php echo htmlspecialchars($info['nome_cargo']); ?> | Ranking <?php echo htmlspecialchars($info['nome_orgao']); ?> - OpenGabarito</title>
    <meta name="description" content="Acompanhe em tempo real o ranking do concurso <?php echo htmlspecialchars($info['nome_orgao']); ?> para o cargo de <?php echo htmlspecialchars($info['nome_cargo']); ?>. Inteligência IA Groq aplicada.">
    <meta property="og:title" content="Ranking <?php echo htmlspecialchars($info['nome_orgao']); ?> | OpenGabarito">
    <meta property="og:description" content="Veja sua posição no cargo de <?php echo htmlspecialchars($info['nome_cargo']); ?>. Predição de nota de corte em tempo real.">
    <?php if (!empty($info['image_url'])): ?>
        <meta property="og:image" content="<?php echo $info['image_url']; ?>">
    <?php endif; ?>
    
    <link rel="icon" href="data:image/svg+xml,<?php echo rawurlencode(getLogoSVG(40)); ?>">
    
    <!-- External Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/ranking.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/toasts.js"></script>
</head>
<body class="bg-slate-50 text-slate-600 min-h-screen pb-20 overflow-x-hidden">
    
    <!-- Navbar -->
    <nav class="bg-white/98 backdrop-blur-md sticky top-0 z-50 mb-8 border-b border-slate-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
                            <a href="index.php" class="flex items-center gap-3 group">
                    <div class="h-10 w-10 group-hover:scale-110 transition-transform">
                        <?php echo getLogoSVG(40); ?>
                    </div>
                    <span class="font-bold text-xl tracking-tight text-slate-900">Open<span class="text-indigo-600">Gabarito</span></span>
                </a>
            
            <div class="flex items-center gap-2 sm:gap-6">
                <div class="hidden md:flex items-center gap-1">
                    <a href="index.php" class="text-slate-500 hover:text-slate-900 font-bold text-xs uppercase tracking-widest px-4 py-2 transition">Rankings</a>
                    <a href="transparencia.php" class="text-slate-500 hover:text-slate-900 font-bold text-xs uppercase tracking-widest px-4 py-2 transition flex items-center gap-2">
                        <i class="fa-solid fa-microchip text-[10px] text-indigo-500"></i> Transparência
                    </a>
                    <a href="minha_area.php" class="text-slate-500 hover:text-slate-900 font-bold text-xs uppercase tracking-widest px-4 py-2 transition">Minha Área</a>
                    <?php if (isAdmin()): ?>
                        <a href="admin/dashboard.php" class="text-rose-600 hover:text-rose-500 transition text-xs font-black uppercase tracking-widest px-4 py-2 flex items-center gap-2">
                            <i class="fa-solid fa-screwdriver-wrench"></i> Admin
                        </a>
                    <?php endif; ?>
                </div>
                <a href="logout.php" class="bg-red-500/10 text-red-600 hover:bg-red-500/20 px-3 sm:px-4 py-2 rounded-lg text-[10px] sm:text-xs font-bold transition">Sair</a>
                
                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="md:hidden text-slate-500 hover:text-slate-900 p-2">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Container -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-100 bg-white/95 backdrop-blur-xl shadow-lg">
            <div class="px-4 pt-2 pb-6 space-y-1">
                <a href="index.php" class="block px-3 py-4 text-base font-medium text-slate-900">Ranking Geral</a>
                <a href="minha_area.php" class="block px-3 py-4 text-base font-medium text-slate-600">Minha Área</a>
                <a href="transparencia.php" class="block px-3 py-4 text-base font-medium text-slate-600 flex items-center gap-2">
                    <i class="fa-solid fa-microchip text-indigo-500"></i> Transparência
                </a>
                <?php if (isAdmin()): ?>
                    <a href="admin/dashboard.php" class="block px-3 py-4 text-base font-black text-rose-600 flex items-center gap-2">
                        <i class="fa-solid fa-screwdriver-wrench"></i> Painel Admin
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <script>
        const menuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        
        function toggleMenu() {
            mobileMenu.classList.toggle('hidden');
            const icon = menuBtn.querySelector('i');
            if (mobileMenu.classList.contains('hidden')) {
                icon.classList.remove('fa-xmark');
                icon.classList.add('fa-bars');
            } else {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-xmark');
            }
        }
        
        if (menuBtn) {
            menuBtn.addEventListener('click', toggleMenu);
        }
    </script>

    <main class="max-w-7xl mx-auto px-4">
        
        <!-- Header Info -->
        <div class="mb-8 animate-fade-in px-2 md:px-0">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="flex items-center gap-4">
                    <?php if (!empty($info['image_url'])): ?>
                        <div class="w-16 h-16 rounded-2xl bg-white border border-slate-200 p-1 shrink-0 overflow-hidden shadow-lg">
                            <img src="<?php echo htmlspecialchars($info['image_url']); ?>" class="w-full h-full object-cover" alt="Logo">
                        </div>
                    <?php endif; ?>
                    <div>
                        <h1 class="text-3xl md:text-5xl font-black text-slate-900 mb-2 tracking-tight italic uppercase">
                            <?php echo e($info['nome_cargo']); ?>
                        </h1>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-lg border border-indigo-200 text-[10px] font-bold uppercase"><?php echo e($info['nome_orgao']); ?></span>
                            <span class="text-slate-300">•</span>
                            <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg border border-emerald-200 text-[10px] font-bold uppercase"><?php echo e($info['banca']); ?></span>
                            
                            <?php if (isAdmin()): ?>
                                <a href="admin/edit.php?cargo_id=<?php echo $cargo_id; ?>" class="bg-rose-100 hover:bg-rose-200 text-rose-600 border border-rose-200 px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest transition flex items-center gap-2">
                                    <i class="fa-solid fa-screwdriver-wrench"></i> Editar Ranking
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 w-full md:w-auto">
                    <a href="novo_gabarito.php?cargo_id=<?php echo $cargo_id; ?>" class="flex-1 md:flex-none bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-3 rounded-xl text-xs font-bold transition shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-pen-to-square"></i> <?php echo $logged_in_user_rank ? 'Editar Meu Gabarito' : 'Cadastrar Gabarito'; ?>
                    </a>
                    <button id="open-simulate" class="flex-1 md:flex-none bg-white hover:bg-slate-50 text-slate-900 px-4 py-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 border border-slate-200 shadow-sm">
                        <i class="fa-solid fa-rotate text-indigo-600"></i> Simular
                    </button>
                    <button class="flex-1 md:flex-none bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-3 rounded-xl text-xs font-bold transition shadow-lg shadow-indigo-500/20 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-share-nodes"></i> Compartilhar
                    </button>
                </div>
            </div>
        </div>

        <?php if (!$quorum_atingido): ?>
            <div class="mb-8 bg-amber-50 border border-amber-100 rounded-2xl p-6 flex items-center gap-6 animate-fade-in shadow-sm">
                <div class="w-16 h-16 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center text-2xl shrink-0">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <h3 class="text-slate-900 font-bold text-lg mb-1">Aguardando Quórum Mínimo</h3>
                    <p class="text-slate-500 text-sm leading-relaxed">
                        Precisamos de pelo menos <strong><?php echo $quorum_minimo; ?> participantes</strong> para gerar uma estimativa estatística confiável. 
                        Abaixo, você vê o progresso atual do ranking.
                    </p>
                    <div class="mt-4 bg-slate-100 h-2 w-full max-w-md rounded-full overflow-hidden">
                        <div class="bg-amber-500 h-full transition-all duration-1000" style="width: <?php echo min(100, ($total_participantes / $quorum_minimo) * 100); ?>%"></div>
                    </div>
                    <div class="mt-2 text-[10px] text-slate-400 uppercase font-bold tracking-widest">
                        Progresso: <?php echo $total_participantes; ?> / <?php echo $quorum_minimo; ?> candidatos
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Dashboard de Inteligência Estilo Olho na Vaga -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-10 px-2 md:px-0">
            <!-- Amostragem & Vagas -->
            <div class="bg-white border border-slate-200 rounded-2xl p-6 flex flex-col justify-between shadow-sm">
                <div>
                    <div class="flex items-center justify-between mb-6">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Amostragem</span>
                        <i class="fa-solid fa-users-viewfinder text-indigo-600"></i>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-500">Inscritos</span>
                            <span class="text-sm font-bold text-slate-900"><?php echo number_format($info['inscritos'] ?? 0, 0, ',', '.'); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-500">Participantes</span>
                            <span class="text-sm font-bold text-emerald-600"><?php echo number_format($total_participantes, 0, ',', '.'); ?></span>
                        </div>
                        <div class="flex justify-between items-center pt-3 border-t border-slate-100">
                            <span class="text-xs text-slate-500">Vagas 2ª Etapa</span>
                            <span class="text-sm font-bold text-indigo-600"><?php echo ($info['vagas_2etapa'] > 0) ? $info['vagas_2etapa'] : ($info['vagas'] * 3); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-500">Vagas Finais</span>
                            <span class="text-sm font-bold text-slate-900"><?php echo $info['vagas']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Projeção de Corte -->
            <div class="bg-white border border-slate-200 rounded-2xl p-6 border-l-4 border-indigo-600 shadow-sm">
                <div class="flex items-center justify-between mb-6">
                    <span class="text-[10px] text-indigo-600 font-bold uppercase tracking-widest">Possível Corte</span>
                    <i class="fa-solid fa-scissors text-indigo-600"></i>
                </div>
                <div class="space-y-6">
                    <div class="text-center">
                        <div class="text-3xl font-black text-slate-900"><?php echo $pnc_2etapa; ?></div>
                        <div class="text-[10px] text-slate-400 font-bold uppercase mt-1 tracking-tighter">Corte 2ª Etapa</div>
                    </div>
                    <div class="text-center pt-5 border-t border-slate-100">
                        <div class="text-3xl font-black text-emerald-600"><?php echo $pnc_final; ?></div>
                        <div class="text-[10px] text-slate-400 font-bold uppercase mt-1 tracking-tighter">Corte Final</div>
                    </div>
                </div>
            </div>

            <!-- Minha Performance -->
            <div class="bg-white border border-slate-200 rounded-2xl p-6 border-l-4 border-emerald-600 shadow-sm">
                <div class="flex items-center justify-between mb-6">
                    <span class="text-[10px] text-emerald-600 font-bold uppercase tracking-widest">Minha Performance</span>
                    <i class="fa-solid fa-chart-line text-emerald-600"></i>
                </div>
                <?php if ($logged_in_user_rank): ?>
                    <div class="space-y-5">
                        <div class="text-center">
                            <div class="text-4xl font-black text-slate-900">#<?php echo $minha_posicao_estimada; ?></div>
                            <div class="text-[10px] text-slate-400 font-bold uppercase mt-1">Classif. Estimada</div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 pt-5 border-t border-slate-100">
                            <div class="text-center">
                                <div class="text-lg font-bold text-emerald-600"><?php echo number_format($meu_score_onv, 2); ?>%</div>
                                <div class="text-[9px] text-slate-400 uppercase font-black">Meu Score</div>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-bold text-slate-500"><?php echo number_format($score_onv_geral, 2); ?>%</div>
                                <div class="text-[9px] text-slate-400 uppercase font-black">Score Geral</div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center h-full text-center py-4">
                        <i class="fa-solid fa-lock text-slate-200 text-2xl mb-3"></i>
                        <p class="text-[10px] text-slate-400 uppercase font-bold leading-tight mb-4">Envie seu gabarito para desbloquear suas projeções.</p>
                        <a href="novo_gabarito.php?cargo_id=<?php echo $cargo_id; ?>" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-2 rounded-xl text-[10px] font-black transition uppercase tracking-widest flex items-center justify-center gap-2">
                             <i class="fa-solid fa-plus"></i> Cadastrar Agora
                        </a>
                    </div>
                <?php endif; ?>
            </div>


            <!-- Comunidade Wiki -->
            <div class="bg-white border border-slate-200 rounded-2xl p-6 border-t-4 border-t-amber-500 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] text-amber-600 font-bold uppercase tracking-widest">Wiki Moderadores</span>
                    <i class="fa-solid fa-pen-nib text-amber-500"></i>
                </div>
                
                <div class="space-y-3 mb-6">
                    <?php if (empty($moderadores_concurso)): ?>
                        <div class="flex flex-col items-center justify-center py-2 text-center">
                            <i class="fa-solid fa-feather text-slate-700 text-lg mb-2"></i>
                            <p class="text-[9px] text-slate-500 uppercase font-black leading-tight">Ninguém editou ainda. Seja o primeiro!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($moderadores_concurso as $mod): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-slate-50 overflow-hidden flex items-center justify-center text-[10px] font-bold text-slate-400 border border-slate-200 uppercase">
                                        <?php if (!empty($mod['foto_perfil'])): ?>
                                            <img src="<?php echo $mod['foto_perfil']; ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <?php echo substr($mod['nome'] ?? '?', 0, 1); ?>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-[11px] font-medium text-slate-700 truncate w-24"><?php echo htmlspecialchars($mod['nome'] ?? 'Anônimo'); ?></span>
                                </div>
                                <span class="text-[9px] bg-amber-50 text-amber-600 px-1.5 py-0.5 rounded-full font-bold">+<?php echo $mod['contribuicoes']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="pt-4 border-t border-slate-100 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[9px] text-slate-400 font-bold uppercase">Confiabilidade IA</span>
                        <span class="text-[10px] font-black text-amber-600"><?php echo $confianca_wiki; ?>%</span>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-2">
                        <a href="colaborar.php?cargo_id=<?php echo $cargo_id; ?>" class="block w-full text-center bg-amber-50 hover:bg-amber-100 text-amber-600 text-[9px] font-black py-2 rounded-lg transition-all border border-amber-200 uppercase tracking-widest">
                            Editar Dados Wiki
                        </a>
                        <button onclick="toggleUpdateModal()" class="block w-full text-center bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-[9px] font-black py-2 rounded-lg transition-all border border-indigo-200 uppercase tracking-widest">
                            <i class="fa-solid fa-bullhorn mr-1"></i> Reportar Nomeações
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Atualização de Concurso -->
        <div id="modal-update-concurso" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="toggleUpdateModal()"></div>
            <div class="glass-panel w-full max-w-lg rounded-3xl p-8 relative animate-fade-in shadow-2xl border border-slate-200">
                <h2 class="text-2xl font-bold text-slate-900 mb-2 flex items-center gap-3">
                    <i class="fa-solid fa-clipboard-list text-indigo-400"></i> Atualizar Lista
                </h2>
                <p class="text-slate-500 mb-6 text-sm">Houve nomeações ou nova lista de convocados? Informe aqui para atualizarmos o ranking.</p>
                
                <form id="form-update-concurso">
                    <input type="hidden" name="concurso_id" value="<?php echo $info['concurso_id']; ?>">
                    <div class="mb-4">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Tipo de Atualização</label>
                        <select name="tipo" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-900 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                            <option value="nomeacao">Novas Nomeações</option>
                            <option value="lista_atualizada">Nova Lista de Convocados</option>
                            <option value="homologacao">Homologação do Concurso</option>
                            <option value="outro">Outras Informações</option>
                        </select>
                    </div>
                    <div class="mb-6">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Mensagem / Link Oficial</label>
                        <textarea name="mensagem" required placeholder="Ex: Saíram 10 novos nomeados no Diário Oficial de hoje. Link: ..." 
                                  class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-slate-900 text-sm focus:ring-2 focus:ring-indigo-500 outline-none h-32 transition"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-4 rounded-2xl transition shadow-lg shadow-indigo-500/20 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i> Enviar Atualização
                    </button>
                </form>
            </div>
        </div>

        <script>
            function toggleUpdateModal() {
                const modal = document.getElementById('modal-update-concurso');
                modal.classList.toggle('hidden');
            }

            document.getElementById('form-update-concurso').addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const btn = e.target.querySelector('button');
                
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin"></i> Enviando...';

                try {
                    const response = await fetch('api/salvar_sugestao.php', {
                        method: 'POST',
                        body: new URLSearchParams(formData)
                    });
                    const result = await response.json();
                    Toast.show(result.message, result.success ? "success" : "error");
                    if (result.success) {
                        e.target.reset();
                        toggleUpdateModal();
                    }
                } catch (err) {
                    Toast.show('Erro ao enviar. Tente novamente.', 'error');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar Atualização';
                }
            });
        </script>


        <!-- Ranking Section -->
        <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden animate-fade-in shadow-xl" style="animation-delay: 0.2s;">
            
            <!-- Table Controls -->
            <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50/50">
                <div class="flex gap-2 bg-slate-100 p-1 rounded-2xl border border-slate-200">
                    <a href="?cargo_id=<?php echo $cargo_id; ?>&modalidade=geral" class="tab-btn <?php echo $modalidade_filtro == 'geral' ? 'active' : ''; ?> text-[10px] uppercase tracking-wider px-4 py-2 rounded-xl transition font-black">Geral</a>
                    <a href="?cargo_id=<?php echo $cargo_id; ?>&modalidade=ampla" class="tab-btn <?php echo $modalidade_filtro == 'ampla' ? 'active' : ''; ?> text-[10px] uppercase tracking-wider px-4 py-2 rounded-xl transition font-black">Ampla</a>
                    <a href="?cargo_id=<?php echo $cargo_id; ?>&modalidade=pcd" class="tab-btn <?php echo $modalidade_filtro == 'pcd' ? 'active' : ''; ?> text-[10px] uppercase tracking-wider px-4 py-2 rounded-xl transition font-black">PCD</a>
                    <a href="?cargo_id=<?php echo $cargo_id; ?>&modalidade=ppp" class="tab-btn <?php echo $modalidade_filtro == 'ppp' ? 'active' : ''; ?> text-[10px] uppercase tracking-wider px-4 py-2 rounded-xl transition font-black">PPP</a>
                    <a href="?cargo_id=<?php echo $cargo_id; ?>&modalidade=hipossuficiente" class="tab-btn <?php echo $modalidade_filtro == 'hipossuficiente' ? 'active' : ''; ?> text-[10px] uppercase tracking-wider px-4 py-2 rounded-xl transition font-black">Hipo</a>
                    <a href="?cargo_id=<?php echo $cargo_id; ?>&modalidade=indigena" class="tab-btn <?php echo $modalidade_filtro == 'indigena' ? 'active' : ''; ?> text-[10px] uppercase tracking-wider px-4 py-2 rounded-xl transition font-black">IND</a>
                    <a href="?cargo_id=<?php echo $cargo_id; ?>&modalidade=trans" class="tab-btn <?php echo $modalidade_filtro == 'trans' ? 'active' : ''; ?> text-[10px] uppercase tracking-wider px-4 py-2 rounded-xl transition font-black">Trans</a>
                    <a href="?cargo_id=<?php echo $cargo_id; ?>&modalidade=quilombola" class="tab-btn <?php echo $modalidade_filtro == 'quilombola' ? 'active' : ''; ?> text-[10px] uppercase tracking-wider px-4 py-2 rounded-xl transition font-black">QUI</a>
                </div>
                <div class="relative w-full md:w-80">
                    <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" id="search-candidate" placeholder="Buscar candidato..." 
                           class="w-full bg-white border border-slate-200 rounded-xl pl-11 pr-4 py-3 text-sm text-slate-900 focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm">
                </div>
            </div>

            <div class="p-6">
                <button onclick="scrollToUser()" class="mb-6 w-full md:w-auto bg-slate-50 hover:bg-white border border-slate-200 text-slate-900 text-[10px] font-black py-4 px-8 rounded-2xl transition-all flex items-center justify-center gap-3 shadow-sm hover:shadow-indigo-500/10 active:scale-95 group">
                    <i class="fa-solid fa-location-crosshairs text-indigo-600 group-hover:rotate-90 transition-transform duration-500"></i> 
                    ENCONTRAR MINHA POSIÇÃO NO RANKING
                </button>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-separate border-spacing-y-2">
                        <thead>
                            <tr class="text-slate-400 text-[10px] uppercase tracking-widest font-black">
                                <th class="px-2 sm:px-4 py-2 text-center w-12">#</th>
                                <th class="px-2 sm:px-4 py-2">Candidato</th>
                                <th class="px-2 sm:px-4 py-2 text-center">Total</th>
                                <?php foreach ($cargo_materias as $m): ?>
                                    <th class="px-4 py-2 text-center min-w-[90px] cursor-help group" 
                                        onclick="Toast.show('Matéria: <?php echo addslashes($m['nome_materia']); ?>', 'info')"
                                        title="<?php echo htmlspecialchars($m['nome_materia']); ?>">
                                        <span class="text-[10px] border-b border-dotted border-slate-300 group-hover:text-indigo-600 transition-colors">
                                            <?php echo htmlspecialchars($m['sigla_materia']); ?>
                                        </span>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ranking)): ?>
                                <tr><td colspan="<?php echo 3 + count($cargo_materias); ?>" class="py-20 text-center text-slate-400 font-medium italic">Nenhum dado disponível ainda.</td></tr>
                            <?php else: foreach ($ranking as $idx => $r): 
                                $is_me = ($r['usuario_id'] == ($_SESSION['usuario_id'] ?? null));
                                $is_top = $idx < $vagas_finais;
                                $is_second = $idx >= $vagas_finais && $idx < $vagas_2etapa;
                            ?>
                                <tr id="<?php echo $is_me ? 'my-rank-row' : ''; ?>" class="group transition-all <?php echo $is_me ? 'bg-indigo-50 ring-1 ring-indigo-200' : 'bg-white hover:bg-slate-50'; ?> rounded-2xl overflow-hidden shadow-sm">
                                    <td class="px-2 sm:px-4 py-4 sm:py-6 text-center font-black rounded-l-2xl <?php echo ($idx < 3) ? 'text-amber-500' : 'text-slate-400'; ?>">
                                        <?php echo $idx + 1; ?>º
                                    </td>
                                    <td class="px-2 sm:px-4 py-4 sm:py-6">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-slate-50 border border-slate-200 overflow-hidden flex items-center justify-center text-[10px] font-black text-slate-400 group-hover:border-indigo-500 transition shadow-inner shrink-0">
                                                <?php if (!empty($r['foto_perfil'])): ?>
                                                    <img src="<?php echo $r['foto_perfil']; ?>" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($r['nome'], 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-sm font-bold text-slate-900 group-hover:text-indigo-600 transition truncate flex items-center gap-2">
                                                    <?php echo htmlspecialchars($r['nome']); ?>
                                                    <?php if (($r['trust_score'] ?? 0) >= 80): ?>
                                                        <span class="bg-amber-50 text-amber-600 text-[8px] px-1.5 py-0.5 rounded-full font-black uppercase tracking-widest border border-amber-200" title="Moderador Confiável">
                                                            <i class="fa-solid fa-shield-check"></i> Moderador
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="text-[8px] text-indigo-600/70 font-black uppercase tracking-widest">Versão <?php echo $r['versao']; ?></span>
                                                    <?php 
                                                        // Cálculo de Posição Estimada via Curva de Gauss (Z-Score)
                                                        $z_individual = ($desvio_padrao > 0) ? ($r['nota_estimada'] - $media_nota) / $desvio_padrao : 0;
                                                        $perc_individual = 1 / (1 + exp(1.7 * $z_individual)); 
                                                        $pos_est = floor($perc_individual * ($inscritos_reais * 0.15));
                                                        if ($pos_est < $idx + 1) $pos_est = $idx + 1;
                                                    ?>
                                                    <span class="text-[8px] text-emerald-600 font-black uppercase tracking-widest">Pos. Estimada: <?php echo $pos_est; ?>º</span>
                                                    <?php if ($r['amostras_versao'] < 5): ?>
                                                        <span class="text-[7px] bg-amber-50 text-amber-600 px-1.5 py-0.5 rounded border border-amber-200 font-black uppercase tracking-tighter" title="Poucos dados para esta versão. A nota pode oscilar.">Nota em Formação</span>
                                                    <?php endif; ?>
                                                    <?php if ($is_me): ?>
                                                        <span class="text-[7px] bg-indigo-500 text-white px-1 py-0.5 rounded-full font-black uppercase tracking-tighter">Você</span>
                                                    <?php endif; ?>
                                                    <?php if ($r['status_eliminado']): ?>
                                                        <span class="text-[7px] bg-rose-500 text-white px-1.5 py-0.5 rounded-full font-black uppercase tracking-tighter shadow-lg shadow-rose-500/20">ELIMINADO</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-2 sm:px-4 py-4 sm:py-6 text-center">
                                        <div class="text-base font-black <?php echo $is_top ? 'text-emerald-600' : 'text-indigo-600'; ?>">
                                            <?php echo number_format((float)($r['nota_estimada'] ?? 0), 1); ?>
                                        </div>
                                    </td>
                                    <?php 
                                        $resp_user = json_decode($r['respostas_json'], true);
                                        $desempenho = calcularDesempenhoMaterias($pdo, $cargo_id, $r['versao'], $resp_user);
                                        foreach ($desempenho as $d):
                                            $erros = $d['total'] - $d['acertos'];
                                            $perc = ($d['total'] > 0 ? ($d['acertos'] / $d['total']) * 100 : 0);
                                    ?>
                                        <td class="px-4 py-4 sm:py-6 text-center border-l border-slate-100">
                                            <div class="text-[11px] font-black text-slate-900 mb-1">
                                                <?php echo $d['acertos']; ?> <span class="text-[8px] text-slate-400 font-normal">pts</span>
                                            </div>
                                            <div class="flex items-center justify-center gap-1.5 text-[10px] font-bold">
                                                <span class="text-blue-400"><?php echo $d['acertos']; ?></span>
                                                <span class="text-slate-700 text-[8px]">|</span>
                                                <span class="text-red-500"><?php echo $erros; ?></span>
                                            </div>
                                            <div class="text-[9px] font-black mt-1.5 <?php echo $perc >= 50 ? 'text-blue-500' : 'text-red-500'; ?>">
                                                <?php echo round($perc, 0); ?>%
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

<?php echo getFooter(); ?>

    <!-- Simulation Modal -->
    <div id="simulate-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>
        <div class="bg-white border border-slate-200 w-full max-w-2xl rounded-3xl p-8 relative animate-fade-in overflow-hidden shadow-2xl">
            <div class="absolute top-0 right-0 p-4">
                <button id="close-simulate" class="text-slate-400 hover:text-slate-900 transition"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            
            <h2 class="text-2xl font-bold text-slate-900 mb-2">Simular Anulações</h2>
            <p class="text-slate-500 mb-8 text-sm">Selecione as questões que você acredita que serão anuladas para ver o impacto no ranking.</p>
            
            <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-3 max-h-60 overflow-y-auto mb-8 pr-2">
                <?php for($i=1; $i<=$info['total_questoes']; $i++): ?>
                    <label class="relative group cursor-pointer">
                        <input type="checkbox" class="null-q hidden" value="<?php echo $i; ?>">
                        <div class="bg-slate-50 border border-slate-200 p-2 rounded-lg text-center text-xs font-bold transition group-hover:border-indigo-500 peer-checked:bg-indigo-600 peer-checked:text-white text-slate-600">
                            <?php echo $i; ?>
                        </div>
                    </label>
                <?php endfor; ?>
            </div>
            
            <div class="flex justify-end gap-4">
                <button id="apply-simulation" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-4 rounded-2xl transition shadow-lg shadow-indigo-500/20">
                    Aplicar Simulação
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->

    <script src="assets/js/ranking.js"></script>
    <style>
        .peer-checked\:bg-indigo-600:checked + div {
            background-color: #6366f1;
            color: white;
            border-color: #6366f1;
        }
    </style>
    <!-- Modal de Sugestões -->
    <div id="modal-sugestao" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="toggleSugestao()"></div>
        <div class="bg-white border border-slate-200 w-full max-w-lg rounded-3xl p-8 relative animate-fade-in shadow-2xl">
            <h2 class="text-2xl font-bold text-slate-900 mb-2">Enviar Sugestão</h2>
            <p class="text-slate-500 mb-6 text-sm">Sua opinião é fundamental para melhorarmos o OpenGabarito.</p>
            
            <form id="form-sugestao">
                <textarea id="msg-sugestao" required placeholder="Digite sua sugestão ou feedback aqui..." 
                          class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-slate-900 text-sm focus:ring-2 focus:ring-indigo-500 outline-none h-40 transition mb-6"></textarea>
                
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-4 rounded-2xl transition shadow-lg shadow-indigo-500/20 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i> Enviar Feedback
                </button>
            </form>
        </div>
    </div>

    <!-- Botão de Sugestões Flutuante -->
    <button onclick="toggleSugestao()" class="fixed bottom-6 right-6 bg-emerald-600 hover:bg-emerald-500 text-white w-14 h-14 rounded-full flex items-center justify-center shadow-2xl shadow-emerald-500/40 transition-all hover:scale-110 z-[100] group">
        <i class="fa-solid fa-lightbulb text-xl"></i>
        <span class="absolute right-full mr-4 bg-white text-slate-900 border border-slate-200 text-xs px-3 py-2 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none shadow-xl">Enviar Sugestão</span>
    </button>

    <script>
        function toggleSugestao() {
            const modal = document.getElementById('modal-sugestao');
            modal.classList.toggle('hidden');
        }

        document.getElementById('form-sugestao').addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = document.getElementById('msg-sugestao').value;
            const btn = e.target.querySelector('button');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin"></i> Enviando...';

            try {
                const response = await fetch('api/salvar_sugestao.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `mensagem=${encodeURIComponent(msg)}`
                });
                const result = await response.json();
                alert(result.message);
                if (result.success) {
                    document.getElementById('msg-sugestao').value = '';
                    toggleSugestao();
                }
            } catch (err) {
                alert('Erro ao enviar sugestão. Tente novamente.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar Feedback';
            }
        });

        // Alerta de Sucesso Wiki
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('wiki_success')) {
                Toast.show('Wiki atualizada com sucesso! Seus dados foram salvos.', 'success');
                // Limpar a URL sem recarregar
                window.history.replaceState({}, document.title, window.location.pathname + "?cargo_id=<?php echo $cargo_id; ?>");
            }
        });
    </script>

</body>
</html>