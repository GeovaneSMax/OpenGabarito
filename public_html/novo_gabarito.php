<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_logic.php';
require_once __DIR__ . '/../includes/groq_api.php';
require_once __DIR__ . '/../includes/ui_helper.php';

requireLogin(); // Exige que o usuário esteja logado

$concursos = [];
try {
    $stmt = $pdo->query("SELECT c.*, cg.id as cargo_id, cg.nome_cargo, cg.total_questoes 
                         FROM concursos c 
                         JOIN cargos cg ON c.id = cg.concurso_id
                         WHERE c.deleted_at IS NULL AND cg.deleted_at IS NULL");
    $concursos = $stmt->fetchAll();

    // Buscar respostas anteriores do usuário logado para pre-preenchimento
    $stmt = $pdo->prepare("SELECT cargo_id, respostas_json, versao, modalidade FROM respostas_usuarios WHERE usuario_id = ? AND deleted_at IS NULL");
    $stmt->execute([$_SESSION['usuario_id']]);
    $respostas_anteriores = $stmt->fetchAll(PDO::FETCH_UNIQUE);
} catch (PDOException $e) {}

$sucesso = '';
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    validateCSRF();
    
    // Honeypot
    if (!empty($_POST['website'])) die("Acesso negado.");

    $cargo_id = $_POST['cargo_id'] ?? '';
    $respostas = $_POST['q'] ?? []; // Array de respostas [1 => 'A', 2 => 'B'...]
    $versao = $_POST['versao'] ?? 0;
    $modalidade = $_POST['modalidade'] ?? 'ampla';
    
    // Validação de Preenchimento Completo
    $stmt = $pdo->prepare("SELECT total_questoes FROM cargos WHERE id = ?");
    $stmt->execute([$cargo_id]);
    $total_esperado = $stmt->fetchColumn();

    if ($cargo_id && count($respostas) >= $total_esperado) {
        try {
            $pdo->beginTransaction();

            // Validação de Versão Inteligente
            $versao = detectarVersao($pdo, $cargo_id, $respostas, $versao);
            
            // Sanitização e UpperCase
            $respostas_limpas = array_map(function($val) {
                return strtoupper(trim($val));
            }, $respostas);

            $respostas_json = json_encode($respostas_limpas);
            $usuario_id = $_SESSION['usuario_id'];

            // Calcula nota estimada baseada no consenso atual e regras de peso
            $resultado = calcularNotaEstimada($pdo, $cargo_id, $versao, $respostas_limpas);
            $nota_estimada = $resultado['nota'] ?? 0;
            $status_eliminado = $resultado['eliminado'] ? 1 : 0;

            $stmt = $pdo->prepare("INSERT INTO respostas_usuarios (usuario_id, cargo_id, respostas_json, versao, modalidade, nota_estimada, status_eliminado, is_suspicious, completion_time_seconds, deleted_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)
                                   ON DUPLICATE KEY UPDATE 
                                   respostas_json = VALUES(respostas_json),
                                   versao = VALUES(versao),
                                   modalidade = VALUES(modalidade),
                                   nota_estimada = VALUES(nota_estimada),
                                   status_eliminado = VALUES(status_eliminado),
                                   is_suspicious = VALUES(is_suspicious),
                                   completion_time_seconds = VALUES(completion_time_seconds),
                                   deleted_at = NULL");
            $stmt->execute([$usuario_id, $cargo_id, $respostas_json, $versao, $modalidade, $nota_estimada, $status_eliminado, $is_suspicious, $duration]);

            // Atualiza o consenso global para este cargo
            atualizarConsenso($pdo, $cargo_id);
            
            // Dispara Predição de IA (PNC e Acurácia)
            atualizarPredicoesIA($pdo, $cargo_id);

            $pdo->commit();
            $sucesso = "Gabarito enviado com sucesso! Versão detectada: $versao. Acompanhe o ranking.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = "Erro ao salvar gabarito: " . $e->getMessage();
        }
    } else {
        $erro = "Atenção: Você precisa preencher todas as " . ($total_esperado ?? "60") . " questões para salvar.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Gabarito | OpenGabarito</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;900&display=swap" rel="stylesheet">
    <script src="assets/js/toasts.js"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass-panel { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(226, 232, 240, 0.5); }
        input[type="radio"]:checked + label { background-color: #4f46e5; color: white; border-color: #4f46e5; }
    </style>
</head>
<body class="bg-slate-50 text-slate-600 min-h-screen pb-20">
    
    <nav class="glass-panel sticky top-0 z-50 mb-10 border-b border-slate-100/50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <?php echo getLogoSVG(36); ?>
                <span class="font-black text-slate-900 tracking-tighter text-xl leading-none">Open<span class="text-indigo-600">Gabarito</span></span>
            </a>
            <a href="index.php" class="text-xs font-black text-slate-400 hover:text-slate-900 transition flex items-center gap-2 uppercase tracking-widest">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4">
        <div class="bg-white rounded-[40px] p-8 md:p-12 shadow-2xl shadow-indigo-500/5 border border-slate-100">
            <h1 class="text-4xl font-black text-slate-900 mb-2 tracking-tight">Enviar Gabarito</h1>
            <p class="text-slate-500 font-medium mb-10 leading-relaxed">Preencha suas respostas conforme o seu caderno de provas para calcular sua nota.</p>

            <?php if ($sucesso): ?>
                <div class="bg-emerald-50 border border-emerald-100 text-emerald-600 p-6 rounded-[24px] mb-8 font-bold flex items-center gap-3 shadow-sm">
                    <i class="fa-solid fa-circle-check text-xl"></i>
                    <?php echo $sucesso; ?>
                </div>
            <?php endif; ?>
            <?php if ($erro): ?>
                <div class="bg-rose-50 border border-rose-100 text-rose-600 p-6 rounded-[24px] mb-8 font-bold flex items-center gap-3 shadow-sm">
                    <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                    <?php echo $erro; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="website" value="">
                <input type="hidden" name="start_time" value="<?php echo time(); ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 ml-1">Encontre o Concurso / Cargo</label>
                        <div class="relative group mb-4">
                            <i class="fa-solid fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-indigo-600 transition-colors"></i>
                            <input type="text" id="filtro-concurso" placeholder="Digite para buscar (ex: Correios, INSS, Analista...)" class="w-full bg-slate-50 border border-slate-100 rounded-2xl pl-12 pr-6 py-4 text-slate-900 focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-500 outline-none transition-all shadow-sm font-medium">
                        </div>
                        <select name="cargo_id" id="select-cargo" required class="w-full bg-white border border-slate-200 rounded-2xl px-6 py-4 text-slate-900 focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-500 outline-none shadow-sm font-bold appearance-none cursor-pointer">
                            <option value="">Escolha um concurso da lista filtrada...</option>
                            <?php foreach ($concursos as $c): ?>
                                <option value="<?php echo $c['cargo_id']; ?>" data-search="<?php echo strtolower($c['nome_orgao'] . ' ' . $c['nome_cargo']); ?>" <?php echo (isset($_GET['cargo_id']) && $_GET['cargo_id'] == $c['cargo_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nome_orgao'] . " - " . $c['nome_cargo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <script>
                        document.getElementById('filtro-concurso').addEventListener('input', function(e) {
                            const termo = e.target.value.toLowerCase();
                            const options = document.getElementById('select-cargo').options;
                            
                            for (let i = 1; i < options.length; i++) {
                                const texto = options[i].getAttribute('data-search') || '';
                                if (texto.includes(termo)) {
                                    options[i].style.display = 'block';
                                } else {
                                    options[i].style.display = 'none';
                                }
                            }
                        });
                    </script>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 ml-1">Versão da Prova</label>
                        <select name="versao" class="w-full bg-white border border-slate-200 rounded-2xl px-6 py-4 text-slate-900 focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-500 outline-none shadow-sm font-bold appearance-none cursor-pointer">
                            <option value="1">Versão 1 (Azul / Branca)</option>
                            <option value="2">Versão 2 (Amarela / Verde)</option>
                            <option value="3">Versão 3 (Rosa / Cinza)</option>
                            <option value="4">Versão 4 (Preta / Marrom)</option>
                            <option value="0">Não sei / Detectar Automaticamente</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 ml-1">Modalidade da Vaga</label>
                        <select name="modalidade" required class="w-full bg-white border border-slate-200 rounded-2xl px-6 py-4 text-slate-900 focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-500 outline-none shadow-sm font-bold appearance-none cursor-pointer">
                            <option value="ampla">Ampla Concorrência</option>
                            <option value="pcd">PCD (Pessoa com Deficiência)</option>
                            <option value="ppp">PPP (Negros / Pardos)</option>
                        </select>
                    </div>
                </div>

                <div id="progresso-container" class="hidden mb-4 bg-slate-100 rounded-full h-4 overflow-hidden shadow-inner">
                    <div id="progresso-bar" class="bg-indigo-600 h-full transition-all duration-700 ease-out" style="width: 0%"></div>
                </div>
                <div id="progresso-texto" class="hidden mb-12 text-[10px] font-black text-indigo-600 uppercase tracking-widest text-center">
                    Aguardando preenchimento...
                </div>

                <div id="questions-container" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
                    <!-- Gerado via JS -->
                    <div class="col-span-full py-12 text-center text-slate-500">
                        <i class="fa-solid fa-arrow-up mb-4 text-3xl block"></i>
                        Selecione um concurso acima para carregar as questões.
                    </div>
                </div>

                <script>
                    const concursos = <?php echo json_encode($concursos); ?>;
                    const respostasAnteriores = <?php echo json_encode($respostas_anteriores); ?>;
                    const container = document.getElementById('questions-container');
                    
                    function atualizarProgresso() {
                        const inputs = document.querySelectorAll('.q-input');
                        const total = inputs.length;
                        const preenchidos = Array.from(inputs).filter(i => i.value.trim() !== '').length;
                        const percent = (preenchidos / total) * 100;
                        
                        document.getElementById('progresso-bar').style.width = percent + '%';
                        document.getElementById('progresso-texto').innerText = `${preenchidos} de ${total} questões preenchidas`;
                        
                        if (preenchidos === total) {
                            document.getElementById('progresso-bar').classList.remove('bg-indigo-500');
                            document.getElementById('progresso-bar').classList.add('bg-emerald-500');
                            document.getElementById('btn-submit').disabled = false;
                            document.getElementById('btn-submit').classList.remove('opacity-50', 'cursor-not-allowed');
                        } else {
                            document.getElementById('progresso-bar').classList.remove('bg-emerald-500');
                            document.getElementById('progresso-bar').classList.add('bg-indigo-500');
                        }
                    }

                    document.querySelector('select[name="cargo_id"]').addEventListener('change', function(e) {
                        const cargoId = e.target.value;
                        const cargo = concursos.find(c => c.cargo_id == cargoId);
                        const anterior = respostasAnteriores[cargoId] || null;
                        
                        if (!cargo) {
                            container.innerHTML = '<div class="col-span-full py-12 text-center text-slate-500">Selecione um concurso...</div>';
                            document.getElementById('progresso-container').classList.add('hidden');
                            document.getElementById('progresso-texto').classList.add('hidden');
                            return;
                        }

                        document.getElementById('progresso-container').classList.remove('hidden');
                        document.getElementById('progresso-texto').classList.remove('hidden');

                        if (anterior) {
                            document.querySelector('select[name="versao"]').value = anterior.versao;
                            document.querySelector('select[name="modalidade"]').value = anterior.modalidade;
                            Toast.show("Carregamos suas respostas anteriores.", "info");
                        }

                        const respMap = anterior ? JSON.parse(anterior.respostas_json) : {};

                        let html = '';
                        for (let i = 1; i <= cargo.total_questoes; i++) {
                            const selecionada = respMap[i] || '';
                            html += `
                                <div class="bg-white border border-slate-100 rounded-2xl p-4 flex flex-col items-center gap-2 group hover:border-indigo-200 transition-all shadow-sm">
                                    <span class="text-[10px] font-black text-slate-400 group-hover:text-indigo-600 transition-colors tracking-tighter uppercase">Questão ${i}</span>
                                    <input type="text" 
                                           name="q[${i}]" 
                                           maxlength="1" 
                                           value="${selecionada}"
                                           data-index="${i}"
                                           autocomplete="off"
                                           placeholder="-"
                                           class="q-input w-full bg-slate-50 border border-slate-100 rounded-xl text-center font-black text-slate-900 uppercase focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-600 outline-none py-3 text-xl transition-all">
                                </div>
                            `;
                        }
                        container.innerHTML = html;
                        atualizarProgresso();

                        // Eventos para Auto-Focus e Validação
                        document.querySelectorAll('.q-input').forEach(input => {
                            input.addEventListener('input', function(e) {
                                this.value = this.value.toUpperCase().replace(/[^A-EX]/g, '');
                                
                                if (this.value.length === 1) {
                                    const next = document.querySelector(`.q-input[data-index="${parseInt(this.dataset.index) + 1}"]`);
                                    if (next) next.focus();
                                }
                                atualizarProgresso();
                            });

                            input.addEventListener('keydown', function(e) {
                                if (e.key === 'Backspace' && this.value === '') {
                                    const prev = document.querySelector(`.q-input[data-index="${parseInt(this.dataset.index) - 1}"]`);
                                    if (prev) prev.focus();
                                }
                            });
                        });
                    });

                    // Trigger inicial se já houver cargo_id no select (vindo do GET ou pre-selecionado)
                    const initialCargo = document.querySelector('select[name="cargo_id"]').value;
                    if (initialCargo) {
                        document.querySelector('select[name="cargo_id"]').dispatchEvent(new Event('change'));
                    }

                    <?php if ($sucesso): ?>
                        window.onload = () => Toast.show("<?php echo $sucesso; ?>", "success");
                    <?php endif; ?>
                    <?php if ($erro): ?>
                        window.onload = () => Toast.show("<?php echo $erro; ?>", "error");
                    <?php endif; ?>
                </script>

                <div class="mt-16 flex flex-col items-center gap-6">
                    <button type="submit" id="btn-submit" disabled class="w-full md:w-auto bg-emerald-600 hover:bg-emerald-500 text-white px-16 py-6 rounded-[32px] font-black uppercase tracking-widest transition-all shadow-xl shadow-emerald-500/20 opacity-50 cursor-not-allowed text-lg">
                        <i class="fa-solid fa-cloud-arrow-up mr-2"></i> Finalizar & Enviar Gabarito
                    </button>
                    <p class="text-[10px] text-slate-400 font-medium italic text-center max-w-sm">
                        * Ao finalizar, seu gabarito será computado no ranking e a IA processará seu resultado imediatamente.
                    </p>
                </div>
            </form>
        </div>
    </main>

<?php echo getFooter(); ?>
</body>
</html>