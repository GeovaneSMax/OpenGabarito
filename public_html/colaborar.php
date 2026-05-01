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
$gabaritos_oficiais = [];
$cargo_materias = [];

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

        // Top Colaboradores
        $stmt = $pdo->prepare("SELECT u.nome, u.foto_perfil, u.trust_score, COUNT(el.id) as total 
                               FROM edicoes_log el 
                               JOIN usuarios u ON el.usuario_id = u.id 
                               WHERE el.objeto_id = ? AND el.tipo_objeto = 'cargo' 
                               GROUP BY u.id 
                               ORDER BY total DESC, u.trust_score DESC LIMIT 5");
        $stmt->execute([$cargo_id]);
        $colaboradores = $stmt->fetchAll();

        // Gabaritos Colaborativos (Versões)
        $stmt = $pdo->prepare("SELECT gc.*, u.nome, u.foto_perfil, 
                               (SELECT COUNT(*) FROM votos_gabaritos WHERE gabarito_colab_id = gc.id) as upvotes
                               FROM gabaritos_colaborativos gc 
                               JOIN usuarios u ON gc.usuario_id = u.id 
                               WHERE gc.cargo_id = ? 
                               ORDER BY gc.criado_em DESC LIMIT 5");
        $stmt->execute([$cargo_id]);
        $versoes_gabarito = $stmt->fetchAll();

        // Buscar gabaritos oficiais
        $gabaritos_oficiais = [];
        $stmt = $pdo->prepare("SELECT versao, respostas_json FROM gabaritos_oficiais WHERE cargo_id = ?");
        $stmt->execute([$cargo_id]);
        while ($row = $stmt->fetch()) {
            $gabaritos_oficiais[$row['versao']] = json_decode($row['respostas_json'], true);
        }
    }
}

// Processamento do POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validateCSRF();
        
        // 0. Auditoria por IA antes de começar
        $dados_auditoria = [
            'nome_orgao' => $_POST['nome_orgao'] ?? '',
            'banca' => $_POST['banca'] ?? '',
            'nome_cargo' => $_POST['nome_cargo'] ?? '',
            'total_questoes' => (int)($_POST['total_questoes'] ?? 0)
        ];
        
        $analise_ia = verificarEdicaoConcursoIA($dados_auditoria);
        if ($analise_ia['veredito'] === 'invalido') {
            throw new Exception("A IA detectou dados inconsistentes: " . $analise_ia['motivo']);
        }
        $score_ia = $analise_ia['confianca'] ?? 50;

        $pdo->beginTransaction();
        
        $nome_orgao = $dados_auditoria['nome_orgao'];
        $banca = $dados_auditoria['banca'];
        $nome_cargo = $dados_auditoria['nome_cargo'];
        $total_questoes = $dados_auditoria['total_questoes'];
        
        // Dados Antigos para Log (Snapshot)
        $dados_anteriores = $info ? $info : [];

        // --- INÍCIO DA BLINDAGEM (FASE 2) ---
        $trust_score = $_SESSION['trust_score'] ?? 0;
        $pode_editar_direto = ($trust_score >= 80 || isAdmin() || $score_ia >= 95);

        if ($pode_editar_direto) {
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
            $slug = 'ranking-pos-prova-' . slugify($nome_orgao) . '-' . slugify($nome_cargo);

            if ($info) {
                $stmt = $pdo->prepare("UPDATE cargos SET nome_cargo = ?, slug = ?, total_questoes = ?, tem_discursiva = ?, tem_titulos = ?, pontos_negativos = ?, nota_padronizada = ?, por_genero = ?, nota_corte_oficial = ?, editado_por = ? WHERE id = ?");
                $stmt->execute([$nome_cargo, $slug, $total_questoes, $regras['tem_discursiva'], $regras['tem_titulos'], $regras['pontos_negativos'], $regras['nota_padronizada'], $regras['por_genero'], $nota_corte, $_SESSION['usuario_id'], $cargo_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO cargos (concurso_id, nome_cargo, slug, total_questoes, tem_discursiva, tem_titulos, pontos_negativos, nota_padronizada, por_genero, nota_corte_oficial, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$concurso_id, $nome_cargo, $slug, $total_questoes, $regras['tem_discursiva'], $regras['tem_titulos'], $regras['pontos_negativos'], $regras['nota_padronizada'], $regras['por_genero'], $nota_corte, $_SESSION['usuario_id']]);
                $cargo_id = $pdo->lastInsertId();
            }

            // 3. Salvar Modalidades
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
                $pdo->prepare("DELETE FROM cargo_materias WHERE cargo_id = ?")->execute([$cargo_id]);
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

            // 5. Salvar Gabarito Oficial (Incluso na blindagem)
            if (!empty($_POST['gabarito_oficial_json'])) {
                $all_versions = json_decode($_POST['gabarito_oficial_json'], true);
                if (is_array($all_versions)) {
                    foreach ($all_versions as $versao => $respostas) {
                        $res_json = json_encode($respostas);
                        $stmt = $pdo->prepare("INSERT INTO gabaritos_oficiais (cargo_id, versao, respostas_json) 
                                               VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE respostas_json = VALUES(respostas_json)");
                        $stmt->execute([$cargo_id, $versao, $res_json]);
                    }
                }
            }

            atualizarConsenso($pdo, $cargo_id);
        }
        // --- FIM DA BLINDAGEM ---

        // 7. Log da Contribuição e Atualização de Trust Score
        $status_edicao = $pode_editar_direto ? 'aprovado' : 'pendente';
        $justificativa = $_POST['justificativa'] ?? '';
        
        registrarEdicaoWiki(
            $pdo, 
            $_SESSION['usuario_id'], 
            'cargo', 
            $cargo_id, 
            $dados_anteriores, 
            $_POST, 
            $score_ia, 
            $status_edicao, 
            $justificativa
        );

        $pdo->commit();
        $sucesso = "Wiki atualizada com sucesso!";
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;800;900&display=swap');
        
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --emerald: #10b981;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background: #f8fafc;
            color: #1e293b;
        }

        .font-outfit { font-family: 'Outfit', sans-serif; }

        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .bento-card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        .bento-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
            border-color: var(--primary);
        }

        .input-elegant {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.2s;
            outline: none;
        }

        .input-elegant:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .btn-modern {
            background: var(--primary);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-modern:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #1e293b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .badge-step {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.875rem;
            background: #eef2ff;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .active-step .badge-step {
            background: var(--primary);
            color: white;
        }

        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }

        .animate-blob {
            animation: blob 7s infinite;
        }

        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }

        .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
    </style>
</head>
<body class="min-h-screen relative overflow-x-hidden">
    <!-- Background Accents -->
    <div class="fixed top-0 left-0 w-full h-full -z-10 pointer-events-none overflow-hidden">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-indigo-100 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob"></div>
        <div class="absolute top-[20%] right-[-5%] w-[35%] h-[35%] bg-emerald-100 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000"></div>
        <div class="absolute bottom-[-10%] left-[20%] w-[30%] h-[30%] bg-pink-100 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-4000"></div>
    </div>

    <nav class="sticky top-0 z-50 glass border-b border-slate-200/50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2">
                <div class="scale-90"><?php echo getLogoSVG(36); ?></div>
                <span class="font-outfit font-black text-xl tracking-tight text-slate-900">Open<span class="text-indigo-600">Gabarito</span></span>
            </a>
            <div class="hidden md:flex items-center gap-6">
                <a href="ranking.php" class="text-sm font-semibold text-slate-600 hover:text-indigo-600 transition">Rankings</a>
                <a href="minha_area.php" class="text-sm font-semibold text-slate-600 hover:text-indigo-600 transition">Minha Área</a>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest bg-slate-100 px-3 py-1.5 rounded-full">Wiki Engine v3</span>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-12">
        <!-- Hero Header -->
        <header class="text-center mb-16 relative">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 border border-indigo-100 rounded-full mb-6">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                </span>
                <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">Central de Inteligência Colaborativa</span>
            </div>
            <h1 class="text-4xl md:text-5xl font-outfit font-black text-slate-900 mb-4 tracking-tight">
                Construa o Gabarito <span class="bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-emerald-500">do Amanhã.</span>
            </h1>
            <p class="text-slate-500 max-w-2xl mx-auto font-medium leading-relaxed">
                Nossa IA processa milhares de dados, mas o seu olhar humano é o que garante a perfeição. Ajude a democratizar a informação.
            </p>
        </header>

        <?php if (!empty($erro)): ?>
            <div class="bento-card border-rose-200 bg-rose-50/50 p-6 mb-8 flex items-center gap-4 animate-fade-in">
                <div class="w-10 h-10 bg-rose-500 text-white rounded-xl flex items-center justify-center shadow-lg shadow-rose-200">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </div>
                <p class="text-rose-700 font-semibold text-sm"><?php echo $erro; ?></p>
            </div>
        <?php endif; ?>

        <!-- Community Bento Hub -->
        <section class="grid grid-cols-1 md:grid-cols-12 gap-6 mb-12">
            <!-- PDF Import (Main Action) -->
            <div class="md:col-span-8 bento-card p-8 bg-gradient-to-br from-indigo-600 to-indigo-700 text-white border-0 overflow-hidden relative group">
                <div class="absolute -right-20 -bottom-20 w-64 h-64 bg-white/10 rounded-full filter blur-3xl group-hover:bg-white/20 transition-all duration-500"></div>
                <div class="relative z-10 flex flex-col md:flex-row items-center gap-8 h-full">
                    <div class="flex-grow">
                        <h3 class="text-2xl font-outfit font-black mb-2">Power Import (IA)</h3>
                        <p class="text-indigo-100/80 text-sm font-medium mb-6">
                            Arraste o edital e nossa IA extrai automaticamente vagas, matérias e regras. Economize 20 minutos de trabalho manual.
                        </p>
                        <button type="button" onclick="document.getElementById('edital-upload').click()" id="ai-import-btn" class="bg-white text-indigo-600 px-6 py-3 rounded-xl font-bold text-sm shadow-xl hover:bg-indigo-50 transition flex items-center gap-2">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Injetar Edital (PDF)
                        </button>
                        <input type="file" id="edital-upload" accept="application/pdf" class="hidden" onchange="handleEditalUpload(this)">
                    </div>
                    <div class="w-32 h-32 bg-white/10 backdrop-blur-md rounded-3xl flex items-center justify-center text-4xl border border-white/20">
                        <i class="fa-solid fa-file-pdf"></i>
                    </div>
                </div>

                <!-- Status da IA Overlaid -->
                <div id="ai-status-bar" class="hidden absolute inset-0 bg-indigo-900/90 backdrop-blur-md z-20 flex flex-col items-center justify-center p-8 text-center">
                    <div class="w-full max-w-xs">
                        <div class="flex items-center justify-between mb-2">
                            <span id="ai-status-text" class="text-xs font-bold uppercase tracking-widest text-indigo-200">Processando...</span>
                            <span id="ai-status-percent" class="text-white text-xs font-black">0%</span>
                        </div>
                        <div class="h-2 w-full bg-white/10 rounded-full overflow-hidden">
                            <div id="ai-status-progress" class="h-full bg-emerald-400 transition-all duration-500 shadow-[0_0_12px_rgba(52,211,153,0.5)]"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contributors List -->
            <div class="md:col-span-4 bento-card p-6 flex flex-col">
                <h4 class="text-slate-900 font-bold text-sm mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-crown text-amber-500"></i> Top Contributors
                </h4>
                <div class="space-y-3 flex-grow overflow-y-auto pr-2 custom-scrollbar">
                    <?php if (empty($colaboradores)): ?>
                        <div class="flex flex-col items-center justify-center h-full opacity-30 py-4">
                            <i class="fa-solid fa-users text-2xl mb-2"></i>
                            <span class="text-[10px] font-bold uppercase">Aguardando Heróis</span>
                        </div>
                    <?php else: foreach ($colaboradores as $c): ?>
                        <div class="flex items-center gap-3 p-2 rounded-xl hover:bg-slate-50 transition border border-transparent hover:border-slate-100">
                            <img src="<?php echo $c['foto_perfil'] ?: 'https://ui-avatars.com/api/?name='.urlencode($c['nome']); ?>" class="w-8 h-8 rounded-lg border border-slate-200">
                            <div class="flex-grow min-w-0">
                                <span class="block text-xs font-bold text-slate-800 truncate"><?php echo e($c['nome']); ?></span>
                                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest"><?php echo $c['total']; ?> contribuições</span>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- History Time Travel -->
            <div class="md:col-span-4 bento-card p-6">
                <h4 class="text-slate-900 font-bold text-sm mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-indigo-500"></i> Log Recente
                </h4>
                <div class="space-y-4 max-h-[160px] overflow-y-auto pr-2 custom-scrollbar">
                    <?php if (empty($historico)): ?>
                         <div class="flex flex-col items-center justify-center py-8 opacity-30">
                            <span class="text-[10px] font-bold uppercase">Sem registros</span>
                        </div>
                    <?php else: foreach ($historico as $h): ?>
                        <div class="relative pl-4 border-l-2 border-slate-100 group">
                            <div class="absolute -left-[5px] top-0 w-2 h-2 rounded-full bg-slate-200 group-hover:bg-indigo-400 transition-colors"></div>
                            <span class="block text-[10px] font-bold text-slate-800"><?php echo e($h['nome']); ?></span>
                            <span class="block text-[9px] text-slate-400 uppercase tracking-tight"><?php echo date('d/m H:i', strtotime($h['criado_em'])); ?></span>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Visual Gallery (If editing) -->
            <div class="md:col-span-8 bento-card p-6 overflow-hidden">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-slate-900 font-bold text-sm flex items-center gap-2">
                        <i class="fa-solid fa-images text-emerald-500"></i> Galeria da Comunidade
                    </h4>
                    <?php if ($info): ?>
                        <button type="button" onclick="document.getElementById('concurso-image-upload').click()" class="text-[10px] font-black text-indigo-600 hover:text-indigo-700 uppercase tracking-widest flex items-center gap-1">
                            <i class="fa-solid fa-plus-circle"></i> Enviar Foto
                        </button>
                        <input type="file" id="concurso-image-upload" accept="image/*" class="hidden" onchange="uploadContestImage(this)">
                    <?php endif; ?>
                </div>
                
                <div id="contest-images-container" class="flex gap-4 overflow-x-auto pb-4 custom-scrollbar min-h-[100px]">
                    <?php if (!$info): ?>
                        <div class="flex flex-col items-center justify-center w-full py-8 opacity-40">
                            <i class="fa-solid fa-lock text-xl mb-2"></i>
                            <p class="text-[10px] font-bold uppercase tracking-widest">Salve os dados básicos primeiro</p>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center justify-center w-full py-8 text-slate-400">
                            <i class="fa-solid fa-circle-notch animate-spin text-xl"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Main Form -->
        <form method="POST" id="onboarding-form" class="space-y-12">
            <?php echo csrfInput(); ?>

            <!-- Step 1: Basic Information -->
            <section class="bento-card p-8 md:p-12">
                <div class="badge-step">01</div>
                <h2 class="text-2xl font-outfit font-black text-slate-900 mb-2 tracking-tight">Informações Básicas</h2>
                <p class="text-slate-500 text-sm mb-10 font-medium leading-relaxed">Defina a identidade do concurso e os detalhes da prova.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Órgão Público</label>
                        <input type="text" id="nome_orgao" name="nome_orgao" required value="<?php echo $info['nome_orgao'] ?? ''; ?>" onchange="checkDuplicates()" placeholder="Ex: Correios, INSS, Polícia Federal" class="w-full input-elegant text-lg font-semibold">
                    </div>
                    
                    <div class="md:col-span-2">
                        <div id="duplicate-alert" class="hidden mb-8 p-6 bg-amber-50 border border-amber-200 rounded-2xl animate-fade-in">
                            <div class="flex flex-col md:flex-row items-center gap-6">
                                <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                                </div>
                                <div class="flex-grow text-center md:text-left">
                                    <h4 class="text-amber-800 font-bold mb-1">Atenção: Já cadastrado!</h4>
                                    <p class="text-amber-600 text-xs font-medium">Este concurso já existe no nosso banco de dados. Evite duplicidade para não fragmentar o ranking.</p>
                                </div>
                                <div id="duplicate-matches" class="flex flex-col gap-2 w-full md:w-auto"></div>
                            </div>
                        </div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Banca Examinadora</label>
                        <input type="text" id="banca" name="banca" required value="<?php echo $info['banca'] ?? ''; ?>" onchange="checkDuplicates()" placeholder="Ex: Cebraspe, FGV, FCC" class="w-full input-elegant">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Cargo / Especialidade</label>
                        <input type="text" id="nome_cargo" name="nome_cargo" required value="<?php echo $info['nome_cargo'] ?? ''; ?>" onchange="checkDuplicates()" placeholder="Ex: Técnico Administrativo - Área Geral" class="w-full input-elegant text-lg font-semibold">
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Status do Ranking</label>
                        <select name="status" class="w-full input-elegant bg-white cursor-pointer appearance-none">
                            <option value="aberto" <?php echo ($info['c_status'] ?? '') == 'aberto' ? 'selected' : ''; ?>>ABERTO (Coletando Gabaritos)</option>
                            <option value="consolidado" <?php echo ($info['c_status'] ?? '') == 'consolidado' ? 'selected' : ''; ?>>CONSOLIDADO (Gabarito Oficial OK)</option>
                            <option value="aguardando" <?php echo ($info['c_status'] ?? '') == 'aguardando' ? 'selected' : ''; ?>>AGUARDANDO PROVA</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Data da Prova</label>
                        <input type="date" name="data_prova" value="<?php echo $info['data_prova'] ?? ''; ?>" class="w-full input-elegant">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Link Oficial (Organizador)</label>
                        <input type="url" name="link_oficial" value="<?php echo $info['link_oficial'] ?? ''; ?>" placeholder="https://..." class="w-full input-elegant">
                    </div>
                </div>
            </section>

            <!-- Step 2: Rules & Mechanics -->
            <section class="bento-card p-8 md:p-12">
                <div class="badge-step">02</div>
                <h2 class="text-2xl font-outfit font-black text-slate-900 mb-2 tracking-tight">Mecânica da Pontuação</h2>
                <p class="text-slate-500 text-sm mb-10 font-medium leading-relaxed">Como os pontos são calculados neste edital?</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php 
                    $regras_lista = [
                        ['name' => 'tem_discursiva', 'label' => 'Prova Discursiva', 'desc' => 'Redação ou questões abertas', 'icon' => 'fa-pen-nib'],
                        ['name' => 'pontos_negativos', 'label' => 'Uma Errada Anula Certa', 'desc' => 'Estilo Cebraspe / Quadrix', 'icon' => 'fa-circle-minus'],
                        ['name' => 'tem_titulos', 'label' => 'Prova de Títulos', 'desc' => 'Mestrado, Doutorado, Experiência', 'icon' => 'fa-medal'],
                        ['name' => 'por_genero', 'label' => 'Divisão por Gênero', 'desc' => 'Rankings Masculino e Feminino', 'icon' => 'fa-venus-mars'],
                        ['name' => 'nota_padronizada', 'label' => 'Nota Padronizada', 'desc' => 'Algoritmo FCC / Vunesp', 'icon' => 'fa-calculator']
                    ];
                    foreach ($regras_lista as $regra):
                    ?>
                    <label class="relative flex items-center p-5 rounded-2xl border border-slate-200 hover:border-indigo-200 hover:bg-indigo-50/50 cursor-pointer transition group">
                        <div class="flex-grow flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-slate-50 group-hover:bg-indigo-100 text-slate-400 group-hover:text-indigo-600 flex items-center justify-center transition">
                                <i class="fa-solid <?php echo $regra['icon']; ?>"></i>
                            </div>
                            <div>
                                <span class="block text-sm font-bold text-slate-800"><?php echo $regra['label']; ?></span>
                                <span class="text-[10px] text-slate-400 font-medium uppercase tracking-tight"><?php echo $regra['desc']; ?></span>
                            </div>
                        </div>
                        <input type="checkbox" name="<?php echo $regra['name']; ?>" <?php echo ($info[$regra['name']] ?? 0) ? 'checked' : ''; ?> class="w-6 h-6 rounded-lg border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Step 3: Quotas & Vacancies -->
            <section class="bento-card p-8 md:p-12 overflow-hidden">
                <div class="badge-step">03</div>
                <h2 class="text-2xl font-outfit font-black text-slate-900 mb-2 tracking-tight">Quadro de Vagas</h2>
                <p class="text-slate-500 text-sm mb-10 font-medium leading-relaxed">Quantas pessoas avançam para a próxima etapa?</p>

                <div class="overflow-x-auto -mx-8 md:-mx-12">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-y border-slate-100">
                                <th class="py-4 px-8 text-[10px] font-black text-slate-400 uppercase tracking-widest">Modalidade</th>
                                <th class="py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Inscritos</th>
                                <th class="py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Vagas Diretas</th>
                                <th class="py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Para 2ª Etapa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php 
                            $mods = [
                                'ampla' => 'Ampla Concorrência',
                                'pcd' => 'PCD (Deficientes)',
                                'ppp' => 'Cotas (Pretos/Pardos)',
                                'hipossuficiente' => 'Hipossuficientes',
                                'indigena' => 'Indígenas',
                                'trans' => 'Trans (LGBT+)',
                                'quilombola' => 'Quilombolas'
                            ];
                            foreach ($mods as $key => $label): 
                            ?>
                            <tr class="hover:bg-slate-50/30 transition">
                                <td class="py-4 px-8">
                                    <span class="text-xs font-bold text-slate-700"><?php echo $label; ?></span>
                                </td>
                                <td class="py-4">
                                    <input type="number" name="inscritos_<?php echo $key; ?>" value="<?php echo $existing_mods[$key]['inscritos'] ?? ''; ?>" placeholder="0" class="w-20 mx-auto block input-elegant py-2 px-2 text-center text-xs">
                                </td>
                                <td class="py-4">
                                    <input type="number" name="vagas_<?php echo $key; ?>" value="<?php echo $existing_mods[$key]['vagas'] ?? ''; ?>" placeholder="0" class="w-20 mx-auto block input-elegant py-2 px-2 text-center text-xs">
                                </td>
                                <td class="py-4">
                                    <input type="number" name="v2e_<?php echo $key; ?>" value="<?php echo $existing_mods[$key]['vagas_2etapa'] ?? ''; ?>" placeholder="0" class="w-20 mx-auto block input-elegant py-2 px-2 text-center text-xs">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Step 4: Official Answer Key (Gabarito Oficial) -->
            <section class="bento-card p-8 md:p-12 border-emerald-100 bg-emerald-50/10">
                <div class="badge-step bg-emerald-100 text-emerald-600">04</div>
                <h2 class="text-2xl font-outfit font-black text-slate-900 mb-2 tracking-tight">Gabarito Oficial</h2>
                <p class="text-slate-500 text-sm mb-10 font-medium leading-relaxed">Importe o gabarito liberado pela banca para oficializar os resultados.</p>

                <div class="flex flex-col md:flex-row items-center justify-between gap-6 mb-10 pb-10 border-b border-emerald-100">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center text-emerald-500 shadow-sm border border-emerald-100">
                            <i class="fa-solid fa-file-invoice text-xl"></i>
                        </div>
                        <div>
                            <h4 class="text-slate-900 font-bold text-sm">Entrada de Respostas</h4>
                            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-tight">Várias versões? Nossa IA separa elas.</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="document.getElementById('gabarito-upload').click()" class="btn-modern bg-emerald-600 hover:bg-emerald-700 text-[11px] shadow-emerald-200">
                            <i class="fa-solid fa-cloud-arrow-up"></i> PDF do Gabarito
                        </button>
                        <button type="button" onclick="addGabaritoVersion()" class="btn-modern btn-secondary text-[11px]">
                            <i class="fa-solid fa-plus"></i> Nova Versão
                        </button>
                        <input type="file" id="gabarito-upload" accept="application/pdf" class="hidden" onchange="handleGabaritoUpload(this)">
                    </div>
                </div>

                <!-- Tabs de Versões -->
                <div id="gabarito-tabs" class="flex gap-2 mb-8 overflow-x-auto pb-2 custom-scrollbar"></div>

                <div id="ai-gabarito-status" class="hidden mb-10 p-10 bg-white border border-dashed border-emerald-200 rounded-3xl text-center">
                    <i class="fa-solid fa-circle-notch animate-spin text-emerald-500 text-3xl mb-4"></i>
                    <p class="text-emerald-700 font-bold" id="gabarito-status-text">Analisando estrutura do gabarito...</p>
                </div>

                <div id="gabarito-container" class="space-y-8">
                    <div class="p-6 md:p-10 bg-white border border-slate-100 rounded-3xl shadow-inner-sm overflow-x-auto">
                        <div id="gabarito-respostas-grid" class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-8 lg:grid-cols-10 gap-4"></div>
                    </div>
                </div>
                <input type="hidden" name="gabarito_oficial_json" id="gabarito-oficial-json">
            </section>

            <!-- Step 5: Structure & Subjects -->
            <section class="bento-card p-8 md:p-12">
                <div class="badge-step">05</div>
                <h2 class="text-2xl font-outfit font-black text-slate-900 mb-2 tracking-tight">Matérias e Estrutura</h2>
                <p class="text-slate-500 text-sm mb-10 font-medium leading-relaxed">Divida a prova por disciplinas para análises detalhadas.</p>

                <div class="space-y-10">
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-4 ml-1">Total de Questões</label>
                        <input type="number" name="total_questoes" required value="<?php echo $info['total_questoes'] ?? 60; ?>" class="w-full input-elegant text-4xl h-20 text-center font-outfit font-black text-indigo-600">
                    </div>

                    <div id="materias-container" class="space-y-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-slate-900 font-bold text-sm">Distribuição das Disciplinas</h4>
                            <button type="button" onclick="addMateriaRow()" class="btn-modern text-[10px] py-2 px-4">
                                <i class="fa-solid fa-plus"></i> Adicionar Matéria
                            </button>
                        </div>
                        
                        <div id="materias-list" class="space-y-4"></div>

                        <div id="materias-empty" class="p-12 bg-slate-50/50 border-2 border-dashed border-slate-200 rounded-3xl text-center">
                            <i class="fa-solid fa-layer-group text-slate-200 text-4xl mb-4"></i>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">Nenhuma matéria adicionada ainda</p>
                        </div>
                    </div>

                    <div class="pt-8 space-y-6 border-t border-slate-100">
                        <label class="block">
                            <span class="text-xs font-black text-slate-400 uppercase mb-3 ml-1 block tracking-widest">Justificativa da Edição</span>
                            <textarea name="justificativa" rows="3" placeholder="Ex: Retificação do Edital n° 02/2026..." class="w-full input-elegant text-sm"></textarea>
                        </label>
                        <div class="flex items-start gap-3 p-4 bg-indigo-50 border border-indigo-100 rounded-2xl">
                            <i class="fa-solid fa-circle-info text-indigo-500 mt-1"></i>
                            <p class="text-[10px] text-indigo-700 font-medium leading-relaxed uppercase tracking-tight">Suas edições alimentam a nossa IA. Usuários com alto Trust Score têm aprovação instantânea.</p>
                        </div>
                    </div>

                    <div class="pt-8">
                        <input type="hidden" name="finalizar" value="1">
                        <button type="submit" class="w-full btn-modern py-6 text-xl shadow-xl shadow-indigo-100">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Publicar na Wiki
                        </button>
                    </div>
                </div>
            </section>
        </form>
    </main>

    <script>
        async function handleEditalUpload(input) {
            function updateStatus(pct, text) {
                const statusBar = document.getElementById('ai-status-bar');
                const statusText = document.getElementById('ai-status-text');
                const statusPercent = document.getElementById('ai-status-percent');
                const statusProgress = document.getElementById('ai-status-progress');
                if (statusProgress) statusProgress.style.width = pct + '%';
                if (statusPercent) statusPercent.innerText = pct + '%';
                if (statusText) statusText.innerText = text;
            }

            const file = input.files[0];
            if (!file) return;

            if (typeof pdfjsLib === 'undefined') {
                alert("Motor de PDF carregando...");
                return;
            }

            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            const statusBar = document.getElementById('ai-status-bar');
            statusBar.classList.remove('hidden');
            updateStatus(10, "Lendo PDF...");

            try {
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
                        updateStatus(70, "IA extraindo dados...");
                        const response = await fetch('api/api_ai_parse_edital.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ text: fullText })
                        });
                        const res = await response.json();
                        if (res.success) {
                            updateStatus(100, "Sucesso!");
                            fillForm(res.data);
                            Toast.show("Dados extraídos pela IA!", "success");
                            setTimeout(() => statusBar.classList.add('hidden'), 2000);
                        } else {
                            throw new Error(res.error);
                        }
                    } catch (err) {
                        Toast.show("Erro: " + err.message, "error");
                        statusBar.classList.add('hidden');
                    }
                };
                reader.readAsArrayBuffer(file);
            } catch (err) {
                statusBar.classList.add('hidden');
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
            if (data.nome_orgao) setVal('nome_orgao', data.nome_orgao);
            if (data.banca) setVal('banca', data.banca);
            if (data.nome_cargo) setVal('nome_cargo', data.nome_cargo);
            if (data.regras) {
                setVal('tem_discursiva', data.regras.tem_discursiva, true);
                setVal('pontos_negativos', data.regras.pontos_negativos, true);
                setVal('tem_titulos', data.regras.tem_titulos, true);
                setVal('por_genero', data.regras.por_genero, true);
                setVal('nota_padronizada', data.regras.nota_padronizada, true);
            }
            if (data.total_questoes) setVal('total_questoes', data.total_questoes);
            if (data.vagas) {
                Object.keys(data.vagas).forEach(key => setVal(`vagas_${key === 'ppd' ? 'pcd' : key}`, data.vagas[key]));
            }
            if (data.materias) {
                document.getElementById('materias-list').innerHTML = '';
                data.materias.forEach(m => addMateriaRow(m));
            }
            checkDuplicates();
        }

        function addMateriaRow(m = {}) {
            const list = document.getElementById('materias-list');
            document.getElementById('materias-empty').classList.add('hidden');
            const div = document.createElement('div');
            div.className = "flex flex-col md:flex-row gap-4 p-5 bg-white border border-slate-100 rounded-2xl shadow-sm animate-fade-in";
            div.innerHTML = `
                <div class="flex-grow grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase mb-1 block">Disciplina</label>
                        <input type="text" name="materia_nome[]" value="${m.nome || ''}" required placeholder="Ex: Português" class="w-full input-elegant text-xs">
                    </div>
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase mb-1 block">Sigla</label>
                        <input type="text" name="materia_sigla[]" value="${m.sigla || ''}" placeholder="PT" class="w-full input-elegant text-xs text-center">
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="text-[9px] font-black text-slate-400 uppercase mb-1 block">Início</label>
                            <input type="number" name="materia_inicio[]" value="${m.inicio || ''}" required class="w-full input-elegant text-xs text-center">
                        </div>
                        <div class="flex-1">
                            <label class="text-[9px] font-black text-slate-400 uppercase mb-1 block">Fim</label>
                            <input type="number" name="materia_fim[]" value="${m.fim || ''}" required class="w-full input-elegant text-xs text-center">
                        </div>
                    </div>
                </div>
                <div class="flex items-end gap-2 shrink-0">
                    <div class="w-20">
                        <label class="text-[9px] font-black text-slate-400 uppercase mb-1 block">Peso</label>
                        <input type="number" step="0.1" name="materia_peso[]" value="${m.peso || '1.0'}" class="w-full input-elegant text-xs text-center">
                    </div>
                    <button type="button" onclick="this.closest('.animate-fade-in').remove()" class="w-10 h-10 flex items-center justify-center rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition shadow-sm border border-rose-100">
                        <i class="fa-solid fa-trash-can text-sm"></i>
                    </button>
                </div>
            `;
            list.appendChild(div);
        }

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

        function renderGabaritoTabs() {
            const tabs = document.getElementById('gabarito-tabs');
            if (!tabs) return;
            const versions = Object.keys(gabaritoData).map(v => parseInt(v)).sort((a,b) => a-b);
            if (versions.length === 0) { gabaritoData[1] = {}; versions.push(1); }
            tabs.innerHTML = versions.map(v => `
                <button type="button" onclick="switchGabaritoVersion(${v})" 
                        class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all ${currentGabaritoVersion == v ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'bg-white text-slate-500 border border-slate-100 hover:bg-slate-50'}">
                    V${v}
                </button>
            `).join('');
            renderGabaritoGrid();
        }

        function renderGabaritoGrid() {
            const grid = document.getElementById('gabarito-respostas-grid');
            if (!grid) return;
            const total = parseInt(document.querySelector('input[name="total_questoes"]').value) || 0;
            const currentAnswers = gabaritoData[currentGabaritoVersion] || {};
            if (total === 0) {
                grid.innerHTML = '<div class="col-span-full py-10 opacity-30 text-center font-bold text-xs">AGUARDANDO TOTAL DE QUESTÕES</div>';
                return;
            }
            let html = '';
            for (let i = 1; i <= total; i++) {
                const val = currentAnswers[i] || '';
                html += `
                    <div class="flex flex-col gap-1">
                        <span class="text-[9px] font-black text-slate-300 ml-1">Q${i}</span>
                        <select onchange="updateGabaritoAnswer(${i}, this.value)" class="input-elegant p-2 text-xs text-center cursor-pointer appearance-none bg-white font-bold ${val ? 'border-emerald-200 bg-emerald-50/30 text-emerald-700' : ''}">
                            <option value="">-</option>
                            ${['A','B','C','D','E','X'].map(alt => `<option value="${alt}" ${val === alt ? 'selected' : ''}>${alt === 'X' ? 'ANUL' : alt}</option>`).join('')}
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
            renderGabaritoGrid();
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
            const el = document.getElementById('gabarito-oficial-json');
            if (el) el.value = JSON.stringify(gabaritoData);
        }

        async function handleGabaritoUpload(input) {
            const file = input.files[0];
            if (!file) return;
            const statusBox = document.getElementById('ai-gabarito-status');
            const statusText = document.getElementById('gabarito-status-text');
            statusBox.classList.remove('hidden');
            try {
                const reader = new FileReader();
                reader.onload = async function() {
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
                        res.data.forEach(v => { gabaritoData[v.version] = v.answers; });
                        currentGabaritoVersion = res.data[0].version;
                        renderGabaritoTabs();
                        Toast.show(`${res.data.length} versões encontradas!`, "success");
                    }
                    statusBox.classList.add('hidden');
                };
                reader.readAsArrayBuffer(file);
            } catch (err) { statusBox.classList.add('hidden'); }
        }

        async function checkDuplicates() {
            const orgao = document.getElementById('nome_orgao').value;
            const banca = document.getElementById('banca').value;
            if (orgao.length < 3 || banca.length < 2) return;
            const alertBox = document.getElementById('duplicate-alert');
            const matchesBox = document.getElementById('duplicate-matches');
            try {
                const response = await fetch(`api/check_duplicate.php?orgao=${encodeURIComponent(orgao)}&banca=${encodeURIComponent(banca)}`);
                const res = await response.json();
                if (res.exists && res.matches.length > 0) {
                    alertBox.classList.remove('hidden');
                    matchesBox.innerHTML = res.matches.map(m => `
                        <a href="colaborar.php?cargo_id=${m.cargo_id}" class="flex items-center justify-between p-3 bg-white hover:bg-indigo-50 rounded-xl border border-slate-100 transition shadow-sm">
                            <div class="text-left">
                                <span class="block text-[10px] font-black text-slate-900 leading-tight">${m.nome_orgao}</span>
                                <span class="text-[8px] text-indigo-500 font-bold uppercase">${m.nome_cargo || 'Geral'}</span>
                            </div>
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-300 ml-4"></i>
                        </a>
                    `).join('');
                } else { alertBox.classList.add('hidden'); }
            } catch (err) {}
        }

        async function loadContestImages() {
            const contestId = "<?php echo $info['concurso_id'] ?? ''; ?>";
            if (!contestId) return;
            const container = document.getElementById('contest-images-container');
            if (!container) return;
            try {
                const response = await fetch(`api/api_concurso_imagem.php?action=listar&concurso_id=${contestId}`);
                const res = await response.json();
                if (res.success && res.data.length > 0) {
                    container.innerHTML = res.data.map((img, idx) => `
                        <div class="shrink-0 w-48 bg-white rounded-2xl border border-slate-100 overflow-hidden flex flex-col group transition shadow-sm">
                            <div class="h-28 w-full relative">
                                <img src="${img.url}" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            </div>
                            <div class="p-2 flex items-center justify-around border-t border-slate-50">
                                <button type="button" onclick="voteImage(${img.id}, 1)" class="p-1 rounded-lg transition ${img.meu_voto == 1 ? 'text-emerald-500' : 'text-slate-300 hover:text-emerald-400'}">
                                    <i class="fa-solid fa-thumbs-up text-xs"></i> <span class="text-[9px] font-bold">${img.votos_positivos}</span>
                                </button>
                                <button type="button" onclick="voteImage(${img.id}, -1)" class="p-1 rounded-lg transition ${img.meu_voto == -1 ? 'text-rose-500' : 'text-slate-300 hover:text-rose-400'}">
                                    <i class="fa-solid fa-thumbs-down text-xs"></i> <span class="text-[9px] font-bold">${img.votos_negativos}</span>
                                </button>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<div class="w-full py-4 text-center opacity-30 text-[9px] font-black uppercase tracking-widest">Nenhuma imagem enviada</div>';
                }
            } catch (err) {}
        }

        async function uploadContestImage(input) {
            const contestId = "<?php echo $info['concurso_id'] ?? ''; ?>";
            const file = input.files[0];
            if (!file || !contestId) return;
            const formData = new FormData();
            formData.append('imagem', file);
            formData.append('concurso_id', contestId);
            Toast.show("Enviando...", "info");
            try {
                const response = await fetch('api/api_concurso_imagem.php?action=upload', { method: 'POST', body: formData });
                const res = await response.json();
                if (res.success) { Toast.show("Sucesso!", "success"); loadContestImages(); }
            } catch (err) {}
        }

        async function voteImage(imgId, voto) {
            const contestId = "<?php echo $info['concurso_id'] ?? ''; ?>";
            try {
                await fetch('api/api_concurso_imagem.php?action=votar&concurso_id=' + contestId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ imagem_id: imgId, voto: voto })
                });
                loadContestImages();
            } catch (err) {}
        }

        document.addEventListener('DOMContentLoaded', () => {
            renderGabaritoTabs();
            const totalInput = document.querySelector('input[name="total_questoes"]');
            if (totalInput) totalInput.addEventListener('input', renderGabaritoGrid);
            loadContestImages();
        });
    </script>

    <?php echo getFooter(); ?>
</body>
</html>
