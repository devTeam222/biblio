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
let activityChart = null;
let currentActivitiesPage = 1;
const ACTIVITIES_PER_PAGE = 5;

document.addEventListener('DOMContentLoaded', async () => {
    const authStatus = await isAuth();
    const logoutButton = document.getElementById('logoutButton');

    if (!authStatus.success || !authStatus?.roles.includes('admin')) {
        await showCustomModal('Accès non autorisé. Vous devez être un administrateur pour accéder à cette page.', { type: 'alert' });
        window.location.href = '/login';
        return;
    }

    // Mise à jour de l'interface utilisateur
    userNameDisplay.textContent = authStatus?.user?.name || 'Admin';
    updateNavBar('admin');
    updateLastModifiedTime();

    // Gestion de la déconnexion
    logoutButton && (logoutButton.addEventListener('click', handleLogout));

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
            showCustomModal(`Erreur chargement stats: ${statsResponse.data.message}`);
        }

        // Charger les emprunts en retard
        const overdueResponse = await apiClient.get('/api/admin/dashboard.php?action=overdue_loans');
        if (overdueResponse.data.success) {
            updateOverdueLoans(overdueResponse.data.data);
        } else {
            console.error("Erreur chargement emprunts en retard:", overdueResponse.data.message);
            showCustomModal(`Erreur chargement emprunts en retard: ${overdueResponse.data.message}`);
        }

        // Charger l'activité récente
        const activityResponse = await apiClient.get(`/api/admin/dashboard.php?action=recent_activity&page=${currentActivitiesPage}&per_page=${ACTIVITIES_PER_PAGE}`);
        if (activityResponse.data.success) {
            updateRecentActivities(activityResponse.data.data);
        } else {
            console.error("Erreur chargement activité récente:", activityResponse.data.message);
            showCustomModal(`Erreur chargement activité récente: ${activityResponse.data.message}`);
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
                    <p class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-circle mr-1"></i> Retour attendu: ${returnDate}</p>
                </div>
                <button class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                    Gérer <i class="fas fa-chevron-right ml-1"></i>
                </button>
            `;
            overdueLoansListEl.appendChild(loanEl);
        });
    } else {
        overdueLoansListEl.innerHTML = `
            <li class="py-4 text-center text-gray-500">
                <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
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
                        <i class="fas fa-info-circle text-gray-500"></i>
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
        'loan': '<i class="fas fa-book-reader text-indigo-600 text-sm"></i>',
        'return': '<i class="fas fa-book text-green-600 text-sm"></i>',
        'user': '<i class="fas fa-user-plus text-blue-600 text-sm"></i>',
        'system': '<i class="fas fa-cog text-purple-600 text-sm"></i>',
        'default': '<i class="fas fa-info-circle text-gray-600 text-sm"></i>'
    };

    return icons[activityType] || icons.default;
}

async function loadMoreActivities() {
    currentActivitiesPage++;
    await loadDashboardData();
}

function initActivityChart() {
    const ctx = document.getElementById('activityChart').getContext('2d');

    // Données factices pour l'exemple - à remplacer par des données réelles
    activityChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            datasets: [
                {
                    label: 'Emprunts',
                    data: [12, 19, 8, 15, 12, 5, 9],
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Retours',
                    data: [8, 11, 5, 12, 10, 3, 7],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.3,
                    fill: true
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
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 5
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