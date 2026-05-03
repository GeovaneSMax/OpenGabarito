<?php
/**
 * UI Helpers for OpenGabarito
 */

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) return 'n-a';
    return $text;
}

/**
 * Gera a base de um nickname baseado no nome ou aleatório (estilo Reddit)
 */
function getNicknameBase($fullName) {
    $parts = array_filter(explode(' ', trim($fullName)));
    
    if (count($parts) > 1) {
        $surname = end($parts);
        $clean = slugify($surname);
        if ($clean !== 'n-a' && strlen($clean) >= 2) {
             return ucfirst(substr($clean, 0, 12)); // Max 12 + ID (até 8 dígitos) = 20
        }
    }

    // Reddit-style fallback: Adjetivo + Substantivo
    $adjectives = ['Focado', 'Sabio', 'Atento', 'Forte', 'Agil', 'Nobre', 'Bravo', 'Ativo', 'Calmo', 'Livre', 'Grande', 'Veloz', 'Justo', 'Tenaz', 'Astro'];
    $nouns = ['Aluno', 'Mestre', 'Genio', 'Lobo', 'Leao', 'Aguia', 'Tigre', 'Urso', 'Touro', 'Lince', 'Falco', 'Fenix', 'Gato', 'Coruja', 'Panda'];

    $adj = $adjectives[array_rand($adjectives)];
    $noun = $nouns[array_rand($nouns)];

    return $adj . $noun;
}

function getLogoSVG($size = 40) {
    return '
    <svg width="'.$size.'" height="'.$size.'" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="40" height="40" rx="12" fill="#2563eb"/>
        <path d="M12 15C12 13.8954 12.8954 13 14 13H26C27.1046 13 28 13.8954 28 15V17H12V15Z" fill="white" fill-opacity="0.9"/>
        <path d="M12 20C12 18.8954 12.8954 18 14 18H26C27.1046 18 28 18.8954 28 20V22H12V20Z" fill="white" fill-opacity="0.6"/>
        <path d="M12 25C12 23.8954 12.8954 23 14 23H26C27.1046 23 28 23.8954 28 25V27H14C12.8954 27 12 26.1046 12 25Z" fill="white" fill-opacity="0.3"/>
    </svg>';
}

/**
 * Motor de Upload Seguro (Skill: Zero-Day Ready)
 * Valida Magic Bytes, MIME-type e renomeia o arquivo.
 */
function handleSecureUpload($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmpPath = $file['tmp_name'];
    $fileSize = $file['size'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

    // 1. Validar Tamanho (Ex: 2MB)
    if ($fileSize > 2 * 1024 * 1024) return false;

    // 2. Validar Magic Bytes (Conteúdo Real)
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpPath);
    } else {
        // Fallback para servidores sem a extensão fileinfo
        $imgInfo = getimagesize($tmpPath);
        $mimeType = $imgInfo['mime'] ?? null;
    }

    if (!in_array($mimeType, $allowedMimes)) return false;

    // 3. Validar Extensão
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) return false;

    // 4. Renomear (Skill: Blindagem de Caminho)
    $newName = bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;
    $uploadDir = __DIR__ . '/../public_html/uploads/';
    $finalPath = $uploadDir . $newName;

    if (move_uploaded_file($tmpPath, $finalPath)) {
        return 'uploads/' . $newName;
    }

    return false;
}

/**
 * Navigation Bar (Skill: Unified Experience)
 */
function getNav() {
    global $pdo;
    $isAdmin = isAdmin();
    $isLoggedIn = isLoggedIn();
    $logo = getLogoSVG(32);
    
    $userSection = '';
    if ($isLoggedIn) {
        $nickname = $_SESSION['usuario_nickname'] ?? 'Usuário';
        $foto = $_SESSION['usuario_foto'] ?? '';
        
        // Se a foto não estiver na sessão, tenta buscar
        if (!$foto && isset($pdo)) {
            $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");
            $stmt->execute([$_SESSION['usuario_id']]);
            $foto = $stmt->fetchColumn();
            $_SESSION['usuario_foto'] = $foto;
        }

        $userSection = '
            <div class="flex items-center gap-3 border-l border-slate-200 pl-4 ml-2">
                <a href="minha_area.php" class="flex items-center gap-3 group">
                    <div class="hidden md:flex flex-col items-end leading-tight">
                        <span class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Minha Área</span>
                        <span class="text-xs text-slate-900 font-bold group-hover:text-primary-600 transition">' . htmlspecialchars($nickname) . '</span>
                    </div>
                    <div class="w-9 h-9 rounded-full bg-white border-2 border-slate-200 overflow-hidden shadow-sm group-hover:border-primary-500 transition-all">
                        ' . ($foto ? '<img src="'.$foto.'" class="w-full h-full object-cover">' : '<div class="w-full h-full flex items-center justify-center bg-primary-600 text-white text-[10px] font-black">'.substr($nickname, 0, 1).'</div>') . '
                    </div>
                </a>
                <a href="logout.php" class="text-slate-400 hover:text-danger-500 transition-colors" title="Sair">
                    <i class="fa-solid fa-power-off text-sm"></i>
                </a>
            </div>';
    }

    $authLinks = $isLoggedIn ? $userSection : '
        <div class="flex items-center gap-3 border-l border-slate-200 pl-4 ml-2">
            <a href="login.php" class="hidden sm:block text-xs font-black uppercase tracking-widest text-slate-500 hover:text-slate-900 transition mr-2">Entrar</a>
            <a href="login.php?action=register" class="bg-primary-600 hover:bg-primary-500 text-white px-3 sm:px-4 py-2 rounded-lg text-[10px] sm:text-xs font-bold transition shadow-lg shadow-primary-500/20 whitespace-nowrap">Cadastrar</a>
        </div>';

    $adminLink = $isAdmin ? '
        <a href="admin/dashboard.php" class="text-danger-600 hover:text-danger-500 transition text-xs font-black uppercase tracking-widest px-4 py-2 flex items-center gap-2">
            <i class="fa-solid fa-screwdriver-wrench"></i> Admin
        </a>' : '';

    $mobileAdminLink = $isAdmin ? '
        <a href="admin/dashboard.php" class="block px-4 py-3 text-base font-bold text-danger-600 hover:bg-danger-50 rounded-xl transition-all flex items-center gap-3">
            <i class="fa-solid fa-screwdriver-wrench w-5"></i> Painel Admin
        </a>' : '';

    $mobileAuthLinks = $isLoggedIn ? '
        <a href="logout.php" class="block px-4 py-3 text-base font-bold text-danger-500 hover:bg-danger-50 rounded-xl transition-all flex items-center gap-3">
            <i class="fa-solid fa-power-off w-5"></i> Sair
        </a>' : '
        <a href="login.php" class="block px-4 py-3 text-base font-bold text-slate-700 hover:bg-slate-50 rounded-xl transition-all flex items-center gap-3">
            <i class="fa-solid fa-right-to-bracket text-primary-500 w-5"></i> Entrar
        </a>
        <a href="login.php?action=register" class="block px-4 py-3 text-base font-bold text-primary-600 hover:bg-primary-50 rounded-xl transition-all flex items-center gap-3">
            <i class="fa-solid fa-user-plus w-5"></i> Cadastrar
        </a>';

    return '
    <!-- Navbar -->
    <nav class="bg-white/98 backdrop-blur-md sticky top-0 z-50 mb-8 border-b border-slate-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
                <a href="index.php" class="flex items-center gap-2 sm:gap-3 group shrink-0">
                    <div class="h-8 w-8 sm:h-10 sm:w-10 group-hover:scale-110 transition-transform">
                        ' . $logo . '
                    </div>
                    <span class="font-bold text-lg sm:text-xl tracking-tight text-slate-900 whitespace-nowrap">Open<span class="text-primary-600">Gabarito</span></span>
                </a>
            
            <div class="flex items-center gap-2 sm:gap-4">
                <div class="hidden md:flex items-center gap-1">
                    <a href="index.php" class="text-slate-500 hover:text-slate-900 font-bold text-xs uppercase tracking-widest px-4 py-2 transition">Rankings</a>
                    <a href="transparencia.php" class="text-slate-500 hover:text-slate-900 font-bold text-xs uppercase tracking-widest px-4 py-2 transition flex items-center gap-2">
                        <i class="fa-solid fa-microchip text-[10px] text-primary-500"></i> Transparência
                    </a>
                    <a href="minha_area.php" class="text-slate-500 hover:text-slate-900 font-bold text-xs uppercase tracking-widest px-4 py-2 transition">Minha Área</a>
                    ' . $adminLink . '
                </div>
                
                ' . $authLinks . '
                
                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="md:hidden text-slate-500 hover:text-slate-900 p-2 flex items-center justify-center">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Container -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-100 bg-white/95 backdrop-blur-xl shadow-lg">
            <div class="px-4 py-6 space-y-2">
                <a href="index.php" class="block px-4 py-3 text-base font-bold text-slate-700 hover:bg-slate-50 rounded-xl transition-all flex items-center gap-3">
                    <i class="fa-solid fa-list-ol text-primary-500 w-5"></i> Ranking Geral
                </a>
                <a href="minha_area.php" class="block px-4 py-3 text-base font-bold text-slate-700 hover:bg-slate-50 rounded-xl transition-all flex items-center gap-3">
                    <i class="fa-solid fa-circle-user text-primary-500 w-5"></i> Minha Área
                </a>
                <a href="transparencia.php" class="block px-4 py-3 text-base font-bold text-slate-700 hover:bg-slate-50 rounded-xl transition-all flex items-center gap-3">
                    <i class="fa-solid fa-microchip text-primary-500 w-5"></i> Transparência
                </a>
                
                ' . $mobileAdminLink . '

                <div class="pt-4 border-t border-slate-100 mt-4 space-y-2">
                    ' . $mobileAuthLinks . '
                </div>
            </div>
        </div>
    </nav>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const menuBtn = document.getElementById(\'mobile-menu-btn\');
            const mobileMenu = document.getElementById(\'mobile-menu\');
            
            if (menuBtn && mobileMenu) {
                menuBtn.addEventListener(\'click\', function() {
                    mobileMenu.classList.toggle(\'hidden\');
                    const icon = menuBtn.querySelector(\'i\');
                    if (mobileMenu.classList.contains(\'hidden\')) {
                        icon.classList.remove(\'fa-xmark\');
                        icon.classList.add(\'fa-bars\');
                    } else {
                        icon.classList.remove(\'fa-bars\');
                        icon.classList.add(\'fa-xmark\');
                    }
                });
            }
        });
    </script>';
}

/**
 * Footer Global (Skill: Professional Identity)
 */
function getFooter() {
    return '
    <footer class="border-t border-slate-100 mt-10 py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 text-center overflow-hidden">
            <!-- Mensagem de Comunidade Global -->
            <div class="mb-12 p-5 sm:p-6 rounded-3xl bg-primary-50 border border-primary-100 max-w-2xl mx-auto overflow-hidden">
                <h4 class="text-slate-900 text-xs sm:text-sm font-black mb-2 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-people-group text-primary-600"></i> A FORÇA É A COMUNIDADE
                </h4>
                <p class="text-slate-500 text-[10px] sm:text-xs leading-relaxed px-2">
                    O OpenGabarito é <span class="text-success-600 font-bold">de graça de verdade</span>. Não cobramos nada porque acreditamos na democratização da informação. <span class="text-slate-900 font-medium">Quanto mais pessoas usam e colaboram, mais assertivos e precisos se tornam os nossos rankings.</span> Ajude compartilhando!
                </p>
                <div class="mt-4 flex justify-center px-4">
                     <button type="button" onclick="sharePlatform()" class="w-full sm:w-auto flex items-center justify-center gap-2 bg-white hover:bg-primary-600 text-primary-600 hover:text-white px-4 py-2 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all border border-primary-200 shadow-sm">
                        <i class="fa-solid fa-share-nodes"></i> Compartilhar Plataforma
                    </button>
                </div>
                <script>
                    function sharePlatform() {
                        const data = { title: \'OpenGabarito | Rankings Gratuitos\', url: window.location.origin };
                        if (navigator.share) {
                            navigator.share(data).catch(() => {});
                        } else {
                            navigator.clipboard.writeText(window.location.origin).then(() => {
                                alert(\'Link da plataforma copiado!\');
                            });
                        }
                    }
                </script>
            </div>

            <div class="flex flex-col items-center gap-4 mb-8">
                <div class="flex items-center gap-2 text-slate-500">
                    <i class="fa-solid fa-layer-group"></i>
                    <span class="font-semibold text-slate-900">Open Gabarito</span>
                </div>
                <span class="text-slate-500 text-sm italic text-center max-w-md">"Tecnologia aberta e transparente: por um mundo onde o conhecimento pertence a todos."</span>
            </div>
            
            <div class="flex flex-col md:flex-row items-center justify-center gap-6 mb-8">
                <div class="text-left">
                    <p class="text-slate-500 text-sm">
                        Desenvolvido por <a href="sobre.php" class="text-primary-600 hover:text-primary-500 font-bold transition">Geovane S. Maximiano</a>
                    </p>
                    <p class="text-slate-400 text-[10px] mt-1 italic">
                        De concurseiro para concurseiro.
                    </p>
                </div>
                <div class="h-8 w-[1px] bg-slate-100 hidden md:block"></div>
                <div class="flex items-center gap-3">
                    <a href="https://www.asaas.com/c/vq7q638uo8lmwmdt" target="_blank" class="flex items-center gap-2 bg-success-50 hover:bg-success-600 text-success-600 hover:text-white px-4 py-2 rounded-lg text-[10px] font-black transition-all border border-success-200 uppercase tracking-widest shadow-sm">
                        <i class="fa-solid fa-heart"></i> Apoiar Projeto
                    </a>
                    <a href="https://github.com/GeovaneSMax/OpenGabarito" target="_blank" class="flex items-center gap-2 bg-slate-50 hover:bg-slate-900 text-slate-900 hover:text-white px-4 py-2 rounded-lg text-[10px] font-black transition-all border border-slate-200 uppercase tracking-widest shadow-sm">
                        <i class="fa-brands fa-github text-sm"></i> Open Source
                    </a>
                </div>
            </div>

            <p class="text-slate-400 text-[10px]">
                © ' . date('Y') . ' OpenGabarito. Todos os direitos reservados.
            </p>
        </div>
    </footer>
';
}
