<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ui_helper.php';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ranking de Concursos Grátis | Alternativa Gratuita ao Olho Na Vaga | OpenGabarito</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Procurando ranking de concursos grátis? Conheça o OpenGabarito, a melhor alternativa gratuita ao De Olho na Vaga. Estimativa de nota de corte com IA e transparência total.">
    <meta name="keywords" content="de olho na vaga gratis, ranking concursos gratuito, simulador nota de corte, gabarito extraoficial, estimativa nota de corte gratis, ranking funcionalismo publico">
    <meta name="author" content="Geovane S. Maximiano">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;900&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #ffffff; color: #0f172a; }
        .glass-card { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(0, 0, 0, 0.05); }
        .text-gradient { background: linear-gradient(135deg, #4f46e5 0%, #10b981 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .animate-float { animation: float 6s ease-in-out infinite; }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-20px); } 100% { transform: translateY(0px); } }
        .bg-mesh {
            background-image: radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.05) 0px, transparent 50%),
                              radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.03) 0px, transparent 50%);
        }
    </style>
</head>
<body class="bg-mesh text-slate-600 overflow-x-hidden">

    <!-- Hero Section -->
    <div class="relative min-h-screen flex items-center justify-center py-20 px-4">
        <!-- Background Orbs -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden -z-10">
            <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-indigo-500/10 rounded-full blur-[120px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-emerald-500/10 rounded-full blur-[120px]"></div>
        </div>

        <div class="max-w-5xl mx-auto text-center relative z-10">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-600 text-[10px] font-black uppercase tracking-widest mb-8 animate-fade-in shadow-sm">
                <i class="fa-solid fa-star"></i> 100% Gratuito de Verdade
            </div>
            
            <h1 class="text-5xl md:text-8xl font-black text-slate-900 mb-8 tracking-tighter leading-[0.9]">
                Cansado de pagar para ver sua <span class="text-gradient">Posição no Ranking?</span>
            </h1>
            
            <p class="text-xl md:text-2xl text-slate-500 mb-16 max-w-3xl mx-auto leading-relaxed font-medium">
                O OpenGabarito é a alternativa definitiva e gratuita ao <strong>De Olho na Vaga</strong>. 
                Use nossa inteligência artificial para prever sua nota de corte sem gastar um centavo.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
                <a href="index.php" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-500 text-white px-12 py-6 rounded-[32px] font-black text-lg transition-all shadow-2xl shadow-indigo-500/20 flex items-center justify-center gap-3 uppercase tracking-widest">
                    <i class="fa-solid fa-trophy"></i> Rankings Ativos
                </a>
                <a href="novo_gabarito.php" class="w-full sm:w-auto bg-white hover:bg-slate-50 text-slate-900 px-12 py-6 rounded-[32px] font-black text-lg transition-all border border-slate-200 flex items-center justify-center gap-3 uppercase tracking-widest shadow-sm">
                    <i class="fa-solid fa-plus"></i> Enviar Gabarito
                </a>
            </div>

            <!-- Stats Bar -->
            <div class="mt-24 grid grid-cols-1 md:grid-cols-3 gap-8 text-left">
                <div class="bg-white p-8 rounded-[32px] border border-slate-100 shadow-xl shadow-slate-200/20 group hover:border-indigo-200 transition-all">
                    <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl mb-6 shadow-sm"><i class="fa-solid fa-bolt"></i></div>
                    <h3 class="text-slate-900 font-black text-xl mb-3">Velocidade Senior</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">Ranking atualizado em milissegundos após cada novo gabarito enviado.</p>
                </div>
                <div class="bg-white p-8 rounded-[32px] border border-slate-100 shadow-xl shadow-slate-200/20 group hover:border-emerald-200 transition-all">
                    <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl mb-6 shadow-sm"><i class="fa-solid fa-brain"></i></div>
                    <h3 class="text-slate-900 font-black text-xl mb-3">IA Preditiva</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">Algoritmos avançados que estimam a nota de corte real baseada na concorrência.</p>
                </div>
                <div class="bg-white p-8 rounded-[32px] border border-slate-100 shadow-xl shadow-slate-200/20 group hover:border-rose-200 transition-all">
                    <div class="w-14 h-14 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-2xl mb-6 shadow-sm"><i class="fa-solid fa-lock-open"></i></div>
                    <h3 class="text-slate-900 font-black text-xl mb-3">Sem Paywalls</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">Diferente do De Olho na Vaga, aqui todas as funcionalidades são liberadas.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparativo Section -->
    <section class="py-32 bg-slate-50/50">
        <div class="max-w-4xl mx-auto px-4">
            <h2 class="text-4xl md:text-5xl font-black text-center text-slate-900 mb-20 tracking-tight">Por que escolher o <span class="text-indigo-600">OpenGabarito?</span></h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-16">
                <div class="bg-white p-10 rounded-[40px] border border-rose-100 shadow-xl shadow-rose-500/5">
                    <h3 class="text-rose-600 font-black text-xl flex items-center gap-3 mb-8 uppercase tracking-widest text-sm">
                        <i class="fa-solid fa-xmark-circle text-2xl"></i> Outros Sites
                    </h3>
                    <ul class="space-y-6">
                        <li class="flex items-start gap-4 text-slate-400">
                            <i class="fa-solid fa-minus mt-1.5"></i> <span class="text-sm font-medium">Cobram para mostrar sua posição exata.</span>
                        </li>
                        <li class="flex items-start gap-4 text-slate-400">
                            <i class="fa-solid fa-minus mt-1.5"></i> <span class="text-sm font-medium">Cobram para ver estatísticas de outros.</span>
                        </li>
                        <li class="flex items-start gap-4 text-slate-400">
                            <i class="fa-solid fa-minus mt-1.5"></i> <span class="text-sm font-medium">Interface complexa e cheia de anúncios.</span>
                        </li>
                    </ul>
                </div>
                <div class="bg-white p-10 rounded-[40px] border border-emerald-100 shadow-xl shadow-emerald-500/5">
                    <h3 class="text-emerald-600 font-black text-xl flex items-center gap-3 mb-8 uppercase tracking-widest text-sm">
                        <i class="fa-solid fa-check-circle text-2xl"></i> OpenGabarito
                    </h3>
                    <ul class="space-y-6">
                        <li class="flex items-start gap-4 text-slate-900 font-bold">
                            <i class="fa-solid fa-check mt-1.5 text-emerald-500"></i> <span class="text-sm">Grátis hoje, amanhã e sempre.</span>
                        </li>
                        <li class="flex items-start gap-4 text-slate-900 font-bold">
                            <i class="fa-solid fa-check mt-1.5 text-emerald-500"></i> <span class="text-sm">Posição exata liberada para todos.</span>
                        </li>
                        <li class="flex items-start gap-4 text-slate-900 font-bold">
                            <i class="fa-solid fa-check mt-1.5 text-emerald-500"></i> <span class="text-sm">Design Premium e fácil de usar.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ / SEO Section -->
    <!-- FAQ / SEO Section -->
    <section class="py-32 px-4">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-4xl font-black text-slate-900 mb-20 text-center tracking-tight">Tudo sobre <span class="text-indigo-600">Ranking de Concursos</span></h2>
            
            <div class="space-y-8">
                <div class="bg-white p-10 rounded-[40px] border border-slate-100 shadow-sm hover:shadow-xl hover:border-indigo-100 transition-all group">
                    <h3 class="text-2xl font-black text-slate-900 mb-4 tracking-tight group-hover:text-indigo-600 transition-colors">Como funciona o ranking gratuito?</h3>
                    <p class="text-slate-500 leading-relaxed font-medium">O OpenGabarito coleta os gabaritos enviados pelos próprios candidatos e utiliza uma lógica de inteligência coletiva para definir o gabarito de consenso. Com isso, conseguimos calcular sua pontuação e te colocar em um ranking dinâmico.</p>
                </div>
                <div class="bg-white p-10 rounded-[40px] border border-slate-100 shadow-sm hover:shadow-xl hover:border-indigo-100 transition-all group">
                    <h3 class="text-2xl font-black text-slate-900 mb-4 tracking-tight group-hover:text-indigo-600 transition-colors">O OpenGabarito é melhor que o De Olho na Vaga?</h3>
                    <p class="text-slate-500 leading-relaxed font-medium">Nossa missão não é apenas competir, mas oferecer uma alternativa democrática. Enquanto outros sites monetizam o seu desespero por informação, nós oferecemos tecnologia de ponta (IA) e transparência total sem custo.</p>
                </div>
                <div class="bg-white p-10 rounded-[40px] border border-slate-100 shadow-sm hover:shadow-xl hover:border-indigo-100 transition-all group">
                    <h3 class="text-2xl font-black text-slate-900 mb-4 tracking-tight group-hover:text-indigo-600 transition-colors">É seguro cadastrar minhas respostas?</h3>
                    <p class="text-slate-500 leading-relaxed font-medium">Sim! Suas respostas são processadas de forma anônima no ranking público, protegendo sua privacidade enquanto ajudamos a construir a amostragem do concurso.</p>
                </div>
            </div>
        </div>
    </section>

    <?php echo getFooter(); ?>

</body>
</html>
