<?php
/**
 * Gerenciamento de Sessão e Autenticação
 */
require_once dirname(__FILE__) . '/config.php';
// Configurações de segurança da sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Mude para 1 se usar HTTPS
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Headers de Segurança (Skill: Hardened Site)
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://n8n.contora.com.br; frame-src https://challenges.cloudflare.com;");

session_start();

// Gerar Token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Valida o token CSRF em requisições POST
 */
function validateCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Erro de validação CSRF (Acesso expirado). Por favor, recarregue a página.");
        }
    }
}

/**
 * Valida o token do Cloudflare Turnstile
 */
function validateTurnstile($token) {
    if (!$token) return false;
    
    $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
    $data = [
        'secret' => TURNSTILE_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result, true);

    return $response['success'] ?? false;
}

/**
 * Retorna o campo HTML do token CSRF
 */
function csrfInput() {
    return '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
}

/**
 * Verifica se houve muitas tentativas de login (Anti-Brute Force)
 */
function checkBruteForce($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE (email = ? OR ip_address = ?) AND attempt_time > NOW() - INTERVAL 15 MINUTE");
    $stmt->execute([$email, $ip]);
    return $stmt->fetchColumn() >= 5; // Bloqueia após 5 tentativas em 15min
}

/**
 * Registra uma tentativa falha de login
 */
function registerLoginAttempt($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)");
    $stmt->execute([$email, $ip]);
}

/**
 * Limpa tentativas após login bem-sucedido
 */
function clearLoginAttempts($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ? OR ip_address = ?");
    $stmt->execute([$email, $ip]);
}

/**
 * Registra uma ação sensível no log de auditoria (Skill: Audit Trail)
 */
function logAction($pdo, $acao, $tabela = null, $registro_id = null, $antigo = null, $novo = null) {
    $user_id = $_SESSION['usuario_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("INSERT INTO audit_logs (usuario_id, acao, tabela, registro_id, valores_antigos, valores_novos, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $user_id, 
        $acao, 
        $tabela, 
        $registro_id, 
        $antigo ? json_encode($antigo) : null, 
        $novo ? json_encode($novo) : null, 
        $ip
    ]);
}

/**
 * Atalho para higienização de output (Skill: Zero XSS)
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['usuario_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: index.php");
        exit;
    }
}

function logout() {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>