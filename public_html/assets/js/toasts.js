/**
 * OpenGabarito Toast System
 * Exibe notificações elegantes e não intrusivas.
 */
const Toast = {
    init() {
        if (!document.getElementById('toast-container')) {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed bottom-6 right-6 z-[9999] flex flex-col gap-3 pointer-events-none';
            document.body.appendChild(container);
        }
    },

    show(message, type = 'success', duration = 4000) {
        this.init();
        const container = document.getElementById('toast-container');
        
        const toast = document.createElement('div');
        const colors = {
            success: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400',
            error: 'bg-rose-500/10 border-rose-500/20 text-rose-400',
            warning: 'bg-amber-500/10 border-amber-500/20 text-amber-400',
            info: 'bg-indigo-500/10 border-indigo-500/20 text-indigo-400'
        };
        
        const icons = {
            success: 'fa-circle-check',
            error: 'fa-circle-xmark',
            warning: 'fa-triangle-exclamation',
            info: 'fa-circle-info'
        };

        toast.className = `${colors[type]} border backdrop-blur-md px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3 animate-slide-in pointer-events-auto transition-all duration-300`;
        toast.innerHTML = `
            <i class="fa-solid ${icons[type]} text-lg"></i>
            <span class="text-sm font-bold">${message}</span>
        `;

        container.appendChild(toast);

        // Auto-remove
        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
};

// Adicionar estilos de animação
const style = document.createElement('style');
style.textContent = `
    @keyframes slide-in {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    .animate-slide-in { animation: slide-in 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
`;
document.head.appendChild(style);
