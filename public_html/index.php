<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/groq_api.php';
require_once __DIR__ . '/../includes/ui_helper.php';
require_once __DIR__ . '/../includes/auth.php';

// Estatísticas
$totalGabaritos = 0;
$concursos = [];
try {
    $totalGabaritos = $pdo->query("SELECT COUNT(*) FROM respostas_usuarios")->fetchColumn();
    $aiAccuracy = $pdo->query("SELECT accuracy FROM site_stats WHERE id = 1")->fetchColumn() ?: 98.2;
    // Busca os concursos ativos
    $stmt = $pdo->query("SELECT c.*, cg.id as cargo_id, cg.nome_cargo, cg.total_questoes, cg.pnc_ia, cg.vagas, cg.inscritos,
                        (SELECT COUNT(*) FROM respostas_usuarios ru WHERE ru.cargo_id = cg.id AND ru.deleted_at IS NULL) as total_amostras,
                        (SELECT MAX(nota_estimada) FROM respostas_usuarios ru WHERE ru.cargo_id = cg.id AND ru.deleted_at IS NULL) as nota_maxima
                        FROM concursos c 
                        JOIN cargos cg ON c.id = cg.concurso_id 
                        WHERE c.deleted_at IS NULL AND cg.deleted_at IS NULL
                        ORDER BY total_amostras DESC, c.criado_em DESC");
    $concursos = $stmt->fetchAll();
    
    // Busca Top Moderadores (Tier List)
    $stmt_mods = $pdo->query("SELECT id, nome, trust_score, foto_perfil FROM usuarios WHERE trust_score > 0 ORDER BY trust_score DESC LIMIT 5");
    $top_mods = $stmt_mods->fetchAll();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open Gabarito | Inteligência Coletiva para Concursos</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="OpenGabarito: A maior plataforma colaborativa de rankings e notas de corte para concursos públicos no Brasil. Use o poder da IA para prever seu resultado gratuitamente.">
    <meta name="keywords" content="concurso público, ranking concurso, nota de corte, gabarito preliminar, simulador de nota, inteligência artificial, opengabarito">
    <meta name="author" content="OpenGabarito">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://opengabarito.com.br/">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://opengabarito.com.br/">
    <meta property="og:title" content="Open Gabarito | Rankings e Notas de Corte com IA">
    <meta property="og:description" content="Descubra sua posição no ranking e a nota de corte estimada com inteligência artificial. 100% gratuito e colaborativo.">
    <meta property="og:image" content="https://opengabarito.com.br/assets/og-image.png">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://opengabarito.com.br/">
    <meta property="twitter:title" content="Open Gabarito | Rankings e Notas de Corte com IA">
    <meta property="twitter:description" content="Descubra sua posição no ranking e a nota de corte estimada com inteligência artificial. 100% gratuito e colaborativo.">
    <meta property="twitter:image" content="https://opengabarito.com.br/assets/og-image.png">

    <!-- Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "OpenGabarito",
      "url": "https://opengabarito.com.br/",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "https://opengabarito.com.br/index.php?q={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    }
    </script>

    <link rel="icon" href="data:image/svg+xml,<?php echo rawurlencode(getLogoSVG(40)); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5' }, // Indigo
                        success: { 400: '#34d399', 500: '#10b981', 600: '#059669' }, // Emerald
                        slate: { 50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b', 600: '#475569', 700: '#334155', 800: '#1e293b', 900: '#0f172a' }
                    },
                    fontFamily: {
                        sans: ['Outfit', 'Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    borderRadius: {
                        '4xl': '2rem',
                        '5xl': '2.5rem',
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <script src="assets/js/toasts.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        html { scroll-behavior: smooth; }
        body { font-family: 'Outfit', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        html, body { max-width: 100vw; overflow-x: hidden; }
        .glass-panel { background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(226, 232, 240, 0.8); }
        .mesh-gradient { background-color: #ffffff; background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,0) 0, hsla(253,16%,7%,0) 50%), radial-gradient(at 50% 0%, hsla(225,39%,30%,0) 0, hsla(225,39%,30%,0) 50%), radial-gradient(at 100% 0%, hsla(339,49%,30%,0) 0, hsla(339,49%,30%,0) 50%); }
    </style>
</head>
<body class="bg-slate-50 text-slate-600 antialiased min-h-screen flex flex-col selection:bg-primary-500 selection:text-white w-full overflow-x-hidden">

    <nav class="glass-panel sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="index.php" class="flex items-center gap-4 group">
                    <div class="h-12 w-12 group-hover:scale-110 transition-all duration-500">
                        <?php echo getLogoSVG(48); ?>
                    </div>
                    <div>
                        <span class="font-black text-2xl tracking-tighter text-slate-900 block leading-none">Open<span class="text-indigo-600">Gabarito</span></span>
                        <span class="text-[9px] bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded-full font-black uppercase tracking-widest border border-emerald-100 shadow-sm inline-block mt-1">Colaborativo</span>
                    </div>
                </a>
                
                <div class="hidden md:flex items-center space-x-1">
                    <a href="index.php" class="text-slate-900 font-bold text-xs uppercase tracking-widest px-4 py-2 rounded-lg bg-slate-100 transition">Rankings</a>
                    <a href="transparencia.php" class="text-slate-500 hover:text-slate-900 font-bold text-xs uppercase tracking-widest px-4 py-2 transition">Transparência</a>
                    <?php if (isAdmin()): ?>
                        <a href="admin/dashboard.php" class="text-rose-600 hover:text-rose-500 font-black px-4 py-2 transition flex items-center gap-2 text-xs uppercase tracking-widest">
                            <i class="fa-solid fa-screwdriver-wrench"></i> Admin
                        </a>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-3 sm:gap-6">
                    <div class="flex items-center gap-4 border-l border-slate-200 pl-6 ml-2">
                        <?php if (isLoggedIn()): ?>
                            <a href="minha_area.php" class="flex items-center gap-3 group">
                                <div class="hidden md:flex flex-col items-end leading-tight">
                                    <span class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Minha Área</span>
                                    <span class="text-xs text-slate-900 font-bold group-hover:text-indigo-600 transition"><?php echo explode(' ', $_SESSION['usuario_nome'])[0]; ?></span>
                                </div>
                                <div class="w-9 h-9 rounded-full bg-white border-2 border-slate-200 overflow-hidden shadow-sm group-hover:border-indigo-500 transition-all">
                                    <?php 
                                    $stmt_nav = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");
                                    $stmt_nav->execute([$_SESSION['usuario_id']]);
                                    $foto_nav = $stmt_nav->fetchColumn();
                                    if ($foto_nav): 
                                    ?>
                                        <img src="<?php echo $foto_nav; ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-indigo-600 text-white text-[10px] font-black">
                                            <?php echo substr($_SESSION['usuario_nome'], 0, 1); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <a href="logout.php" class="text-slate-500 hover:text-rose-400 transition" title="Sair">
                                <i class="fa-solid fa-power-off text-sm"></i>
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="text-xs font-black uppercase tracking-widest text-slate-500 hover:text-slate-900 transition">Entrar</a>
                            <a href="login.php?action=register" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition shadow-lg shadow-indigo-500/20">Cadastrar</a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Mobile Menu Button -->
                    <button id="mobile-menu-btn" class="md:hidden text-slate-500 hover:text-slate-900 p-2">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Container -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-200 bg-white/95 backdrop-blur-xl">
            <div class="px-4 pt-2 pb-6 space-y-1">
                <a href="index.php" class="block px-3 py-4 text-base font-medium text-slate-900 border-b border-primary-500">Ranking</a>
                <a href="transparencia.php" class="block px-3 py-4 text-base font-medium text-slate-500 hover:text-slate-900">Transparência</a>
                <a href="#global-stats" class="block px-3 py-4 text-base font-medium text-slate-500 hover:text-slate-900" onclick="toggleMenu()">Estatísticas</a>
                <?php if (isAdmin()): ?>
                    <a href="admin/dashboard.php" class="block px-3 py-4 text-base font-black text-rose-600 flex items-center gap-2">
                        <i class="fa-solid fa-screwdriver-wrench"></i> Painel Admin
                    </a>
                <?php endif; ?>
                
                <div class="pt-4 border-t border-slate-100 mt-4">
                    <a href="colaborar.php" class="flex items-center gap-3 px-3 py-4 text-indigo-600 font-bold">
                        <i class="fa-solid fa-plus-circle"></i> Adicionar Concurso
                    </a>
                    <?php if (isLoggedIn()): ?>
                        <a href="minha_area.php" class="block px-3 py-4 text-base font-medium text-slate-600">Minha Área</a>
                        <a href="logout.php" class="block px-3 py-4 text-base font-medium text-red-500">Sair</a>
                    <?php else: ?>
                        <a href="login.php" class="block px-3 py-4 text-base font-medium text-slate-600">Entrar</a>
                    <?php endif; ?>
                </div>
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
        
        menuBtn.addEventListener('click', toggleMenu);
    </script>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full">
        <!-- Hero Colaborativo: Manifesto + Moderadores -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-10 mb-16 animate-fade-in py-6 items-center">
            
            <!-- Lado Esquerdo: Manifesto -->
            <div class="md:col-span-7 text-left">
                <div class="inline-flex items-center gap-2 bg-indigo-100 text-indigo-600 px-3 py-1.5 rounded-full text-[9px] font-bold uppercase tracking-widest mb-4 border border-indigo-200">
                    <i class="fa-solid fa-hand-holding-heart"></i> 100% Gratuito e Colaborativo
                </div>
                <h1 class="text-4xl md:text-5xl font-black text-slate-900 mb-4 tracking-tighter leading-tight">
                    A união faz o <span class="text-indigo-600">Ranking.</span>
                </h1>
                <p class="text-base text-slate-500 max-w-xl leading-relaxed mb-8">
                    Chega de pagar caro. O OpenGabarito é uma wiki feita por estudantes para estudantes. Ajuda mútua com o poder da IA.
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-start gap-3">
                    <a href="novo_gabarito.php" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-6 py-3 rounded-xl transition-all flex items-center justify-center gap-2 text-sm shadow-lg shadow-indigo-500/10">
                        <i class="fa-solid fa-paper-plane"></i> Enviar Gabarito
                    </a>
                    <a href="colaborar.php" class="w-full sm:w-auto bg-white hover:bg-slate-50 text-slate-900 font-bold px-6 py-3 rounded-xl transition-all border border-slate-200 flex items-center justify-center gap-2 text-sm shadow-sm">
                        <i class="fa-solid fa-plus"></i> Adicionar Concurso
                    </a>
                </div>
            </div>

            <!-- Lado Direito: Heróis da Comunidade (Moderadores) -->
            <div class="md:col-span-5">
                <div class="bg-white border border-slate-100 rounded-[40px] p-8 shadow-2xl shadow-indigo-500/5 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:opacity-10 transition-opacity">
                        <i class="fa-solid fa-trophy text-8xl text-indigo-600"></i>
                    </div>
                    <div class="flex items-center justify-between mb-8">
                        <span class="text-[11px] text-slate-400 font-black uppercase tracking-[0.2em]">Heróis da Comunidade</span>
                        <div class="w-10 h-10 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center shadow-sm">
                            <i class="fa-solid fa-award"></i>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <?php 
                        // 1. Busca os top 3 globais
                        $stmt = $pdo->query("SELECT id, nome, trust_score, foto_perfil FROM usuarios WHERE trust_score > 0 ORDER BY trust_score DESC LIMIT 3");
                        $top_mods = $stmt->fetchAll();
                        $ids_exibidos = array_column($top_mods, 'id');
                        
                        foreach ($top_mods as $m): 
                            $foto_m = $m['foto_perfil'] ?? null;
                        ?>
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100 hover:border-indigo-200 transition-all">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-white overflow-hidden flex items-center justify-center text-[10px] font-black text-slate-400 border border-slate-200 uppercase">
                                    <?php if ($foto_m): ?>
                                        <img src="<?php echo $foto_m; ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?php echo substr($m['nome'], 0, 1); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-900"><?php echo htmlspecialchars($m['nome']); ?></div>
                                    <div class="text-[8px] text-slate-500 uppercase font-black">Score Confiança</div>
                                </div>
                            </div>
                            <div class="text-xs font-black text-indigo-400"><?php echo $m['trust_score']; ?></div>
                        </div>
                        <?php endforeach; ?>

                        <?php 
                        // 2. Se o usuário logado NÃO está no top 3, mostra ele aqui embaixo
                        if (isLoggedIn() && !in_array($_SESSION['usuario_id'], $ids_exibidos)):
                            $stmt = $pdo->prepare("SELECT nome, trust_score, foto_perfil FROM usuarios WHERE id = ?");
                            $stmt->execute([$_SESSION['usuario_id']]);
                            $me = $stmt->fetch();
                            if ($me && $me['trust_score'] > 0):
                                $foto_me = $me['foto_perfil'] ?? null;
                        ?>
                        <div class="mt-4 pt-4 border-t border-slate-100">
                            <div class="flex items-center justify-between p-3 bg-indigo-50 rounded-2xl border border-indigo-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-600 overflow-hidden flex items-center justify-center text-[10px] font-black text-white border border-indigo-400 uppercase">
                                        <?php if ($foto_me): ?>
                                            <img src="<?php echo $foto_me; ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <?php echo substr($me['nome'], 0, 1); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900"><?php echo htmlspecialchars($me['nome']); ?> (Você)</div>
                                        <div class="text-[8px] text-indigo-600 uppercase font-black">Sua Pontuação Wiki</div>
                                    </div>
                                </div>
                                <div class="text-xs font-black text-indigo-600"><?php echo $me['trust_score']; ?></div>
                            </div>
                        </div>
                        <?php endif; endif; ?>
                    </div>
                    
                    <p class="text-[10px] text-slate-400 mt-6 text-center italic font-medium">
                        Colabore com gabaritos e matérias para subir no ranking!
                    </p>
                </div>
            </div>
        </div>

        <?php if (isLoggedIn()): ?>
        <!-- Seção: Minha Área (Dashboard Rápido) -->
        <div class="mb-20 animate-fade-in">
            <div class="bg-white rounded-[40px] p-8 border border-indigo-100 relative overflow-hidden shadow-2xl shadow-indigo-500/5">
                <div class="absolute top-0 right-0 p-12 opacity-5">
                    <i class="fa-solid fa-user-gear text-9xl text-indigo-600"></i>
                </div>
                
                <div class="flex flex-col md:flex-row items-center gap-10 relative z-10">
                    <!-- Foto de Perfil e Upload -->
                    <div class="relative group">
                        <div class="w-32 h-32 rounded-[32px] border-4 border-white overflow-hidden bg-slate-50 shadow-xl relative transition-all duration-500 group-hover:scale-105 group-hover:rotate-3">
                            <?php 
                            $stmt = $pdo->prepare("SELECT foto_perfil, trust_score FROM usuarios WHERE id = ?");
                            $stmt->execute([$_SESSION['usuario_id']]);
                            $userData = $stmt->fetch();
                            $foto = $userData['foto_perfil'] ?? 'https://www.gravatar.com/avatar/'.md5($_SESSION['usuario_email']).'?d=mp';
                            ?>
                            <img src="<?php echo $foto; ?>" class="w-full h-full object-cover" id="avatar-preview">
                        </div>
                        <form action="handle_avatar.php" method="POST" enctype="multipart/form-data" id="avatar-form" class="absolute -bottom-2 -right-2">
                            <?php echo csrfInput(); ?>
                            <label for="avatar-input" class="w-10 h-10 bg-indigo-600 hover:bg-indigo-500 rounded-2xl flex items-center justify-center cursor-pointer shadow-xl transition-all hover:scale-110 border-2 border-white" title="Trocar foto">
                                <i class="fa-solid fa-camera text-sm text-white"></i>
                            </label>
                            <input type="file" name="avatar" id="avatar-input" class="hidden" accept="image/*" onchange="document.getElementById('avatar-form').submit()">
                        </form>
                    </div>

                    <div class="text-center md:text-left flex-grow">
                        <h2 class="text-3xl font-black text-slate-900 mb-2 tracking-tight">Olá, <?php echo explode(' ', $_SESSION['usuario_nome'])[0]; ?>! 👋</h2>
                        <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 mt-4">
                            <span class="text-[11px] font-black text-slate-500 uppercase tracking-widest bg-amber-50 px-4 py-2 rounded-2xl border border-amber-100 flex items-center gap-2 shadow-sm">
                                <i class="fa-solid fa-star text-amber-500"></i> Trust Score: <span class="text-amber-600"><?php echo $userData['trust_score']; ?></span>
                            </span>
                            <span class="text-[11px] font-black text-slate-500 uppercase tracking-widest bg-emerald-50 px-4 py-2 rounded-2xl border border-emerald-100 flex items-center gap-2 shadow-sm">
                                <i class="fa-solid fa-check-double text-emerald-500"></i> Gabaritos: 
                                <span class="text-emerald-700">
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM respostas_usuarios WHERE usuario_id = ? AND deleted_at IS NULL");
                                    $stmt->execute([$_SESSION['usuario_id']]);
                                    echo $stmt->fetchColumn();
                                    ?>
                                </span>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <a href="minha_area.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-black px-8 py-4 rounded-2xl transition-all shadow-xl shadow-indigo-500/20 flex items-center gap-3 uppercase tracking-widest">
                            <i class="fa-solid fa-chart-line"></i> Painel de Desempenho
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Seção: Nossa Missão -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-24">
            <div class="bg-white border border-slate-100 rounded-[32px] p-10 relative overflow-hidden group hover:border-rose-200 transition-all shadow-xl shadow-slate-200/20">
                <div class="absolute top-0 right-0 p-6 opacity-5 group-hover:opacity-10 transition-opacity">
                    <i class="fa-solid fa-ban text-8xl text-rose-500"></i>
                </div>
                <h3 class="text-slate-900 font-black text-xl mb-4 tracking-tight">Preços Abusivos? Nunca.</h3>
                <p class="text-sm text-slate-500 leading-relaxed font-medium">
                    Nascemos para acabar com o lucro em cima do esforço do concurseiro. Aqui, a informação é livre e o acesso ao ranking é (e sempre será) gratuito.
                </p>
            </div>
            <div class="bg-white border border-slate-100 rounded-[32px] p-10 relative overflow-hidden group hover:border-indigo-200 transition-all shadow-xl shadow-slate-200/20">
                <div class="absolute top-0 right-0 p-6 opacity-5 group-hover:opacity-10 transition-opacity">
                    <i class="fa-solid fa-users-rays text-8xl text-indigo-500"></i>
                </div>
                <h3 class="text-slate-900 font-black text-xl mb-4 tracking-tight">Inteligência Coletiva</h3>
                <p class="text-sm text-slate-500 leading-relaxed font-medium">
                    Cada gabarito enviado ajuda a comunidade. Nossa IA auditada pela Groq (Llama 3.3) processa os dados para te dar a visão mais real do cenário.
                </p>
            </div>
            <div class="bg-white border border-slate-100 rounded-[32px] p-10 relative overflow-hidden group hover:border-emerald-200 transition-all shadow-xl shadow-slate-200/20">
                <div class="absolute top-0 right-0 p-6 opacity-5 group-hover:opacity-10 transition-opacity">
                    <i class="fa-solid fa-shield-halved text-8xl text-emerald-500"></i>
                </div>
                <h3 class="text-slate-900 font-black text-xl mb-4 tracking-tight">Wiki de Elite</h3>
                <p class="text-sm text-slate-500 leading-relaxed font-medium">
                    Você pode editar matérias, sugerir correções e ajudar no gabarito de consenso. Os moderadores mais ativos ganham destaque em nossa comunidade.
                </p>
            </div>
        </div>

        <div id="global-stats" class="flex flex-col md:flex-row justify-between items-start md:items-center mb-12 gap-8">
            <div>
                <h2 class="text-3xl font-black text-slate-900 mb-3 tracking-tighter">Simulação de Notas</h2>
                <p class="text-slate-500 font-medium">Algoritmo aberto, dados transparentes. Acompanhe a estimativa da nota de corte em tempo real.</p>
            </div>
            <div class="w-full md:w-[400px] relative group">
                <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                    <i class="fa-solid fa-magnifying-glass text-slate-300 group-focus-within:text-indigo-500 transition-colors"></i>
                </div>
                <input type="text" id="search-contest" class="block w-full pl-14 pr-6 py-4 bg-white border border-slate-200 rounded-2xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all shadow-sm font-medium" placeholder="Buscar cargo, órgão ou banca...">
            </div>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            <div class="bg-white border border-slate-100 rounded-3xl p-8 shadow-xl shadow-slate-200/20 flex items-center gap-6">
                <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl shadow-inner">
                    <i class="fa-solid fa-database"></i>
                </div>
                <div>
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Gabaritos</h3>
                    <div class="text-3xl font-black text-slate-900 tracking-tight"><?php echo number_format($totalGabaritos, 0, ',', '.'); ?></div>
                </div>
            </div>
            <div class="bg-white border border-slate-100 rounded-3xl p-8 shadow-xl shadow-slate-200/20 flex items-center gap-6">
                <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl shadow-inner">
                    <i class="fa-regular fa-file-lines"></i>
                </div>
                <div>
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Ativos</h3>
                    <div class="text-3xl font-black text-slate-900 tracking-tight"><?php echo count($concursos); ?></div>
                </div>
            </div>
            <div class="bg-white border border-slate-100 rounded-3xl p-8 shadow-xl shadow-slate-200/20 flex items-center gap-6 sm:col-span-2 lg:col-span-1">
                <div class="w-14 h-14 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-xl shadow-inner">
                    <i class="fa-solid fa-bullseye"></i>
                </div>
                <div>
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Acurácia IA</h3>
                    <div class="text-3xl font-black text-slate-900 tracking-tight"><?php echo number_format($aiAccuracy, 1); ?>%</div>
                </div>
            </div>
        </div>

        <!-- Tabela de Concursos -->
        <div class="bg-white border border-slate-100 rounded-[40px] shadow-2xl shadow-slate-200/40 overflow-hidden flex flex-col">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-50">
                    <thead class="bg-slate-50/50">
                        <tr>
                            <th scope="col" class="px-8 py-6 text-left text-[11px] font-black text-slate-400 uppercase tracking-widest">Órgão / Cargo</th>
                            <th scope="col" class="hidden sm:table-cell px-8 py-6 text-left text-[11px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                            <th scope="col" class="px-4 py-6 text-center text-[11px] font-black text-slate-400 uppercase tracking-widest">Amostra</th>
                             <th scope="col" class="px-4 py-6 text-center text-[11px] font-black text-slate-400 uppercase tracking-widest" title="Nota de Corte estimada por inteligência artificial baseada na amostragem e concorrência">Corte (IA)</th>
                            <th scope="col" class="px-8 py-6 text-right"></th>
                        </tr>
                    </thead>
                    <tbody id="ranking-body" class="divide-y divide-slate-50 bg-white">
                        
                        <?php if (empty($concursos)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-slate-400">Nenhum concurso encontrado.</td>
                        </tr>
                        <?php else: foreach ($concursos as $c): ?>
                        <tr class="group hover:bg-slate-50 transition-colors cursor-pointer" onclick="window.location='ranking.php?cargo_id=<?php echo $c['cargo_id']; ?>'">
                            <td class="px-4 sm:px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 rounded-lg bg-white items-center justify-center border border-slate-200 shadow-sm overflow-hidden shrink-0">
                                        <?php if (!empty($c['image_url'])): ?>
                                            <img src="<?php echo e($c['image_url']); ?>" class="w-full h-full object-cover" alt="Logo">
                                        <?php else: ?>
                                            <i class="fa-solid <?php echo e($c['icon']); ?> text-slate-400 text-sm"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-slate-900 group-hover:text-primary-600 transition-colors"><?php echo e($c['nome_orgao']); ?></div>
                                        <div class="text-[11px] text-slate-500 mt-0.5 truncate max-w-[150px] md:max-w-none flex items-center gap-2">
                                            <span><?php echo e($c['nome_cargo']); ?></span>
                                            <?php if (!empty($c['data_prova'])): ?>
                                                <span class="text-[9px] bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200 text-slate-500 font-bold uppercase tracking-tighter">
                                                    <i class="fa-regular fa-calendar-days mr-1"></i> <?php echo date('d/m/Y', strtotime($c['data_prova'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden sm:table-cell px-8 py-6 whitespace-nowrap text-[11px] font-black uppercase tracking-widest">
                                <?php if ($c['status'] == 'aberto'): ?>
                                    <span class="text-emerald-600 flex items-center gap-2">
                                        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                                        Aberto
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 flex items-center gap-2">
                                        <span class="w-2 h-2 bg-slate-300 rounded-full"></span>
                                        Encerrado
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-2 sm:px-6 py-4 whitespace-nowrap text-center text-xs sm:text-sm text-slate-400">
                                <?php echo $c['total_amostras']; ?>
                            </td>
                            <td class="px-2 sm:px-6 py-4 whitespace-nowrap text-center">
                                <?php 
                                    $pnc = "--";
                                    $total = $c['total_amostras'];
                                    $inscritos = $c['inscritos'] ?: 1;
                                    $vagas = $c['vagas'] ?: 1;
                                    $max = $c['nota_maxima'];

                                    if ($total > 0) {
                                        if ($total >= 10) {
                                            // Mesma lógica do ranking.php para amostras maiores
                                            // Como não temos o array completo aqui para buscar o índice exato de forma performática no loop,
                                            // usamos a pnc_ia se estiver saudável (> 30% da prova)
                                            $pnc = ($c['pnc_ia'] > ($c['total_questoes'] * 0.3)) ? $c['pnc_ia'] : ($max * 0.85);
                                        } else {
                                            // Mesma lógica de extrapolação inicial do ranking.php
                                            $pnc = round($max * 0.85, 1);
                                        }
                                    }
                                ?>
                                <span class="font-mono font-bold text-emerald-600"><?php echo is_numeric($pnc) ? number_format($pnc, 1) : $pnc; ?></span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right">
                                <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-slate-900 transition-colors"></i>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Moderadores (Wiki Tier List) -->
        <?php if (!empty($top_mods)): ?>
        <div class="mt-20 mb-10 animate-fade-in">
            <div class="flex items-center gap-4 mb-8">
                <h2 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                    <i class="fa-solid fa-crown text-amber-500"></i> Top Moderadores
                </h2>
                <div class="h-[1px] bg-slate-200 flex-grow"></div>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-12">
                <?php foreach ($top_mods as $idx => $mod): 
                    $colors = ['from-amber-400 to-orange-500', 'from-slate-300 to-slate-400', 'from-amber-700 to-amber-900', 'from-indigo-500 to-blue-600', 'from-emerald-500 to-teal-600'];
                    $color = $colors[$idx] ?? 'from-slate-700 to-slate-800';
                ?>
                    <div class="bg-white border border-slate-200 p-6 rounded-3xl flex flex-col items-center text-center group hover:border-indigo-500 transition-all duration-300 shadow-lg">
                        <div class="w-14 h-14 bg-slate-50 rounded-2xl flex items-center justify-center text-white text-xl font-bold mb-4 shadow-sm group-hover:scale-110 transition-transform overflow-hidden border-2 border-white">
                            <?php if (!empty($mod['foto_perfil'])): ?>
                                <img src="<?php echo $mod['foto_perfil']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-br <?php echo $color; ?> flex items-center justify-center text-white">
                                    <?php echo strtoupper(substr($mod['nome'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="text-sm font-bold text-slate-900 mb-1 truncate w-full"><?php echo htmlspecialchars($mod['nome']); ?></div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full font-black uppercase tracking-tighter">
                                Reputação <?php echo $mod['trust_score']; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>


    <!-- Modal de Sugestões -->
    <div id="modal-sugestao" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="toggleSugestao()"></div>
        <div class="glass-panel w-full max-w-lg rounded-3xl p-8 relative animate-fade-in shadow-2xl">
            <h2 class="text-2xl font-bold text-slate-900 mb-2">Enviar Sugestão</h2>
            <p class="text-slate-500 mb-6 text-sm">Sua opinião é fundamental para melhorarmos o OpenGabarito.</p>
            
            <form id="form-sugestao">
                <textarea id="msg-sugestao" required placeholder="Digite sua sugestão ou feedback aqui..." 
                          class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-slate-900 text-sm focus:ring-2 focus:ring-indigo-500 outline-none h-40 transition mb-6"></textarea>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-4 rounded-2xl transition shadow-lg shadow-indigo-500/20 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i> Enviar Feedback
                </button>
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

        // Live Search Logic
        const searchInput = document.getElementById('search-contest');
        const rankingBody = document.getElementById('ranking-body');

        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const q = e.target.value;

            if (q.length < 2) {
                // Se apagar tudo, você pode recarregar a página ou manter o que está lá
                // Para simplificar, só buscaremos se houver 2+ caracteres
                return;
            }

            searchTimeout = setTimeout(async () => {
                rankingBody.innerHTML = '<tr><td colspan="5" class="px-6 py-10 text-center text-slate-400"><i class="fa-solid fa-circle-notch animate-spin mr-2"></i> Buscando...</td></tr>';
                
                try {
                    const response = await fetch(`api/search.php?q=${encodeURIComponent(q)}`);
                    const data = await response.json();

                    if (data.length === 0) {
                        rankingBody.innerHTML = '<tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">Nenhum resultado encontrado para "' + q + '".</td></tr>';
                    } else {
                        rankingBody.innerHTML = data.map(c => `
                             <tr class="group hover:bg-slate-50 transition-colors cursor-pointer" onclick="window.location='ranking.php?cargo_id=${c.cargo_id}'">
                                <td class="px-4 sm:px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 rounded-lg bg-white items-center justify-center border border-slate-200 shadow-sm overflow-hidden shrink-0">
                                            ${c.image_url ? `<img src="${c.image_url}" class="w-full h-full object-cover" alt="Logo">` : `<i class="fa-solid ${c.icon} text-slate-400 text-sm"></i>`}
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-slate-900 group-hover:text-primary-600 transition-colors">${c.nome_orgao}</div>
                                            <div class="text-[11px] text-slate-500 mt-0.5 truncate max-w-[150px] md:max-w-none flex items-center gap-2">
                                                <span>${c.nome_cargo}</span>
                                                ${c.data_prova ? `
                                                    <span class="text-[9px] bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200 text-slate-500 font-bold uppercase tracking-tighter">
                                                        <i class="fa-regular fa-calendar-days mr-1"></i> ${new Date(c.data_prova).toLocaleDateString('pt-BR')}
                                                    </span>
                                                ` : ''}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden sm:table-cell px-6 py-5 whitespace-nowrap text-xs">
                                    <span class="${c.status === 'aberto' ? 'text-success-600 font-medium' : 'text-slate-400'}">● ${c.status === 'aberto' ? 'Recebendo' : 'Encerrado'}</span>
                                </td>
                                <td class="px-2 sm:px-6 py-4 whitespace-nowrap text-center text-xs sm:text-sm text-slate-400">
                                    ${c.total_amostras}
                                </td>
                                <td class="px-2 sm:px-6 py-4 whitespace-nowrap text-center">
                                    <span class="font-mono font-bold text-emerald-600">${c.pnc_ia > 0 ? parseFloat(c.pnc_ia).toFixed(1) : (c.nota_maxima || '--')}</span>
                                </td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right">
                                    <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-slate-900 transition-colors"></i>
                                </td>
                            </tr>
                        `).join('');
                    }
                } catch (err) {
                    console.error('Search error:', err);
                }
            }, 300);
        });
    </script>

    <?php echo getFooter(); ?>

    <script>
        <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
            window.onload = () => Toast.show("Bem-vindo de volta, <?php echo $_SESSION['usuario_nome']; ?>!", "success");
        <?php endif; ?>
    </script>
</body>
</html>