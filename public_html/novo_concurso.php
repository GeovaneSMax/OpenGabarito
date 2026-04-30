<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_orgao = $_POST['nome_orgao'] ?? '';
    $banca = $_POST['banca'] ?? '';
    $nome_cargo = $_POST['nome_cargo'] ?? '';
    $vagas = (int)($_POST['vagas'] ?? 0);
    $inscritos = (int)($_POST['inscritos'] ?? 0);
    $total_questoes = (int)($_POST['total_questoes'] ?? 60);

    if ($nome_orgao && $banca && $nome_cargo) {
        try {
            // Verifica se já existe um concurso com esse órgão e banca
            $stmt = $pdo->prepare("SELECT id FROM concursos WHERE nome_orgao = ? AND banca = ?");
            $stmt->execute([$nome_orgao, $banca]);
            $concurso = $stmt->fetch();

            if ($concurso) {
                $concurso_id = $concurso['id'];
                // Verifica se já existe o cargo para esse concurso
                $stmt = $pdo->prepare("SELECT id FROM cargos WHERE concurso_id = ? AND nome_cargo = ?");
                $stmt->execute([$concurso_id, $nome_cargo]);
                if ($stmt->fetch()) {
                    $erro = "Este cargo já está cadastrado para este concurso.";
                }
            } else {
                // Cria o concurso
                $stmt = $pdo->prepare("INSERT INTO concursos (nome_orgao, banca, status) VALUES (?, ?, 'aberto')");
                $stmt->execute([$nome_orgao, $banca]);
                $concurso_id = $pdo->lastInsertId();
            }

            if (!$erro) {
                // Cria o cargo
                $stmt = $pdo->prepare("INSERT INTO cargos (concurso_id, nome_cargo, vagas, inscritos, total_questoes) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$concurso_id, $nome_cargo, $vagas, $inscritos, $total_questoes]);
                $sucesso = "Concurso/Cargo cadastrado com sucesso!";
            }
        } catch (PDOException $e) {
            $erro = "Erro ao cadastrar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha os campos obrigatórios.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Concurso | OpenGabarito</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="assets/js/toasts.js"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass-panel { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(226, 232, 240, 0.5); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.5s ease-out forwards; }
    </style>
</head>
<body class="bg-slate-50 text-slate-600 min-h-screen pb-20">
    
    <nav class="glass-panel sticky top-0 z-50 mb-10 border-b border-slate-100/50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <?php echo getLogoSVG(36); ?>
                <span class="font-black text-slate-900 tracking-tighter text-xl">Open<span class="text-indigo-600">Gabarito</span></span>
            </a>
            <a href="index.php" class="text-xs font-black text-slate-400 hover:text-slate-900 transition flex items-center gap-2 uppercase tracking-widest">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 py-12">
        <div class="animate-fade-in space-y-10">
            <!-- Cabeçalho -->
            <!-- Cabeçalho -->
            <div class="text-center">
                <h1 class="text-5xl font-black text-slate-900 mb-4 tracking-tighter leading-tight">Novo Concurso</h1>
                <p class="text-slate-500 font-medium leading-relaxed">Cadastre um novo concurso e cargo na plataforma para iniciar o ranking.</p>
            </div>

            <!-- Mágica IA (Centralizada) -->
            <div class="bg-indigo-50/50 rounded-[40px] p-10 border-2 border-indigo-100 border-dashed relative overflow-hidden">
                <div class="flex flex-col md:flex-row items-center justify-between gap-8 relative z-10">
                    <div class="flex items-center gap-6">
                        <div class="w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center text-white text-3xl shadow-xl shadow-indigo-500/20">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                        </div>
                        <div>
                            <h3 class="text-slate-900 font-black text-xl mb-1">Mágica IA</h3>
                            <p class="text-indigo-600 text-[10px] uppercase font-black tracking-[0.2em]">Importar do PDF do Edital</p>
                        </div>
                    </div>
                    <button type="button" onclick="document.getElementById('edital-upload').click()" id="ai-import-btn" class="w-full md:w-auto bg-white hover:bg-indigo-600 hover:text-white text-indigo-600 px-8 py-5 rounded-[24px] text-xs font-black transition-all flex items-center justify-center gap-3 shadow-xl shadow-indigo-500/5 border border-indigo-100">
                        <i class="fa-solid fa-file-pdf"></i> SELECIONAR EDITAL
                    </button>
                    <input type="file" id="edital-upload" accept="application/pdf" class="hidden" onchange="handleEditalUpload(this)">
                </div>

                <!-- Status da IA -->
                <div id="ai-status-bar" class="hidden mt-8 bg-white/80 backdrop-blur-md rounded-3xl p-6 border border-emerald-100 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span id="ai-status-text" class="text-emerald-600 text-[10px] font-black uppercase tracking-widest">Iniciando análise...</span>
                        <span id="ai-status-percent" class="text-emerald-600 text-[10px] font-black">0%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden shadow-inner">
                        <div id="ai-status-progress" class="bg-emerald-500 h-full w-0 transition-all duration-700 ease-out"></div>
                    </div>
                </div>
            </div>

            <?php if ($erro): ?>
                <div class="bg-rose-50 border border-rose-100 text-rose-600 p-8 rounded-[32px] font-bold flex items-center gap-4 shadow-sm">
                    <i class="fa-solid fa-triangle-exclamation text-xl"></i> <?php echo $erro; ?>
                </div>
            <?php endif; ?>
            <?php if ($sucesso): ?>
                <div class="bg-emerald-50 border border-emerald-100 text-emerald-600 p-8 rounded-[32px] font-bold flex items-center gap-4 shadow-sm">
                    <i class="fa-solid fa-circle-check text-xl"></i> <?php echo $sucesso; ?>
                </div>
            <?php endif; ?>

            <!-- Aviso de Beta/IA -->
            <div class="bg-amber-50 rounded-[32px] p-8 border border-amber-100 shadow-sm">
                <div class="flex items-center gap-6">
                    <div class="w-14 h-14 bg-white text-amber-500 rounded-2xl flex items-center justify-center text-2xl shadow-sm border border-amber-100">
                        <i class="fa-solid fa-flask-vial"></i>
                    </div>
                    <div>
                        <h4 class="text-amber-700 text-[11px] font-black uppercase tracking-widest mb-1">IA em Fase de Testes</h4>
                        <p class="text-amber-600/80 text-xs font-medium leading-relaxed">A tecnologia de extração de editais via PDF utiliza IA experimental. Revise cuidadosamente todos os campos antes de salvar.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[40px] p-8 md:p-12 shadow-2xl shadow-indigo-500/5 border border-slate-100">
                <form method="POST" class="space-y-10">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Órgão Público</label>
                            <input type="text" name="nome_orgao" required placeholder="Ex: Correios, PF, INSS" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-slate-900 focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-500 outline-none transition-all font-bold">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Banca Organizadora</label>
                            <input type="text" name="banca" required placeholder="Ex: FGV, Cebraspe" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-slate-900 focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-500 outline-none transition-all font-bold">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Nome do Cargo</label>
                            <input type="text" name="nome_cargo" required placeholder="Ex: Técnico Administrativo" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-slate-900 focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-500 outline-none transition-all font-bold">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Total de Vagas</label>
                            <input type="number" name="vagas" placeholder="0" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-slate-900 focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-500 outline-none font-bold">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Inscritos Estimados</label>
                            <input type="number" name="inscritos" placeholder="0" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-slate-900 focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-500 outline-none font-bold">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Nº de Questões</label>
                            <input type="number" name="total_questoes" value="60" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-slate-900 focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-500 outline-none font-bold">
                        </div>
                    </div>

                    <div class="pt-8 border-t border-slate-100">
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-black py-6 rounded-[32px] transition-all shadow-xl shadow-indigo-500/20 text-lg flex items-center justify-center gap-3 uppercase tracking-widest">
                            <i class="fa-solid fa-plus-circle"></i> Cadastrar Concurso & Cargo
                        </button>
                    </div>
                </form>
            </div>
        </div>
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
            btn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin"></i> ...';
            
            statusBar.classList.remove('hidden');
            updateStatus(20, "Lendo PDF...");

            try {
                const reader = new FileReader();
                reader.onload = async function() {
                    try {
                        const typedarray = new Uint8Array(this.result);
                        const pdf = await pdfjsLib.getDocument(typedarray).promise;
                        let fullText = "";
                        
                        updateStatus(40, `Analisando ${pdf.numPages} pgs...`);

                        const maxPages = 50;
                        for (let i = 1; i <= Math.min(pdf.numPages, maxPages); i++) {
                            const page = await pdf.getPage(i);
                            const content = await page.getTextContent();
                            fullText += content.items.map(item => item.str).join(" ");
                        }
                        fullText = fullText.replace(/\s+/g, ' ').trim();

                        if (pdf.numPages > maxPages) {
                            for (let i = Math.max(maxPages + 1, pdf.numPages - 5); i <= pdf.numPages; i++) {
                                const page = await pdf.getPage(i);
                                const content = await page.getTextContent();
                                fullText += content.items.map(item => item.str).join(" ");
                            }
                            fullText = fullText.replace(/\s+/g, ' ').trim();
                        }

                        updateStatus(60, "IA Extraindo...");

                        const response = await fetch('api/api_ai_parse_edital.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ text: fullText })
                        });
                        
                        updateStatus(80, "IA Finalizando...");
                        const res = await response.json();

                        if (res.success) {
                            updateStatus(100, "Pronto!");
                            const data = res.data;
                            
                            const setVal = (name, val) => {
                                const el = document.getElementsByName(name)[0];
                                if (el) el.value = val;
                            };

                            if (data.nome_orgao) setVal('nome_orgao', data.nome_orgao);
                            if (data.banca) setVal('banca', data.banca);
                            if (data.nome_cargo) setVal('nome_cargo', data.nome_cargo);
                            if (data.total_questoes) setVal('total_questoes', data.total_questoes);
                            
                            // Vagas
                            if (data.vagas) {
                                if (data.vagas.ampla) setVal('vagas', data.vagas.ampla);
                            }

                            Toast.show("Dados importados do edital!", "success");
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
            }

            function updateStatus(pct, text) {
                statusProgress.style.width = pct + '%';
                statusPercent.innerText = pct + '%';
                statusText.innerText = text;
            }

            function handleError(err) {
                Toast.show(err.message || "Erro ao processar PDF.", "error");
                statusBar.classList.add('hidden');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    </script>
</body>
</html>
