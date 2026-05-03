<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui_helper.php';

$erro = '';
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

    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if ($email && $senha && !$erro) {
        // Brute Force Check
        if (checkBruteForce($pdo, $email)) {
            $erro = "Muitas tentativas. Aguarde 15 minutos.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
                // Sucesso
                clearLoginAttempts($pdo, $email);
                session_regenerate_id(true);
                
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_nickname'] = $usuario['nickname'];
                $_SESSION['role'] = $usuario['role'] ?? 'user';
                header("Location: index.php?login=success");
                exit;
            } else {
                registerLoginAttempt($pdo, $email);
                $erro = "E-mail ou senha incorretos.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar | OpenGabarito</title>
    <link rel="icon" href="data:image/svg+xml,<?php echo rawurlencode(getLogoSVG(40)); ?>">
    
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
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
    <script src="assets/js/toasts.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;900&display=swap');
        body { font-family: 'Outfit', sans-serif; }
        .bg-mesh {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.08) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(34, 197, 94, 0.08) 0px, transparent 50%);
        }
        .glass-panel { background: white; border: 1px solid var(--slate-100); }
    </style>
</head>
<body class="bg-mesh text-slate-600 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full glass-panel rounded-[40px] p-10 shadow-2xl shadow-primary-500/5">
        <div class="text-center mb-10">
            <a href="index.php" class="inline-flex flex-col items-center gap-4 group">
                <div class="h-20 w-20 group-hover:scale-110 transition-transform">
                    <?php echo getLogoSVG(80); ?>
                </div>
                <span class="text-4xl font-black text-slate-900 tracking-tighter">Open<span class="text-primary-600">Gabarito</span></span>
            </a>
            <h1 class="text-2xl font-black text-slate-900 mt-6 tracking-tight">Bem-vindo de volta</h1>
            <p class="text-slate-500 mt-2 font-medium">Acesse sua conta para colaborar</p>
        </div>

        <?php if ($erro): ?>
            <div class="bg-danger-50 border border-danger-100 text-danger-600 p-4 rounded-2xl text-sm mb-6 flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span class="font-bold"><?php echo $erro; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <?php echo csrfInput(); ?>
            <!-- Honeypot -->
            <div style="display:none;">
                <input type="text" name="website" value="">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">E-mail de Acesso</label>
                <input type="email" name="email" requidanger class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-slate-900 focus:outline-none focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 transition-all font-medium" placeholder="exemplo@email.com">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">Sua Senha</label>
                <input type="password" name="senha" requidanger class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-slate-900 focus:outline-none focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 transition-all font-medium" placeholder="••••••••">
            </div>

            <!-- Turnstile Widget -->
            <div class="cf-turnstile flex justify-center py-2" data-sitekey="<?php echo TURNSTILE_SITE_KEY; ?>" data-theme="light" data-appearance="always"></div>

            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-500 text-white font-black py-4 rounded-2xl transition-all shadow-xl shadow-primary-500/20 transform hover:-translate-y-0.5 active:translate-y-0 uppercase tracking-widest text-xs">
                Entrar na Plataforma
            </button>
        </form>

        <p class="text-center text-slate-500 mt-10 text-sm font-medium">
            Ainda não tem conta? <a href="cadastro.php" class="text-primary-600 hover:text-primary-500 font-bold underline underline-offset-4">Crie uma agora</a>
        </p>
    </div>

    <script>
        <?php if ($erro): ?>
            window.onload = () => Toast.show("<?php echo $erro; ?>", "error");
        <?php endif; ?>
    </script>

</body>
</html>