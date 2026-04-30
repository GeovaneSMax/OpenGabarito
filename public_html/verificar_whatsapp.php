<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui_helper.php';

if (!isset($_SESSION['pending_user_id'])) {
    header("Location: login.php");
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $codigo = $_POST['codigo'] ?? '';
    $user_id = $_SESSION['pending_user_id'];

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();

    if ($usuario && $usuario['otp_secret'] === $codigo) {
        $stmt = $pdo->prepare("UPDATE usuarios SET whatsapp_verificado = 1, otp_secret = NULL WHERE id = ?");
        $stmt->execute([$user_id]);

        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['role'] = $usuario['role'] ?? 'user';
        unset($_SESSION['pending_user_id']);

        header("Location: index.php?login=success");
        exit;
    } else {
        $erro = "Código inválido. Verifique seu WhatsApp.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar WhatsApp | Open Gabarito</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="assets/js/toasts.js"></script>
    <style>
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(71, 85, 105, 0.4); }
    </style>
</head>
<body class="bg-[#0f172a] text-slate-300 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full glass-panel rounded-2xl p-8 shadow-2xl">
        <div class="text-center mb-8">
            <div class="inline-flex h-16 w-16 mb-4 text-emerald-400 text-5xl">
                <i class="fa-brands fa-whatsapp"></i>
            </div>
            <h1 class="text-2xl font-bold text-white">Verifique seu WhatsApp</h1>
            <p class="text-slate-400 mt-2">Enviamos um código de 6 dígitos para o seu número.</p>
        </div>

        <?php if ($erro): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-3 rounded-lg text-sm mb-6"><?php echo $erro; ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <?php echo csrfInput(); ?>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-2 text-center uppercase tracking-widest">Código de Verificação</label>
                <input type="text" name="codigo" required maxlength="6" placeholder="000000" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-4 text-center text-3xl font-black tracking-[1em] text-white focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-4 rounded-xl transition shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2">
                <i class="fa-solid fa-check-circle"></i> Verificar e Entrar
            </button>
        </form>

        <p class="text-center text-slate-500 mt-8 text-sm">
            Não recebeu o código? <a href="cadastro.php" class="text-indigo-400 hover:text-indigo-300 font-medium">Tentar novamente</a>
        </p>
    </div>

    <script>
        <?php if ($erro): ?>
            window.onload = () => Toast.show("<?php echo $erro; ?>", "error");
        <?php endif; ?>
    </script>
</body>
</html>
