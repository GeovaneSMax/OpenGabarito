<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ui_helper.php';
require_once __DIR__ . '/../includes/auth.php';

// CSS específico para a página de transparência
$extra_css = "
    .tech-card { background: white; border: 1px solid rgba(0, 0, 0, 0.05); }
    .feature-icon { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 16px; margin-bottom: 1.5rem; }
    .stat-card { background: #f8fafc; border: 1px solid #e2e8f0; }
";

?>
<!DOCTYPE html>
<html lang="pt-BR" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transparência & Tecnologia | OpenGabarito</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;900&display=swap');
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; color: #0f172a; }
        .bg-mesh {
            background-image: radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.05) 0px, transparent 50%),
                              radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.03) 0px, transparent 50%);
        }
        .gradient-text { background: linear-gradient(135deg, #4f46e5 0%, #10b981 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        <?php echo $extra_css; ?>
    </style>
</head>
<body class="bg-mesh min-h-screen">

    <nav class="border-b border-slate-100 bg-white/80 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2">
                <?php echo getLogoSVG(32); ?>
                <span class="font-black text-slate-900 tracking-tighter text-xl">Open<span class="text-indigo-600">Gabarito</span></span>
            </a>
            <a href="index.php" class="text-xs font-bold text-slate-400 hover:text-slate-900 transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Voltar para a Home
            </a>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-16">
        <header class="text-center mb-20">
            <div class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-600 px-4 py-2 rounded-full text-[10px] font-black uppercase tracking-widest mb-6 border border-indigo-100 shadow-sm">
                <i class="fa-solid fa-microchip"></i> Engine v2.5 "Full Transparency"
            </div>
            <h1 class="text-5xl md:text-6xl font-black text-slate-900 mb-6 tracking-tighter">Onde a Verdade é <br><span class="gradient-text">Algoritmo</span></h1>
            <p class="text-lg text-slate-500 max-w-2xl mx-auto leading-relaxed">
                Diferente de plataformas que cobram para mostrar projeções, o OpenGabarito é 100% gratuito, financiado de forma independente e com código focado em honestidade técnica.
            </p>
        </header>

        <!-- Seção Financeira -->
        <div class="tech-card p-10 rounded-[32px] mb-12 shadow-sm border-slate-100 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-10 opacity-[0.03] rotate-12">
                <i class="fa-solid fa-hand-holding-dollar text-9xl"></i>
            </div>
            
            <div class="flex flex-col md:flex-row gap-12 items-center">
                <div class="flex-1 text-center md:text-left">
                    <h2 class="text-3xl font-black text-slate-900 mb-4 tracking-tight">Transparência Financeira</h2>
                    <p class="text-slate-500 mb-8 leading-relaxed">
                        Este site não possui fins lucrativos. Ele é mantido integralmente por <strong>Geovane S. Maximiano</strong>, utilizando recursos gerados por seus outros projetos profissionais pagos. O objetivo é retribuir à comunidade de concurseiros.
                    </p>
                    <div class="inline-flex items-center gap-3 bg-emerald-50 text-emerald-700 px-6 py-3 rounded-2xl font-bold border border-emerald-100 shadow-sm">
                        <i class="fa-solid fa-heart text-emerald-500"></i> 100% Gratuito de Verdade
                    </div>
                </div>
                
                <div class="w-full md:w-80 grid grid-cols-1 gap-4">
                    <div class="stat-card p-6 rounded-2xl">
                        <span class="text-[10px] font-black text-slate-400 uppercase block mb-1">Domínio (.com.br)</span>
                        <span class="text-2xl font-black text-slate-900">R$ 40,00 <span class="text-xs font-normal text-slate-500">/ano</span></span>
                    </div>
                    <div class="stat-card p-6 rounded-2xl">
                        <span class="text-[10px] font-black text-slate-400 uppercase block mb-1">Hospedagem (Pay-as-you-go)</span>
                        <span class="text-2xl font-black text-slate-900">~R$ 420,00 <span class="text-xs font-normal text-slate-500">/ano</span></span>
                        <p class="text-[10px] text-slate-400 mt-2 italic">*Estimado R$ 35,00/mês na infraestrutura cloud.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensagem de Garantia -->
        <div class="bg-indigo-600 rounded-[32px] p-8 mb-20 text-white shadow-xl shadow-indigo-500/20 relative overflow-hidden">
            <div class="relative z-10 flex flex-col md:flex-row items-center gap-6">
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center text-3xl">
                    <i class="fa-solid fa-infinity"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold mb-1">O site nunca vai parar.</h4>
                    <p class="text-indigo-100 text-sm">Como apoiador de projetos <strong>Open Source</strong>, Geovane garante a manutenção vitalícia da plataforma. Não há risco de os dados desaparecerem ou tornarem-se pagos.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-20">
            <!-- Inteligência Artificial -->
            <div class="tech-card p-8 rounded-3xl group hover:shadow-lg transition-all border-slate-100">
                <div class="feature-icon bg-indigo-50 text-indigo-600 border border-indigo-100 shadow-sm">
                    <i class="fa-solid fa-brain text-xl"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-4 italic">Cérebro Híbrido (Groq + Gemini)</h3>
                <p class="text-sm text-slate-500 leading-relaxed mb-6">
                    Utilizamos a infraestrutura da <strong>Groq</strong> operando em seu <strong>plano gratuito</strong>, que é plenamente suficiente para atender a demanda atual do site com altíssima velocidade. O <strong>Google Gemini</strong> é utilizado como um sistema de <i>failover</i> (contingência), operando estritamente dentro dos limites da API gratuita.
                </p>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <span class="text-[10px] font-black text-indigo-600 uppercase block mb-2">Processamento:</span>
                    <p class="text-[11px] text-slate-500 italic leading-snug">
                        "As IAs validam a verossimilhança de cada concurso cadastrado, prevenindo Trolls e mantendo a integridade da base de dados."
                    </p>
                </div>
            </div>

            <!-- Inteligência Coletiva -->
            <div class="tech-card p-8 rounded-3xl group hover:shadow-lg transition-all border-slate-100">
                <div class="feature-icon bg-emerald-50 text-emerald-600 border border-emerald-100 shadow-sm">
                    <i class="fa-solid fa-people-group text-xl"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-4 italic">Consenso Ponderado Wiki</h3>
                <p class="text-sm text-slate-500 leading-relaxed mb-6">
                    Nosso gabarito não é estático. Ele é calculado ponderando as respostas pela <strong>Reputação (Trust Score)</strong> de cada colaborador. Se você ajuda e acerta, sua opinião vale mais para o grupo.
                </p>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <span class="text-[10px] font-black text-emerald-600 uppercase block mb-2">Lógica de Auditoria:</span>
                    <p class="text-[11px] text-slate-500 italic leading-snug">
                        "Detectamos automaticamente anomalias estatísticas e excluímos votos de baixa concordância (\u003c 15%) para evitar poluição."
                    </p>
                </div>
            </div>
        </div>

        <!-- Deep Dive Técnico -->
        <div class="tech-card p-10 rounded-[40px] mb-20 shadow-sm border-slate-100">
            <h2 class="text-3xl font-black text-slate-900 mb-10 tracking-tight flex items-center gap-3">
                <i class="fa-solid fa-square-root-variable text-indigo-600"></i> O Algoritmo de Ranking
            </h2>
            
            <div class="space-y-12">
                <!-- Z-Score -->
                <div class="flex flex-col md:flex-row gap-8">
                    <div class="w-full md:w-1/3">
                        <div class="bg-slate-900 text-indigo-300 p-6 rounded-3xl font-mono text-sm shadow-xl">
                            <span class="text-slate-500 block mb-2">// Z-Score Formula</span>
                            z = (x - μ) / σ
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-lg font-bold text-slate-900 mb-2">Projeção via Curva de Gauss</h4>
                        <p class="text-sm text-slate-500 leading-relaxed">
                            Para calcular sua <strong>Posição Estimada</strong>, usamos o <strong>Z-Score</strong> (Desvio Padrão). Calculamos a média (\u03bc) e o desvio (\u03c3) de todos os participantes para determinar onde você se encaixa na curva de sino estatística, extrapolando para o total de inscritos reais.
                        </p>
                    </div>
                </div>

                <div class="h-px bg-slate-100"></div>

                <!-- Clustering -->
                <div class="flex flex-col md:flex-row-reverse gap-8">
                    <div class="w-full md:w-1/3">
                        <div class="bg-slate-900 text-emerald-300 p-6 rounded-3xl font-mono text-sm shadow-xl">
                            <span class="text-slate-500 block mb-2">// Clustering Logic</span>
                            if (correlation \u003e 0.8) {<br>
                            &nbsp;&nbsp;new_family(group);<br>
                            }
                        </div>
                    </div>
                    <div class="flex-1 text-left">
                        <h4 class="text-lg font-bold text-slate-900 mb-2">Descoberta Automática de Versões</h4>
                        <p class="text-sm text-slate-500 leading-relaxed">
                            Através de <strong>Clustering</strong>, nosso sistema identifica se um grupo de usuários está respondendo de forma idêntica, mas diferente do gabarito oficial. Isso permite descobrir novas versões de prova (V1, V2, V3) automaticamente.
                        </p>
                    </div>
                </div>

                <div class="h-px bg-slate-100"></div>

                <!-- PNC -->
                <div class="flex flex-col md:flex-row gap-8">
                    <div class="w-full md:w-1/3">
                        <div class="bg-slate-900 text-amber-300 p-6 rounded-3xl font-mono text-sm shadow-xl">
                            <span class="text-slate-500 block mb-2">// Note of Corte Projection</span>
                            PNC = Top(20%) * factor
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-lg font-bold text-slate-900 mb-2">Predição de Nota de Corte (PNC)</h4>
                        <p class="text-sm text-slate-500 leading-relaxed">
                            Quando a amostra é pequena (\u003c 10), usamos extrapolação IA. Como os primeiros a cadastrar são estatisticamente os mais competitivos, ajustamos a média para baixo com base na dispersão histórica para projetar o corte final.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center">
            <p class="text-slate-400 text-xs italic">\"Software é opinião. A nossa é que a tecnologia deve ser livre e transparente.\"</p>
        </div>

    </main>

    <?php echo getFooter(); ?>

</body>
</html>
