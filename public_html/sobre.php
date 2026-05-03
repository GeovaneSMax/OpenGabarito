<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ui_helper.php';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geovane S. Maximiano | Fullstack Developer & Concurseiro</title>
    
    <link rel="icon" href="data:image/svg+xml,<?php echo rawurlencode(getLogoSVG(40)); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { 500: '#0f172a', 600: '#1f2937' },
                        gray: { 50: '#f9fafb', 100: '#f3f4f6', 200: '#e5e7eb', 300: '#d1d5db', 400: '#9ca3af', 500: '#6b7280', 600: '#4b5563', 700: '#374151', 800: '#1f2937', 900: '#111827', 950: '#0a0a0a' }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                        serif: ['Crimson Pro', 'serif'],
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@400;700;900&family=Crimson+Pro:ital,wght@0,400;0,700;1,400&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        :root { --primary-primary: #0f172a; }
        body { font-family: 'Inter', sans-serif; background-color: #ffffff; color: #111827; overflow-x: hidden; }
        
        .text-balance { text-wrap: balance; }
        
        .section-title { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body>

    <main class="relative pt-32">
        <section class="max-w-4xl mx-auto px-6 mb-20 text-center">
            <h1 class="section-title text-5xl lg:text-7xl font-black text-gray-900 leading-tight mb-8 text-balance">
                "O verdadeiro poder do código reside em sua capacidade de ser <span class="text-primary-500">aberto</span>, <span class="text-gray-600">colaborativo</span> e <span class="text-primary-500">livre</span>."
            </h1>
            <p class="text-lg text-gray-500 leading-relaxed max-w-2xl mx-auto">
                Acreditamos na força da comunidade e na inovação impulsionada pelo compartilhamento.
            </p>
        </section>

        <!-- Ecosystem: The Multi-Project Strategy -->
        <section id="projects" class="max-w-7xl mx-auto px-6 mb-40">
            <div class="flex flex-col md:flex-row items-end justify-between gap-8 mb-20">
                <div class="max-w-xl">
                    <h2 class="section-title text-4xl font-black text-gray-900 mb-6 uppercase tracking-tighter">Meus <span class="text-primary-500">Projetos</span></h2>
                    <p class="text-gray-500 leading-relaxed italic">
                        De soluções empresariais a impacto social, cada projeto é uma peça de um quebra-cabeça tecnológico voltado para a eficiência e o bem comum.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                <div class="bg-white p-12 rounded-[3rem] shadow-lg border border-gray-100 group">
                    <div class="flex justify-between items-start mb-12">
                        <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center text-primary-500 text-3xl">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </div>
                        <span class="text-[9px] font-black text-primary-500 bg-gray-100 px-4 py-1.5 rounded-full uppercase tracking-widest">Flagship Product</span>
                    </div>
                    <h3 class="section-title text-3xl font-black text-gray-900 mb-6 uppercase tracking-tight">OpenGabarito</h3>
                    <p class="text-gray-500 leading-relaxed mb-10 text-sm">
                        Plataforma colaborativa que revoluciona o acompanhamento de concursos públicos. Através de <span class="text-gray-900 font-bold">inteligência coletiva</span> e modelos preditivos em <span class="text-gray-900 font-bold">IA</span>, o sistema processa milhares de dados para entregar transparência e precisão em rankings.
                    </p>
                    <div class="flex flex-wrap gap-3 mb-12">
                        <span class="px-3 py-1 bg-gray-100 rounded-lg text-[10px] font-bold text-gray-500 uppercase tracking-tighter">PHP 8.2</span>
                        <span class="px-3 py-1 bg-gray-100 rounded-lg text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Tailwind Engine</span>
                        <span class="px-3 py-1 bg-gray-100 rounded-lg text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Groq / Gemini AI</span>
                    </div>
                    <a href="index.php" class="inline-flex items-center gap-3 text-[11px] font-black uppercase tracking-widest text-primary-500 group-hover:translate-x-2 transition-transform">
                        Launch Application <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <div class="bg-white p-12 rounded-[3rem] shadow-lg border border-gray-100 group">
                    <div class="flex justify-between items-start mb-12">
                        <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center text-primary-500 text-3xl">
                            <i class="fa-solid fa-toolbox"></i>
                        </div>
                        <span class="text-[9px] font-black text-primary-500 bg-gray-100 px-4 py-1.5 rounded-full uppercase tracking-widest">Service Design</span>
                    </div>
                    <h3 class="section-title text-3xl font-black text-gray-900 mb-6 uppercase tracking-tight">Assessoria MEI</h3>
                    <p class="text-gray-500 leading-relaxed mb-10 text-sm">
                        Um hub estratégico focado na desburocratização. Oferece <span class="text-gray-900 font-bold">ferramentas gratuitas</span>, guias práticos e postagens informativas para transformar a complexidade fiscal em clareza operacional para o microempreendedor.
                    </p>
                    <div class="flex flex-wrap gap-3 mb-12">
                        <span class="px-3 py-1 bg-gray-100 rounded-lg text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Automation</span>
                        <span class="px-3 py-1 bg-gray-100 rounded-lg text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Content Strategy</span>
                        <span class="px-3 py-1 bg-gray-100 rounded-lg text-[10px] font-bold text-gray-500 uppercase tracking-tighter">SEO Optimized</span>
                    </div>
                    <a href="https://assessoriamei.com.br" target="_blank" class="inline-flex items-center gap-3 text-[11px] font-black uppercase tracking-widest text-primary-500 group-hover:translate-x-2 transition-transform">
                        Access Knowledge Base <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <div class="bg-white p-12 rounded-[3rem] shadow-lg border border-gray-100 group">
                    <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center text-primary-500 text-3xl mb-12">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <h3 class="section-title text-3xl font-black text-gray-900 mb-6 uppercase tracking-tight">Contora ERP</h3>
                    <p class="text-gray-500 leading-relaxed mb-10 text-sm">
                        Gestão empresarial de alta performance. O foco aqui é a <span class="text-gray-900 font-bold">UX de resultado</span>: transformar processos complexos de PDV e financeiro em interfaces que qualquer lojista domina em minutos.
                    </p>
                    <div class="flex flex-wrap gap-3 mb-12">
                        <span class="px-3 py-1 bg-gray-100 rounded-lg text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Fintech Core</span>
                        <span class="px-3 py-1 bg-gray-100 rounded-lg text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Real-time DB</span>
                    </div>
                    <a href="https://contora.com.br" target="_blank" class="inline-flex items-center gap-3 text-[11px] font-black uppercase tracking-widest text-primary-500 group-hover:translate-x-2 transition-transform">
                        Enterprise Solutions <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <div class="bg-white p-12 rounded-[3rem] shadow-lg border border-gray-100 group">
                    <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center text-primary-500 text-3xl mb-12">
                        <i class="fa-solid fa-hands-holding"></i>
                    </div>
                    <h3 class="section-title text-3xl font-black text-gray-900 mb-6 uppercase tracking-tight">Bem na Prática</h3>
                    <p class="text-gray-500 leading-relaxed mb-10 text-sm">
                        A tecnologia a serviço da empatia. Plataforma de rastreabilidade para doações, garantindo que cada centavo chegue onde é mais necessário com <span class="text-gray-900 font-bold">transparência absoluta</span>.
                    </p>
                    <div class="flex flex-wrap gap-3 mb-12">
                        <span class="px-3 py-1 bg-gray-100 rounded-lg text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Social Tech</span>
                        <span class="px-3 py-1 bg-gray-100 rounded-lg text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Transparency First</span>
                    </div>
                    <a href="https://bemnapratica.com.br" target="_blank" class="inline-flex items-center gap-3 text-[11px] font-black uppercase tracking-widest text-primary-500 group-hover:translate-x-2 transition-transform">
                        Human Impact <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </section>

        <section class="max-w-7xl mx-auto px-6 mb-40 text-center">
            <div class="inline-block p-1 mb-10 bg-gray-200 rounded-full">
                <div class="px-10 py-5 bg-white rounded-full">
                    <p class="text-[10px] font-black uppercase tracking-[0.4em] text-gray-900">Let's build something significant</p>
                </div>
            </div>
            <h3 class="section-title text-4xl sm:text-6xl font-black text-gray-900 mb-12 uppercase tracking-tighter">Conecte-se com o <span class="text-primary-500">Desenvolvedor</span></h3>
            
            <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
                <a href="https://wa.me/5511998833971" target="_blank" class="w-full sm:w-auto px-12 py-5 bg-primary-500 text-white rounded-2xl font-black text-[11px] uppercase tracking-widest hover:bg-gray-800 transition-all shadow-xl shadow-primary-500/20">
                    <i class="fa-brands fa-whatsapp mr-2"></i> WhatsApp Direct
                </a>
                <a href="https://github.com/GeovaneSMax" target="_blank" class="w-full sm:w-auto px-12 py-5 bg-gray-950 text-white rounded-2xl font-black text-[11px] uppercase tracking-widest hover:bg-gray-800 transition-all shadow-xl shadow-gray-950/20">
                    <i class="fa-brands fa-github mr-2"></i> Repository
                </a>
            </div>
        </section>
    </main>

    <?php echo getFooter(); ?>

</body>
</html>
