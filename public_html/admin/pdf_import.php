<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helper.php';

requireAdmin();

$cargo_id = $_GET['cargo_id'] ?? '';
$cargos = $pdo->query("SELECT cg.id, c.nome_orgao, cg.nome_cargo FROM cargos cg JOIN concursos c ON cg.concurso_id = c.id WHERE cg.deleted_at IS NULL AND c.deleted_at IS NULL ORDER BY c.nome_orgao ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <title>Importador IA de PDF | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="toasts.js"></script>
    <style>
        body { background: #0f172a; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .loading-shimmer { background: linear-gradient(90deg, #1e293b 25%, #334155 50%, #1e293b 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    </style>
</head>
<body class="min-h-screen py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-10">
            <div>
                <h1 class="text-3xl font-black flex items-center gap-3">
                    <i class="fa-solid fa-file-pdf text-rose-500"></i>
                    Importador IA de Gabaritos
                </h1>
                <p class="text-slate-400">Arraste o PDF e deixe a IA detectar as respostas automaticamente</p>
            </div>
            <a href="dashboard.php" class="text-slate-500 hover:text-white transition">Voltar</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Coluna de Upload -->
            <div class="md:col-span-1 space-y-6">
                <div class="glass-panel rounded-3xl p-6">
                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-4 tracking-widest">1. Selecionar Cargo</label>
                    <select id="cargo_id" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Escolha...</option>
                        <?php foreach ($cargos as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $cargo_id == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo e($c['nome_orgao'] . " - " . $c['nome_cargo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="drop-zone" class="glass-panel rounded-3xl p-8 border-2 border-dashed border-slate-700 flex flex-col items-center justify-center text-center cursor-pointer hover:border-indigo-500 transition-all group">
                    <i class="fa-solid fa-cloud-arrow-up text-4xl text-slate-600 mb-4 group-hover:text-indigo-400 transition"></i>
                    <p class="text-sm font-bold text-slate-400">Arraste aqui ou</p>
                    <button type="button" class="mt-4 bg-indigo-600/20 text-indigo-400 px-4 py-2 rounded-xl font-bold text-xs hover:bg-indigo-600 hover:text-white transition">
                        Selecionar Arquivo PDF
                    </button>
                    <input type="file" id="pdf-file" accept="application/pdf" class="hidden">
                </div>
            </div>

            <!-- Coluna de Preview -->
            <div class="md:col-span-2">
                <div id="result-panel" class="glass-panel rounded-3xl p-8 min-h-[400px] flex flex-col">
                    <div id="idle-state" class="flex-1 flex flex-col items-center justify-center text-slate-500">
                        <i class="fa-solid fa-brain text-5xl mb-4 opacity-20"></i>
                        <p>Aguardando processamento...</p>
                    </div>

                    <div id="loading-state" class="hidden flex-1 flex flex-col items-center justify-center space-y-4">
                        <div class="h-12 w-12 border-4 border-indigo-500/20 border-t-indigo-500 rounded-full animate-spin"></div>
                        <p class="text-indigo-400 font-bold animate-pulse">IA analisando o PDF...</p>
                    </div>

                    <div id="success-state" class="hidden flex-1 flex flex-col">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                                <i class="fa-solid fa-check-circle text-emerald-500"></i>
                                Extração Concluída
                            </h3>
                            <div class="bg-indigo-500/20 text-indigo-400 px-3 py-1 rounded-full text-xs font-black uppercase tracking-widest" id="detected-version">
                                Versão Detectada
                            </div>
                        </div>
                        
                        <div id="answers-grid" class="grid grid-cols-5 gap-3 max-h-[300px] overflow-y-auto p-4 bg-slate-900/50 rounded-2xl mb-6">
                            <!-- JS Generated -->
                        </div>

                        <button id="save-btn" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black py-4 rounded-2xl transition shadow-xl shadow-emerald-500/20">
                            Salvar como Gabarito Oficial
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('pdf-file');
        const idleState = document.getElementById('idle-state');
        const loadingState = document.getElementById('loading-state');
        const successState = document.getElementById('success-state');
        const answersGrid = document.getElementById('answers-grid');
        const detectedVersion = document.getElementById('detected-version');

        let extractedData = null;

        // Prevent browser default drop behavior
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        dropZone.addEventListener('dragover', () => dropZone.classList.add('border-indigo-500'));
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-indigo-500'));

        dropZone.addEventListener('drop', (e) => {
            dropZone.classList.remove('border-indigo-500');
            const file = e.dataTransfer.files[0];
            handleFile(file);
        });

        dropZone.onclick = () => fileInput.click();

        fileInput.onchange = (e) => handleFile(e.target.files[0]);

        async function handleFile(file) {
            if (!file || file.type !== 'application/pdf') {
                Toast.show("Por favor, selecione um arquivo PDF.", "error");
                return;
            }

            const cargoId = document.getElementById('cargo_id').value;
            if (!cargoId) {
                Toast.show("Selecione o cargo primeiro.", "error");
                return;
            }

            idleState.classList.add('hidden');
            loadingState.classList.remove('hidden');
            successState.classList.add('hidden');

            try {
                // 1. Extrair Texto do PDF (Client-Side)
                const reader = new FileReader();
                reader.onload = async function() {
                    const typedarray = new Uint8Array(this.result);
                    const pdf = await pdfjsLib.getDocument(typedarray).promise;
                    let fullText = "";
                    
                    for (let i = 1; i <= pdf.numPages; i++) {
                        const page = await pdf.getPage(i);
                        const content = await page.getTextContent();
                        fullText += content.items.map(item => item.str).join(" ");
                    }

                    // 2. Enviar para IA processar
                    processWithAI(fullText);
                };
                reader.readAsArrayBuffer(file);
            } catch (err) {
                console.error(err);
                Toast.show("Erro ao ler PDF.", "error");
                resetUI();
            }
        }

        async function processWithAI(text) {
            try {
                const response = await fetch('../api/api_ai_parse_gabarito.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ text: text })
                });
                
                const res = await response.json();
                
                if (res.success) {
                    extractedData = res.data;
                    renderAnswers(res.data);
                } else {
                    throw new Error(res.error || "IA falhou ao processar.");
                }
            } catch (err) {
                Toast.show(err.message, "error");
                resetUI();
            }
        }

        function renderAnswers(data) {
            loadingState.classList.add('hidden');
            successState.classList.remove('hidden');
            
            detectedVersion.innerText = "Versão Detectada: " + data.version;
            
            let html = "";
            Object.entries(data.answers).sort((a, b) => a[0] - b[0]).forEach(([q, a]) => {
                html += `
                    <div class="flex items-center justify-between bg-slate-800 p-2 rounded-lg border border-slate-700">
                        <span class="text-[10px] text-slate-500 font-bold">Q${q}</span>
                        <span class="text-white font-black">${a}</span>
                    </div>
                `;
            });
            answersGrid.innerHTML = html;
        }

        function resetUI() {
            idleState.classList.remove('hidden');
            loadingState.classList.add('hidden');
            successState.classList.add('hidden');
        }

        document.getElementById('save-btn').onclick = async () => {
            const cargoId = document.getElementById('cargo_id').value;
            if (!extractedData || !cargoId) return;

            try {
                const response = await fetch('save_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cargo_id: cargoId,
                        versao: extractedData.version,
                        respostas: extractedData.answers,
                        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                    })
                });

                const res = await response.json();
                if (res.success) {
                    Toast.show("Gabarito Oficial salvo e ranking atualizado!", "success");
                    setTimeout(() => location.href = "dashboard.php", 2000);
                } else {
                    Toast.show(res.error, "error");
                }
            } catch (err) {
                Toast.show("Erro ao salvar.", "error");
            }
        };
    </script>
</body>
</html>
