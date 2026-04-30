<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui_helper.php';

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    validateCSRF();

    // Honeypot
    if (!empty($_POST['website'])) {
        die("Acesso negado.");
    }

    // Turnstile
    if (!validateTurnstile($_POST['cf-turnstile-response'] ?? '')) {
        $erro = "Falha na verificação de robô. Tente novamente.";
    }

    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($nome && $email && $telefone && $senha && !$erro) {
        try {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, telefone, senha_hash, whatsapp_verificado) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$nome, $email, $telefone, $senha_hash]);
            $user_id = $pdo->lastInsertId();

            // Login Automático
            $_SESSION['usuario_id'] = $user_id;
            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['role'] = 'user';

            header("Location: index.php?login=success");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $erro = "Este e-mail já está cadastrado.";
            } else {
                $erro = "Erro ao criar conta: " . $e->getMessage();
            }
        }
    } else {
        $erro = "Preencha todos os campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro | OpenGabarito</title>
    <link rel="icon" href="data:image/svg+xml,<?php echo rawurlencode(getLogoSVG(40)); ?>">
    
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="assets/js/toasts.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;900&display=swap');
        body { font-family: 'Outfit', sans-serif; }
        .bg-mesh {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.08) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.08) 0px, transparent 50%);
        }
        .glass-panel { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(226, 232, 240, 1); }
    </style>
</head>
<body class="bg-mesh text-slate-600 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full glass-panel rounded-[40px] p-10 shadow-2xl shadow-indigo-500/5 my-8">
        <div class="text-center mb-10">
            <a href="index.php" class="inline-flex flex-col items-center gap-4 group">
                <div class="h-16 w-16 group-hover:scale-110 transition-transform">
                    <?php echo getLogoSVG(64); ?>
                </div>
                <span class="text-3xl font-black text-slate-900 tracking-tighter">Open<span class="text-indigo-600">Gabarito</span></span>
            </a>
            <h1 class="text-2xl font-black text-slate-900 mt-6 tracking-tight">Crie sua conta</h1>
            <p class="text-slate-500 mt-2 font-medium">Faça parte da maior wiki de concursos</p>
        </div>

        <?php if ($erro): ?>
            <div class="bg-rose-50 border border-rose-100 text-rose-600 p-4 rounded-2xl text-sm mb-6 flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span class="font-bold"><?php echo $erro; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <?php echo csrfInput(); ?>
            <!-- Honeypot -->
            <div style="display:none;">
                <input type="text" name="website" value="">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">Nome Completo</label>
                <input type="text" name="nome" required class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-slate-900 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">E-mail</label>
                <input type="email" name="email" required class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-slate-900 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">WhatsApp (DDD + Número)</label>
                <input type="text" name="telefone" required placeholder="11999999999" class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-slate-900 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">Senha</label>
                <input type="password" name="senha" required class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-slate-900 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium">
            </div>

            <!-- Turnstile Widget -->
            <div class="cf-turnstile flex justify-center py-2" data-sitekey="<?php echo TURNSTILE_SITE_KEY; ?>" data-theme="light" data-appearance="always"></div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-black py-4 rounded-2xl transition-all shadow-xl shadow-indigo-500/20 transform hover:-translate-y-0.5 active:translate-y-0 uppercase tracking-widest text-xs">
                Cadastrar Agora
            </button>
        </form>

        <p class="text-center text-slate-500 mt-10 text-sm font-medium">
            Já tem uma conta? <a href="login.php" class="text-indigo-600 hover:text-indigo-500 font-bold underline underline-offset-4">Faça login</a>
        </p>
    </div>

    <script>
        <?php if ($erro): ?>
            window.onload = () => Toast.show("<?php echo $erro; ?>", "error");
        <?php endif; ?>
    </script>

</body>
</html>