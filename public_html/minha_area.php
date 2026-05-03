<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui_helper.php';

requireLogin();

$usuario_id = $_SESSION['usuario_id'];
$participacoes = [];

try {
    // Busca as participações do usuário
    $stmt = $pdo->prepare("SELECT ru.*, c.nome_orgao, c.image_url, c.icon, cg.nome_cargo, cg.total_questoes,
                           (SELECT COUNT(*) + 1 FROM respostas_usuarios ru2 WHERE ru2.cargo_id = ru.cargo_id AND ru2.nota_estimada > ru.nota_estimada) as posicao
                           FROM respostas_usuarios ru
                           JOIN cargos cg ON ru.cargo_id = cg.id
                           JOIN concursos c ON cg.concurso_id = c.id
                           WHERE ru.usuario_id = ?
                           ORDER BY ru.criado_em DESC");
    $stmt->execute([$usuario_id]);
    $participacoes = $stmt->fetchAll();
    
    $total_rankings = count($participacoes);
} catch (PDOException $e) {
    // die($e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Área | Open Gabarito</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' },
                        success: { 400: '#4ade80', 500: '#22c55e', 600: '#16a34a' },
                        danger: { 500: '#ef4444', 600: '#dc2626' },
                        slate: { 50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b', 600: '#475569', 700: '#334145', 800: '#1e293b', 900: '#0f172a' }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;900&display=swap');
        body { font-family: 'Outfit', sans-serif; }
        .glass-panel { background: white; border: 1px solid var(--slate-100); }
        .bg-mesh {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(34, 197, 94, 0.05) 0px, transparent 50%);
        }
        .animate-fade-in { animation: fadeIn 0.5s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .card-shadow { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.02), 0 8px 10px -6px rgba(0, 0, 0, 0.02); }
    </style>
</head>
<body class="bg-mesh text-slate-600 min-h-screen pb-20">

    <?php echo getNav(); ?>

    <main class="max-w-5xl mx-auto px-4">
        
        <!-- Profile Hero -->
        <div class="flex flex-col md:flex-row items-center gap-8 mb-12 animate-fade-in bg-white p-8 rounded-[40px] border border-slate-100 card-shadow">
            <div class="relative group">
                <div class="w-28 h-28 rounded-[32px] overflow-hidden bg-slate-50 flex items-center justify-center text-4xl text-slate-300 font-black shadow-lg border-4 border-white transition-transform group-hover:scale-105">
                    <?php 
                    $stmt = $pdo->prepare("SELECT foto_perfil, nickname, trust_score FROM usuarios WHERE id = ?");
                    $stmt->execute([$_SESSION['usuario_id']]);
                    $userData = $stmt->fetch();
                    $foto_area = $userData['foto_perfil'] ?? null;
                    if ($foto_area): 
                    ?>
                        <img src="<?php echo $foto_area; ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-primary-600 text-white font-black uppercase">
                            <?php echo strtoupper(substr($userData['nickname'] ?? $_SESSION['usuario_nome'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <form action="handle_avatar.php" method="POST" enctype="multipart/form-data" id="avatar-form-area" class="absolute -bottom-2 -right-2">
                    <?php echo csrfInput(); ?>
                    <label for="avatar-input-area" class="w-10 h-10 bg-primary-600 hover:bg-primary-500 rounded-2xl flex items-center justify-center cursor-pointer shadow-xl transition-all hover:scale-110 border-2 border-white" title="Trocar foto">
                        <i class="fa-solid fa-camera text-sm text-white"></i>
                    </label>
                    <input type="file" name="avatar" id="avatar-input-area" class="hidden" accept="image/*" onchange="document.getElementById('avatar-form-area').submit()">
                </form>
            </div>
            <div class="text-center md:text-left flex-grow">
                <div class="flex flex-col md:flex-row items-center gap-3 mb-2">
                    <h1 class="text-4xl font-black text-slate-900 tracking-tighter"><?php echo htmlspecialchars($userData['nickname']); ?></h1>
                    <button onclick="toggleEditNickname()" class="text-slate-400 hover:text-primary-600 transition-colors" title="Editar Nickname">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                </div>
                
                <!-- Nickname Edit Form (Hidden by default) -->
                <form id="edit-nickname-form" action="api/update_nickname.php" method="POST" class="hidden mb-4 animate-fade-in">
                    <?php echo csrfInput(); ?>
                    <div class="flex items-center gap-2 max-w-sm mx-auto md:mx-0">
                        <input type="text" name="nickname" value="<?php echo htmlspecialchars($userData['nickname']); ?>" required minlength="3" maxlength="20"
                               class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold text-slate-900 focus:ring-2 focus:ring-primary-500 outline-none w-full">
                        <button type="submit" class="bg-success-600 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-success-500 transition shadow-lg shadow-success-500/20">Salvar</button>
                        <button type="button" onclick="toggleEditNickname()" class="bg-slate-100 text-slate-500 px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-200 transition">Cancelar</button>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-2 italic">*Este nome aparecerá nos rankings públicos para sua privacidade.</p>
                </form>

                <p class="text-slate-500 mb-6 font-medium">Bem-vindo de volta! Aqui está um resumo do seu desempenho.</p>
                <div class="flex flex-wrap justify-center md:justify-start gap-4">
                    <div class="bg-primary-50 border border-primary-100 px-4 py-2 rounded-2xl flex items-center gap-3 shadow-sm">
                        <i class="fa-solid fa-trophy text-primary-500"></i>
                        <span class="text-sm font-bold text-slate-700"><strong class="text-primary-600"><?php echo $total_rankings; ?></strong> Rankings</span>
                    </div>
                    <div class="bg-amber-50 border border-amber-100 px-4 py-2 rounded-2xl flex items-center gap-3 shadow-sm">
                        <i class="fa-solid fa-star text-amber-500"></i>
                        <span class="text-sm font-bold text-slate-700">Trust Score: <strong class="text-amber-600"><?php echo $userData['trust_score']; ?></strong></span>
                    </div>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-4 w-full md:w-auto">
                <a href="novo_gabarito.php" class="bg-primary-600 hover:bg-primary-500 text-white px-8 py-4 rounded-2xl font-bold transition shadow-lg shadow-primary-500/20 flex items-center justify-center gap-3">
                    <i class="fa-solid fa-plus"></i> Novo Gabarito
                </a>
                <a href="colaborar.php" class="bg-white hover:bg-slate-50 text-slate-900 px-8 py-4 rounded-2xl font-bold transition border border-slate-200 flex items-center justify-center gap-3 shadow-sm">
                    <i class="fa-solid fa-folder-plus text-primary-500"></i> Wiki
                </a>
            </div>
        </div>

        <script>
            function toggleEditNickname() {
                const form = document.getElementById('edit-nickname-form');
                form.classList.toggle('hidden');
            }
        </script>

        <!-- Section Title -->
        <div class="flex items-center gap-4 mb-8">
            <h2 class="text-xl font-black text-slate-900 tracking-tight whitespace-nowrap uppercase text-[11px] tracking-widest">Meus Gabaritos Recentes</h2>
            <div class="h-[1px] bg-slate-200 w-full"></div>
        </div>

        <!-- List -->
        <div class="grid grid-cols-1 gap-6">
            <?php if (empty($participacoes)): ?>
                <div class="bg-white rounded-[40px] border border-slate-100 p-20 text-center animate-fade-in card-shadow">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fa-solid fa-ghost text-3xl text-slate-300"></i>
                    </div>
                    <h3 class="text-2xl font-black text-slate-900 mb-2 tracking-tight">Nada por aqui ainda...</h3>
                    <p class="text-slate-500 mb-8 max-w-sm mx-auto">Você ainda não enviou nenhum gabarito. Comece agora e veja sua posição no ranking!</p>
                    <a href="novo_gabarito.php" class="bg-primary-600 hover:bg-primary-500 text-white px-8 py-4 rounded-2xl font-bold transition inline-block shadow-lg shadow-primary-500/20">Enviar Meu Primeiro Gabarito</a>
                </div>
            <?php else: foreach ($participacoes as $idx => $p): ?>
                <div class="bg-white rounded-[32px] p-6 md:p-8 border border-slate-100 flex flex-col md:flex-row justify-between items-center gap-8 animate-fade-in card-shadow transition-all hover:border-primary-100" style="animation-delay: <?php echo $idx * 0.1; ?>s">
                    <div class="flex items-center gap-6 w-full md:w-auto">
                        <div class="h-20 w-20 rounded-2xl bg-slate-50 flex items-center justify-center text-primary-400 border border-slate-100 shrink-0 overflow-hidden">
                            <?php if (!empty($p['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($p['image_url']); ?>" class="w-full h-full object-cover" alt="Logo">
                            <?php else: ?>
                                <i class="fa-solid <?php echo htmlspecialchars($p['icon'] ?? 'fa-file-lines'); ?> text-3xl"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow">
                            <h3 class="text-2xl font-black text-slate-900 mb-1 tracking-tight"><?php echo htmlspecialchars($p['nome_orgao']); ?></h3>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                                <span class="text-sm text-slate-500 font-bold"><?php echo htmlspecialchars($p['nome_cargo']); ?></span>
                                <span class="text-[10px] bg-slate-50 text-slate-400 px-3 py-1 rounded-full font-black uppercase tracking-widest border border-slate-100">
                                    <?php echo date('d/m/Y', strtotime($p['criado_em'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between md:justify-end gap-6 sm:gap-12 w-full md:w-auto border-t md:border-t-0 border-slate-50 pt-6 md:pt-0">
                        <div class="text-center">
                            <div class="text-[10px] text-slate-400 uppercase font-black mb-1 tracking-widest">Posição</div>
                            <div class="text-3xl font-black text-slate-900"><?php echo $p['posicao']; ?>º</div>
                        </div>
                        <div class="text-center">
                            <div class="text-[10px] text-slate-400 uppercase font-black mb-1 tracking-widest">Nota Est.</div>
                            <div class="text-3xl font-black text-success-600"><?php echo number_format($p['nota_estimada'] ?? 0, 1); ?></div>
                        </div>
                        <div class="flex flex-row md:flex-col gap-3">
                            <a href="ranking.php?cargo_id=<?php echo $p['cargo_id']; ?>" class="bg-primary-600 hover:bg-primary-500 text-white w-12 h-12 rounded-2xl flex items-center justify-center transition shadow-lg shadow-primary-500/20" title="Ver Ranking">
                                <i class="fa-solid fa-trophy text-sm"></i>
                            </a>
                            <a href="novo_gabarito.php?cargo_id=<?php echo $p['cargo_id']; ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-600 w-12 h-12 rounded-2xl flex items-center justify-center transition" title="Editar Gabarito">
                                <i class="fa-solid fa-pen-to-square text-sm"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </main>

    <!-- Botão de Sugestões Flutuante -->
    <a href="colaborar.php" class="fixed bottom-8 right-8 bg-primary-600 hover:bg-primary-500 text-white w-16 h-16 rounded-full flex items-center justify-center shadow-2xl shadow-primary-500/40 transition-all hover:scale-110 z-[100] group">
        <i class="fa-solid fa-lightbulb text-2xl"></i>
        <span class="absolute right-full mr-4 bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest px-4 py-2 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">Sugerir Melhoria</span>
    </a>

<?php echo getFooter(); ?>
</body>
</html>