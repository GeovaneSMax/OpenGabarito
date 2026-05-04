<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui_helper.php';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre | Geovane S. Maximiano</title>
    
    <link rel="icon" href="data:image/svg+xml,<?php echo rawurlencode(getLogoSVG(40)); ?>">
    
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
                        slate: { 50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b', 600: '#475569', 700: '#334155', 800: '#1e293b', 900: '#0f172a' },
                        vibrant: {
                            pink: '#ec4899',
                            purple: '#a855f7',
                            blue: '#3b82f6',
                            cyan: '#06b6d4',
                            amber: '#f59e0b',
                            rose: '#f43f5e'
                        }
                    },
                    fontFamily: {
                        sans: ['Outfit', 'Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    borderRadius: {
                        '4xl': '2rem',
                        '5xl': '2.5rem',
                    },
                    animation: {
                        'gradient-x': 'gradient-x 15s ease infinite',
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        'gradient-x': {
                            '0%, 100%': { 'background-size': '200% 200%', 'background-position': 'left center' },
                            '50%': { 'background-size': '200% 200%', 'background-position': 'right center' },
                        },
                        'float': {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #fafafa; color: #1f2937; overflow-x: hidden; }
        .font-outfit { font-family: 'Outfit', sans-serif; }
        
        .mesh-gradient {
            background-color: #fafafa;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(236, 72, 153, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(59, 130, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(245, 158, 11, 0.1) 0px, transparent 50%);
        }

        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .card-glow-pink:hover { box-shadow: 0 20px 40px -15px rgba(236, 72, 153, 0.3); }
        .card-glow-blue:hover { box-shadow: 0 20px 40px -15px rgba(59, 130, 246, 0.3); }
        .card-glow-purple:hover { box-shadow: 0 20px 40px -15px rgba(168, 85, 247, 0.3); }
        .card-glow-amber:hover { box-shadow: 0 20px 40px -15px rgba(245, 158, 11, 0.3); }

        .text-gradient {
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            background-image: linear-gradient(to right, #6366f1, #ec4899, #f59e0b);
        }
    </style>
</head>
<body class="mesh-gradient min-h-screen">

    <?php echo getNav(); ?>

    <main class="relative pt-20 pb-32 px-6">
        <!-- Floating Blobs for decoration -->
        <div class="fixed top-20 left-[-10%] w-96 h-96 bg-vibrant-purple/20 rounded-full blur-[100px] animate-pulse-slow"></div>
        <div class="fixed bottom-20 right-[-10%] w-96 h-96 bg-vibrant-pink/20 rounded-full blur-[100px] animate-pulse-slow" style="animation-delay: 2s;"></div>

        <!-- Hero Section -->
        <section class="max-w-5xl mx-auto text-center mb-32 relative">
            <div class="inline-block px-6 py-2 rounded-full bg-white/50 border border-white/50 backdrop-blur-md mb-8 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-[0.3em] text-vibrant-purple">Manifesto Tecnológico</span>
            </div>
            <h1 class="font-outfit text-4xl md:text-6xl lg:text-7xl font-black leading-tight mb-12 text-slate-900 tracking-tight">
                "Tecnologia <span class="text-gradient">aberta e transparente</span>: por um mundo onde o conhecimento pertence a todos."
            </h1>
            
            <div class="flex flex-col items-center gap-4">
                <div class="w-24 h-[2px] bg-gradient-to-right from-transparent via-vibrant-pink to-transparent"></div>
                <h2 class="font-outfit text-2xl md:text-3xl font-bold text-slate-800">
                    Geovane S. Maximiano
                </h2>
                <p class="text-slate-500 font-medium">Software Developer</p>
            </div>
        </section>

        <!-- Projects Grid -->
        <section class="max-w-6xl mx-auto relative">
            <div class="flex items-center gap-4 mb-16">
                <div class="h-[1px] flex-grow bg-slate-200"></div>
                <h3 class="font-outfit text-xs font-black uppercase tracking-[0.4em] text-slate-400">Meus Projetos</h3>
                <div class="h-[1px] flex-grow bg-slate-200"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- OpenGabarito -->
                <div class="glass p-10 rounded-[2.5rem] card-glow-purple transition-all duration-500 group">
                    <div class="w-14 h-14 bg-gradient-to-br from-vibrant-purple to-vibrant-blue rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg mb-8 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <h4 class="font-outfit text-3xl font-black text-slate-900 mb-4 tracking-tight">OpenGabarito</h4>
                    <p class="text-slate-500 leading-relaxed mb-8">
                        Revolucionando o acompanhamento de concursos através da inteligência coletiva e transparência absoluta nos rankings.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-purple uppercase tracking-wider border border-purple-100 shadow-sm">HTML</span>
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-blue uppercase tracking-wider border border-blue-100 shadow-sm">JavaScript</span>
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-pink uppercase tracking-wider border border-pink-100 shadow-sm">PHP</span>
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-cyan uppercase tracking-wider border border-cyan-100 shadow-sm">CSS</span>
                    </div>
                </div>

                <!-- Assessoria MEI -->
                <div class="glass p-10 rounded-[2.5rem] card-glow-pink transition-all duration-500 group">
                    <div class="w-14 h-14 bg-gradient-to-br from-vibrant-pink to-vibrant-rose rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg mb-8 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-toolbox"></i>
                    </div>
                    <h4 class="font-outfit text-3xl font-black text-slate-900 mb-4 tracking-tight">Assessoria MEI</h4>
                    <p class="text-slate-500 leading-relaxed mb-8">
                        Hub estratégico para microempreendedores, simplificando a burocracia e potencializando pequenos negócios.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-purple uppercase tracking-wider border border-purple-100 shadow-sm">HTML</span>
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-blue uppercase tracking-wider border border-blue-100 shadow-sm">JavaScript</span>
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-pink uppercase tracking-wider border border-pink-100 shadow-sm">PHP</span>
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-cyan uppercase tracking-wider border border-cyan-100 shadow-sm">CSS</span>
                    </div>
                </div>

                <!-- Contora ERP -->
                <div class="glass p-10 rounded-[2.5rem] card-glow-amber transition-all duration-500 group">
                    <div class="w-14 h-14 bg-gradient-to-br from-vibrant-amber to-vibrant-pink rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg mb-8 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <h4 class="font-outfit text-3xl font-black text-slate-900 mb-4 tracking-tight">Contora ERP</h4>
                    <p class="text-slate-500 leading-relaxed mb-8">
                        Gestão empresarial de alta performance focada em experiência do usuário e eficiência operacional em tempo real.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-purple uppercase tracking-wider border border-purple-100 shadow-sm">HTML</span>
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-blue uppercase tracking-wider border border-blue-100 shadow-sm">JavaScript</span>
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-pink uppercase tracking-wider border border-pink-100 shadow-sm">PHP</span>
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-cyan uppercase tracking-wider border border-cyan-100 shadow-sm">CSS</span>
                    </div>
                </div>

                <!-- Bem na Prática -->
                <div class="glass p-10 rounded-[2.5rem] card-glow-blue transition-all duration-500 group">
                    <div class="w-14 h-14 bg-gradient-to-br from-vibrant-blue to-vibrant-cyan rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg mb-8 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-hands-holding"></i>
                    </div>
                    <h4 class="font-outfit text-3xl font-black text-slate-900 mb-4 tracking-tight">Bem na Prática</h4>
                    <p class="text-slate-500 leading-relaxed mb-8">
                        Tecnologia a serviço da empatia, garantindo transparência absoluta na rastreabilidade de doações sociais.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-purple uppercase tracking-wider border border-purple-100 shadow-sm">HTML</span>
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-blue uppercase tracking-wider border border-blue-100 shadow-sm">JavaScript</span>
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-pink uppercase tracking-wider border border-pink-100 shadow-sm">PHP</span>
                        <span class="px-4 py-1.5 bg-white rounded-xl text-[10px] font-bold text-vibrant-cyan uppercase tracking-wider border border-cyan-100 shadow-sm">CSS</span>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php echo getFooter(); ?>

</body>
</html>
