<?php
/**
 * UI Helpers for OpenGabarito
 */

function getLogoSVG($size = 40) {
    return '
    <svg width="'.$size.'" height="'.$size.'" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="40" height="40" rx="12" fill="url(#logo_grad)"/>
        <path d="M12 15C12 13.8954 12.8954 13 14 13H26C27.1046 13 28 13.8954 28 15V17H12V15Z" fill="white" fill-opacity="0.9"/>
        <path d="M12 20C12 18.8954 12.8954 18 14 18H26C27.1046 18 28 18.8954 28 20V22H12V20Z" fill="white" fill-opacity="0.6"/>
        <path d="M12 25C12 23.8954 12.8954 23 14 23H26C27.1046 23 28 23.8954 28 25V27H14C12.8954 27 12 26.1046 12 25Z" fill="white" fill-opacity="0.3"/>
        <defs>
            <linearGradient id="logo_grad" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse">
                <stop stop-color="#6366F1"/>
                <stop offset="1" stop-color="#10B981"/>
            </linearGradient>
        </defs>
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
 * Footer Global (Skill: Professional Identity)
 */
function getFooter() {
    return '
    <footer class="border-t border-slate-100 mt-10 py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 text-center overflow-hidden">
            <!-- Mensagem de Comunidade Global -->
            <div class="mb-12 p-5 sm:p-6 rounded-3xl bg-indigo-50 border border-indigo-100 max-w-2xl mx-auto overflow-hidden">
                <h4 class="text-slate-900 text-xs sm:text-sm font-black mb-2 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-people-group text-indigo-600"></i> A FORÇA É A COMUNIDADE
                </h4>
                <p class="text-slate-500 text-[10px] sm:text-xs leading-relaxed px-2">
                    O OpenGabarito é <span class="text-emerald-600 font-bold">de graça de verdade</span>. Não cobramos nada porque acreditamos na democratização da informação. <span class="text-slate-900 font-medium">Quanto mais pessoas usam e colaboram, mais assertivos e precisos se tornam os nossos rankings.</span> Ajude compartilhando!
                </p>
                <div class="mt-4 flex justify-center px-4">
                     <button type="button" onclick="sharePlatform()" class="w-full sm:w-auto flex items-center justify-center gap-2 bg-white hover:bg-indigo-600 text-indigo-600 hover:text-white px-4 py-2 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all border border-indigo-200 shadow-sm">
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
                        Desenvolvido por <a href="sobre.php" class="text-indigo-600 hover:text-indigo-500 font-bold transition">Geovane S. Maximiano</a>
                    </p>
                    <p class="text-slate-400 text-[10px] mt-1 italic">
                        De concurseiro para concurseiro.
                    </p>
                </div>
                <div class="h-8 w-[1px] bg-slate-100 hidden md:block"></div>
                <a href="https://www.asaas.com/c/vq7q638uo8lmwmdt" target="_blank" class="flex items-center gap-2 bg-emerald-50 hover:bg-emerald-600 text-emerald-600 hover:text-white px-4 py-2 rounded-lg text-[10px] font-black transition-all border border-emerald-200 uppercase tracking-widest shadow-sm">
                    <i class="fa-solid fa-heart"></i> Apoiar Projeto
                </a>
            </div>

            <p class="text-slate-400 text-[10px]">
                © ' . date('Y') . ' OpenGabarito. Todos os direitos reservados.
            </p>
        </div>
    </footer>
';
}
