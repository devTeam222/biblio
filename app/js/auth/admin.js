import { apiClient } from "../util/ocho-api.js";
import { TimeFormatter } from "../util/formatter.js";
import { showCustomModal, addLoader, removeLoader, isAuth, handleLogout, updateNavBar } from "../util/utils.js";

// Assurez-vous que modalContainer est accessible globalement pour showCustomModal
window.modalContainer = document.getElementById('modalContainer');

// Éléments du DOM
const userNameDisplay = document.getElementById('userNameDisplay');
const notificationCountEl = document.getElementById('notificationCount');
const overdueCountEl = document.getElementById('overdueCount');
const lastUpdateTimeEl = document.getElementById('lastUpdateTime');
const viewAllOverdueBtn = document.getElementById('viewAllOverdueBtn');
const loadMoreActivitiesBtn = document.getElementById('loadMoreActivities');



// Variables globales
let activityChartInstance = null; // Renommé pour éviter le conflit avec la fonction Chart
let currentActivitiesPage = 1;
const ACTIVITIES_PER_PAGE = 5;

document.addEventListener('DOMContentLoaded', async () => {
    const authStatus = await isAuth();

    if (!authStatus.success || !authStatus?.roles.includes('admin')) {
        await showCustomModal('Accès non autorisé. Vous devez être un administrateur pour accéder à cette page.', { type: 'alert' });
        window.location.href = '/login';
        return;
    }

    // Mise à jour de l'interface utilisateur
    userNameDisplay.textContent = authStatus?.user?.name || 'Admin';
    updateNavBar('admin');
    updateLastModifiedTime();

    // Chargement des données
    await loadDashboardData();

    // Initialisation du graphique
    initActivityChart();

    // Événements
    viewAllOverdueBtn.addEventListener('click', () => {
        showCustomModal('Fonctionnalité à implémenter: Liste complète des emprunts en retard', {
            type: 'info'
        });
    });

    loadMoreActivitiesBtn.addEventListener('click', loadMoreActivities);

    // Actualisation automatique toutes les 5 minutes
    setInterval(loadDashboardData, 300000);
});

function updateLastModifiedTime() {
    const now = new Date();
    const formatter = new TimeFormatter(now, { lang: navigator.language, long: true });
    lastUpdateTimeEl.textContent = formatter.format();
}

async function loadDashboardData() {
    try {
        addLoader(document.querySelector('main'));

        // Charger les statistiques globales
        const statsResponse = await apiClient.get('/api/admin/dashboard.php?action=stats');
        if (statsResponse.data.success) {
            updateStats(statsResponse.data.data);
        } else {
            console.error("Erreur chargement stats:", statsResponse.data.message);
            showCustomModal(`Erreur chargement stats: ${statsResponse.data.message || 'Erreur inconnue'}`);
        }

        // Charger les emprunts en retard
        const overdueResponse = await apiClient.get('/api/admin/dashboard.php?action=overdue_loans');
        if (overdueResponse.data.success) {
            updateOverdueLoans(overdueResponse.data.data);
        } else {
            console.error("Erreur chargement emprunts en retard:", overdueResponse.data.message);
            showCustomModal(`Erreur chargement emprunts en retard: ${overdueResponse.data.message || "Erreur inconnue"}`);
        }

        // Charger l'activité récente
        const activityResponse = await apiClient.get(`/api/admin/dashboard.php?action=recent_activity&page=${currentActivitiesPage}&per_page=${ACTIVITIES_PER_PAGE}`);
        if (activityResponse.data.success) {
            updateRecentActivities(activityResponse.data.data);
        } else {
            console.error("Erreur chargement activité récente:", activityResponse.data.message);
            showCustomModal(`Erreur chargement activité récente: ${activityResponse.data.message || "Erreur inconnue"}`);
        }

        // Mettre à jour le temps de dernière modification
        updateLastModifiedTime();

    } catch (error) {
        console.error("Erreur lors du chargement des données:", error);
        showCustomModal("Une erreur est survenue lors du chargement des données du tableau de bord.");
    } finally {
        removeLoader(document.querySelector('main'));
    }
}

function updateStats(stats) {
    // Éléments pour les statistiques
    const totalBooksEl = document.getElementById('totalBooks');
    const availableBooksEl = document.getElementById('availableBooks');
    const totalUsersEl = document.getElementById('totalUsers');
    const currentLoansCountEl = document.getElementById('currentLoansCount');
    

    totalBooksEl.textContent = stats.total_books;
    availableBooksEl.textContent = stats.available_books;
    totalUsersEl.textContent = stats.total_users;
    currentLoansCountEl.textContent = stats.current_loans_count;

    // Animation des nombres
    animateValue(totalBooksEl, 0, stats.total_books, 1000);
    animateValue(availableBooksEl, 0, stats.available_books, 1000);
    animateValue(totalUsersEl, 0, stats.total_users, 1000);
    animateValue(currentLoansCountEl, 0, stats.current_loans_count, 1000);
}

function updateOverdueLoans(loans) {
    const overdueLoansListEl = document.getElementById('overdueLoansList');
    

    overdueLoansListEl.innerHTML = '';
    overdueCountEl.textContent = loans.length;
    notificationCountEl.textContent = loans.length;

    if (loans.length > 0) {
        loans.forEach(loan => {
            const returnDate = new TimeFormatter(loan.date_retour * 1000, { lang: navigator.language, long: false }).format();
            const loanEl = document.createElement('li');
            loanEl.className = 'py-3 flex items-center justify-between hover:bg-gray-50 px-2 rounded';
            loanEl.innerHTML = `
                <div>
                    <p class="font-medium text-gray-800">${loan.titre}</p>
                    <p class="text-sm text-gray-500">${loan.auteur}</p>
                    <p class="text-xs text-red-500 mt-1 flex items-center gap-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-alert-icon lucide-circle-alert">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" x2="12" y1="8" y2="12" />
                            <line x1="12" x2="12.01" y1="16" y2="16" />
                        </svg> 
                        <span>Retour attendu: ${returnDate}</span>
                    </p>
                </div>
                <button class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center">
                    <span>Gérer</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right">
                        <path d="m9 18 6-6-6-6" />
                    </svg>
                </button>
            `;
            overdueLoansListEl.appendChild(loanEl);
        });
    } else {
        overdueLoansListEl.innerHTML = `
            <li class="py-8 text-center text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="text-green-500 mx-auto" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-check-icon lucide-circle-check">
                    <circle cx="12" cy="12" r="10" />
                    <path d="m9 12 2 2 4-4" />
                </svg>
                <p>Aucun emprunt en retard</p>
            </li>
        `;
    }
}

function updateRecentActivities(activities) {
    const recentActivityListEl = document.getElementById('recentActivityList');
    if (currentActivitiesPage === 1) {
        recentActivityListEl.innerHTML = '';
    }

    if (activities.length > 0) {
        activities.forEach(activity => {
            const activityDate = new TimeFormatter(activity.timestamp * 1000, { lang: navigator.language, long: true }).format();
            const activityEl = document.createElement('li');
            activityEl.className = 'activity-item';
            activityEl.innerHTML = `
                <div class="flex items-start">
                    <div class="bg-indigo-100 p-2 rounded-full mr-3">
                        ${getActivityIcon(activity.type)}
                    </div>
                    <div>
                        <p class="text-sm text-gray-800">${activity.description}</p>
                        <p class="text-xs text-gray-400 mt-1">${activityDate}</p>
                    </div>
                </div>
            `;
            recentActivityListEl.appendChild(activityEl);
        });

        // Masquer le bouton si moins d'éléments que le maximum par page
        loadMoreActivitiesBtn.style.display = activities.length < ACTIVITIES_PER_PAGE ? 'none' : 'block';
    } else if (currentActivitiesPage === 1) {
        recentActivityListEl.innerHTML = `
            <li class="activity-item">
                <div class="flex items-start">
                    <div class="bg-gray-100 p-2 rounded-full mr-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-info-icon lucide-info">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 16v-4" />
                            <path d="M12 8h.01" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Aucune activité récente</p>
                    </div>
                </div>
            </li>
        `;
        loadMoreActivitiesBtn.style.display = 'none';
    }
}

function getActivityIcon(activityType) {
    const icons = {
        'loan': `<svg xmlns="http://www.w3.org/2000/svg" class="text-indigo-600" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open-check-icon lucide-book-open-check">
                    <path d="M12 21V7" />
                    <path d="m16 12 2 2 4-4" />
                    <path d="M22 6V4a1 1 0 0 0-1-1h-5a4 4 0 0 0-4 4 4 4 0 0 0-4-4H3a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h6a3 3 0 0 1 3 3 3 3 0 0 1 3-3h6a1 1 0 0 0 1-1v-1.3" />
                </svg>`,
        'return': `<svg xmlns="http://www.w3.org/2000/svg" class="text-green-600" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-text-icon lucide-book-text">
                    <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20" />
                    <path d="M8 11h8" />
                    <path d="M8 7h6" />
                </svg>`,
        'user': `<svg xmlns="http://www.w3.org/2000/svg" class="text-blue-600" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-plus-icon lucide-user-round-plus">
                    <path d="M2 21a8 8 0 0 1 13.292-6" />
                    <circle cx="10" cy="8" r="5" />
                    <path d="M19 16v6" />
                    <path d="M22 19h-6" />
                </svg>`,
        'system': `<svg xmlns="http://www.w3.org/2000/svg" class="text-purple-600" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings-icon lucide-settings">
                    <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
                    <circle cx="12" cy="12" r="3" />
                </svg>`,
        'default': `<svg xmlns="http://www.w3.org/2000/svg" class="text-gray-600" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-info-icon lucide-info">
                        <circle cx="12" cy="12" r="10" />
                        <path d="M12 16v-4" />
                        <path d="M12 8h.01" />
                    </svg>`
    };

    return icons[activityType] || icons.default;
}

async function loadMoreActivities() {
    currentActivitiesPage++;
    await loadDashboardData();
}

/**
 * Initialise et charge les données pour le graphique d'activité.
 */
async function initActivityChart() {
    const ctx = document.getElementById('activityChart');
    if (!ctx) {
        console.error("Canvas pour le graphique d'activité non trouvé.");
        return;
    }

    try {
        const response = await apiClient.get('/api/admin/dashboard.php?action=chart_data', { throwHttpErrors: true });
        if (response.data.success) {
            const chartData = response.data.data;
            const labels = chartData.labels.map(label => {
                // Formatter le temps unix en date lisible
                const date = new TimeFormatter(label * 1000, { lang: navigator.language, long: false, full: true });
                return date.formatCalendar("weekday").toLocaleUpperCase(); // Ex: "Lun 01 Jan"
            });
            const loanData = chartData.loan_data;
            const returnData = chartData.return_data; // Correction ici

            if (activityChartInstance) {
                activityChartInstance.destroy(); // Détruire l'ancienne instance si elle existe
            }

            activityChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Emprunts',
                            data: loanData,
                            borderColor: '#4f46e5', // Indigo-600
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            tension: 0.3,
                            fill: true,
                            pointBackgroundColor: '#4f46e5',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: '#4f46e5',
                        },
                        {
                            label: 'Retours',
                            data: returnData,
                            borderColor: '#10b981', // Green-600
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.3,
                            fill: true,
                            pointBackgroundColor: '#10b981',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: '#10b981',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#e2e8f0'
                            },
                            title: {
                                display: true,
                                text: 'Nombre'
                            },
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });

        } else {
            console.error("Erreur chargement données graphique:", response.data.message);
            showCustomModal(`Erreur chargement données graphique: ${response.data.message}`);
        }
    } catch (error) {
        console.error("Erreur lors du chargement des données du graphique d'activité:", error);
        showCustomModal("Une erreur est survenue lors du chargement des données du graphique.");
    }
}

function animateValue(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        element.textContent = Math.floor(progress * (end - start) + start);
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}