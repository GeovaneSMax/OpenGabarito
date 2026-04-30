/* ranking.js */
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('search-candidate');
    const tableRows = document.querySelectorAll('.ranking-row');
    const simulateBtn = document.getElementById('open-simulate');
    const simulateModal = document.getElementById('simulate-modal');
    const closeSimulate = document.getElementById('close-simulate');
    
    // Search Filtering
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            tableRows.forEach(row => {
                const name = row.dataset.name.toLowerCase();
                row.style.display = name.includes(term) ? '' : 'none';
            });
        });
    }

    // Modal Logic
    if (simulateBtn && simulateModal) {
        simulateBtn.addEventListener('click', () => simulateModal.classList.remove('hidden'));
        closeSimulate.addEventListener('click', () => simulateModal.classList.add('hidden'));
        
        window.onclick = (event) => {
            if (event.target == simulateModal) {
                simulateModal.classList.add('hidden');
            }
        };
    }

    // Simulation Logic
    const applySimulation = document.getElementById('apply-simulation');
    if (applySimulation) {
        applySimulation.addEventListener('click', () => {
            const nulledQuestions = Array.from(document.querySelectorAll('.null-q:checked')).map(cb => cb.value);
            console.log('Nulling questions:', nulledQuestions);
            
            // In a real app, we'd recalculate via AJAX or locally if we have all JSON data
            // For this demo, we'll just show an alert and simulate a refresh
            alert('Simulando anulação das questões: ' + nulledQuestions.join(', ') + '\nO ranking será recalculado...');
            simulateModal.classList.add('hidden');
        });
    }

    // Chart Initialization
    const ctx = document.getElementById('rankingChart');
    if (ctx && window.rankingData) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: window.rankingLabels,
                datasets: [{
                    label: 'Candidatos',
                    data: window.rankingData,
                    backgroundColor: 'rgba(99, 102, 241, 0.5)',
                    borderColor: '#6366f1',
                    borderWidth: 2,
                    borderRadius: 4,
                    hoverBackgroundColor: '#6366f1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#64748b', font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b', font: { size: 10 } }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#fff',
                        bodyColor: '#cbd5e1',
                        borderColor: 'rgba(71, 85, 105, 0.4)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false
                    }
                }
            }
        });
    }
});

function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');
    // Implement tab filtering logic here
    console.log('Switching to tab:', tab);
}

function scrollToUser() {
    const userRow = document.getElementById('my-rank-row');
    if (userRow) {
        userRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        userRow.classList.add('ring-2', 'ring-indigo-500', 'bg-indigo-500/20');
        setTimeout(() => {
            userRow.classList.remove('ring-2', 'ring-indigo-500', 'bg-indigo-500/20');
        }, 2000);
    } else {
        alert('Você ainda não possui nota cadastrada neste ranking.');
    }
}
