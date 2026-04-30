<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ui_helper.php';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre o Desenvolvedor | Geovane S. Maximiano</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; color: #0f172a; overflow-x: hidden; }
        .glass { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(16px); border: 1px solid rgba(0, 0, 0, 0.05); }
        .gradient-text { background: linear-gradient(135deg, #4f46e5 0%, #10b981 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero-blob { position: absolute; filter: blur(80px); opacity: 0.1; z-index: -1; border-radius: 50%; }
        .card-hover:hover { transform: translateY(-5px); border-color: rgba(99, 102, 241, 0.2); box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="min-h-screen">

    <!-- Blobs decorativos -->
    <div class="hero-blob w-96 h-96 bg-indigo-600 top-[-10%] left-[-10%] animate-pulse"></div>
    <div class="hero-blob w-[500px] h-[500px] bg-emerald-600 bottom-[-10%] right-[-10%] animate-pulse" style="animation-delay: 2s"></div>

    <nav class="p-6 max-w-7xl mx-auto flex justify-between items-center relative z-10">
        <a href="index.php" class="flex items-center gap-2 group">
            <div class="h-8 w-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white group-hover:rotate-12 transition shadow-lg shadow-indigo-500/20">
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </div>
            <span class="text-sm font-bold text-slate-400 group-hover:text-slate-900 transition">Voltar ao Sistema</span>
        </a>
    </nav>

    <main class="max-w-5xl mx-auto px-6 py-20 relative z-10">
        <div class="flex flex-col md:flex-row items-center gap-12 mb-24">
            <div class="relative">
                <div class="w-48 h-48 md:w-64 md:h-64 rounded-[40px] bg-gradient-to-br from-indigo-500 to-emerald-500 p-1 rotate-3 shadow-2xl overflow-hidden group">
                    <img src="https://opengabarito.com.br/includes/uploads/e225aafea2957f27cfeae97c1374fae7_1777470704.png" class="w-full h-full object-cover rounded-[38px] group-hover:scale-110 transition duration-500" alt="Geovane S. Maximiano">
                </div>
                <div class="absolute -bottom-4 -right-4 bg-indigo-600 text-white px-4 py-2 rounded-2xl font-bold text-xs shadow-xl rotate-[-5deg]">
                    Desenvolvedor
                </div>
            </div>
            
            <div class="flex-1 text-center md:text-left">
                <h1 class="text-5xl md:text-7xl font-black mb-6 tracking-tight text-slate-900">Geovane S. <br><span class="gradient-text">Maximiano</span></h1>
                <p class="text-xl text-slate-500 max-w-2xl leading-relaxed font-light">
                    Desenvolvedor Full-Stack especializado em arquitetura de sistemas escaláveis e segurança de dados. Criador de soluções que transformam complexidade em simplicidade.
                </p>
                
                <div class="flex flex-wrap justify-center md:justify-start gap-4 mt-10">
                    <a href="https://wa.me/5511998833971" target="_blank" class="bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-4 rounded-2xl font-bold transition shadow-xl shadow-indigo-500/20 flex items-center gap-3">
                        <i class="fa-brands fa-whatsapp"></i> Vamos Conversar
                    </a>
                    <div class="flex gap-4 items-center px-4">
                        <i class="fa-brands fa-github text-2xl text-slate-400 hover:text-slate-900 cursor-pointer transition"></i>
                        <i class="fa-brands fa-linkedin text-2xl text-slate-400 hover:text-slate-900 cursor-pointer transition"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Meus Projetos -->
        <div class="mb-32">
            <div class="flex items-center gap-4 mb-12">
                <h2 class="text-3xl font-black tracking-tight text-slate-900">Projetos em <span class="text-indigo-600">Destaque</span></h2>
                <div class="h-[2px] flex-1 bg-slate-100"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Contora -->
                <a href="https://contora.com.br" target="_blank" class="bg-white border border-slate-100 p-8 rounded-[32px] card-hover transition-all block group shadow-sm">
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mb-6 text-xl group-hover:scale-110 transition">
                        <i class="fa-solid fa-shop"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-900 mb-3">Contora ERP</h3>
                    <p class="text-slate-500 text-sm leading-relaxed mb-6">Um sistema PDV (Ponto de Venda) e ERP completo para gestão empresarial, focado em alta performance e simplicidade.</p>
                    <div class="text-xs font-black text-indigo-600 uppercase tracking-widest flex items-center gap-2">
                        Ver Projeto <i class="fa-solid fa-arrow-right-long"></i>
                    </div>
                </a>

                <!-- Assessoria MEI -->
                <a href="https://assessoriamei.com.br" target="_blank" class="bg-white border border-slate-100 p-8 rounded-[32px] card-hover transition-all block group shadow-sm">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mb-6 text-xl group-hover:scale-110 transition">
                        <i class="fa-solid fa-briefcase"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-900 mb-3">Assessoria MEI</h3>
                    <p class="text-slate-500 text-sm leading-relaxed mb-6">Plataforma dedicada a auxiliar Microempreendedores Individuais em sua jornada de regularização e crescimento.</p>
                    <div class="text-xs font-black text-emerald-600 uppercase tracking-widest flex items-center gap-2">
                        Ver Projeto <i class="fa-solid fa-arrow-right-long"></i>
                    </div>
                </a>

                <!-- Bem na Prática -->
                <a href="https://bemnapratica.com.br" target="_blank" class="bg-white border border-slate-100 p-8 rounded-[32px] card-hover transition-all block group shadow-sm">
                    <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center mb-6 text-xl group-hover:scale-110 transition">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-900 mb-3">Bem na Prática</h3>
                    <p class="text-slate-500 text-sm leading-relaxed mb-6">Sistema inovador focado em projetos sociais e doações, conectando quem quer ajudar com quem precisa.</p>
                    <div class="text-xs font-black text-rose-600 uppercase tracking-widest flex items-center gap-2">
                        Ver Projeto <i class="fa-solid fa-arrow-right-long"></i>
                    </div>
                </a>

                <!-- Open Gabarito -->
                <a href="#" class="bg-indigo-50 border border-indigo-100 p-8 rounded-[32px] card-hover transition-all block group shadow-sm">
                    <div class="w-12 h-12 bg-white text-indigo-600 rounded-2xl flex items-center justify-center mb-6 text-xl group-hover:scale-110 transition shadow-sm">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-900 mb-3">Open Gabarito</h3>
                    <p class="text-slate-500 text-sm leading-relaxed mb-6">A plataforma que você está usando! Engenharia de IA para predição de notas de corte em tempo real.</p>
                    <div class="text-xs font-black text-indigo-600 uppercase tracking-widest flex items-center gap-2">
                        Projeto Atual <i class="fa-solid fa-star text-amber-500"></i>
                    </div>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center py-12 border-t border-slate-100">
            <p class="text-slate-400 text-sm">© <?php echo date('Y'); ?> Desenvolvido com ❤️ por Geovane S. Maximiano.</p>
        </footer>
    </main>

<?php echo getFooter(); ?>
</body>
</html>
