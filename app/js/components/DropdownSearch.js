// app/js/components/DropdownSearch.js

import { apiClient } from "../util/ocho-api.js"; // Assurez-vous que le chemin est correct
import { addLoader, debounce, removeLoader } from "../util/utils.js"; // Réutilisez la fonction debounce existante

/**
 * Crée un composant de sélection avec recherche et chargement de données distant/local.
 * @param {Object} options - Options de configuration pour le composant.
 * @param {string} options.targetElementId - L'ID de l'élément DIV où le composant sera rendu.
 * @param {string} options.listType - Le type de liste à récupérer (e.g., 'authors', 'departement').
 * @param {string} [options.apiBaseUrl='/api/admin/lists.php'] - L'URL de base pour le point d'API unique de liste.
 * @param {string} [options.idField='id'] - Le nom du champ qui contient l'ID dans les données de l'API.
 * @param {string} [options.textField='name'] - Le nom du champ qui contient le texte à afficher dans les données de l'API.
 * @param {string} [options.placeholder='Sélectionner une option'] - Le texte d'espace réservé pour le champ de recherche.
 * @param {number|string|null} [options.initialSelectedId=null] - L'ID de l'option initialement sélectionnée.
 * @param {string} [options.searchParam='search'] - Le nom du paramètre de recherche à envoyer à l'API.
 * @param {number} [options.debounceDelay=300] - Le délai de debounce en ms pour la recherche.
 * @returns {Object} Un objet avec les méthodes pour interagir avec le composant (getValue, setValue, clear, getTypedValue).
 */
export function createDropdownSearch(options) {
    const {
        targetElementId,
        listType, // Nouveau: Le type d'entité à lister (ex: 'authors')
        apiBaseUrl = '/api/admin/lists.php', // Nouveau: Point d'API commun pour toutes les listes
        idField = 'id',
        textField = 'name',
        placeholder = 'Sélectionner une option',
        initialSelectedId = null,
        searchParam = 'search',
        debounceDelay = 300
    } = options;

    const targetElement = document.getElementById(targetElementId);
    if (!targetElement) {
        console.error(`DropdownSearch: L'élément cible avec l'ID "${targetElementId}" est introuvable.`);
        return null;
    }

    // Vérification défensive pour s'assurer que listType est valide
    if (!listType || typeof listType !== 'string' || listType.trim() === '') {
        console.error(`DropdownSearch: 'listType' est manquant, vide, ou n'est pas une chaîne valide pour l'élément "${targetElementId}".`);
        return null;
    }

    let allData = []; // Déclaré avec 'let' pour permettre la réaffectation
    let filteredData = []; // Données filtrées basées sur la recherche
    let selectedValue = initialSelectedId; // L'ID de l'élément actuellement sélectionné

    let searchInput;
    let optionsList;
    let selectedDisplay;

    /**
     * Initialise le composant en rendant sa structure HTML.
     */
    function init() {
        targetElement.innerHTML = `
            <div class="relative w-full body">
                <div class="flex items-center justify-between border border-gray-300 rounded-md shadow-sm p-2 cursor-pointer bg-white" id="${targetElementId}-display">
                    <span class="text-gray-700 overflow-hidden whitespace-nowrap text-ellipsis flex-grow" id="${targetElementId}-selected-display">${placeholder}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down text-gray-400"><path d="m6 9 6 6 6-6"/></svg>
                </div>
                <div class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 hidden" id="${targetElementId}-options-container">
                    <input type="text" placeholder="Rechercher..." class="w-full p-2 border-b border-gray-200 focus:outline-none focus:ring-green-500 focus:border-green-500" id="${targetElementId}-search-input">
                    <ul class="max-h-60 overflow-y-auto" id="${targetElementId}-options-list">
                        <!-- Options will be loaded here -->
                    </ul>
                </div>
            </div>
        `;
        addLoader(targetElement); // Ajoute un loader pendant le chargement initial
        targetElement.classList.add("flex", "items-center", "gap-2");
        selectedDisplay = document.getElementById(`${targetElementId}-selected-display`);
        const displayWrapper = document.getElementById(`${targetElementId}-display`);
        const optionsContainer = document.getElementById(`${targetElementId}-options-container`);
        searchInput = document.getElementById(`${targetElementId}-search-input`);
        optionsList = document.getElementById(`${targetElementId}-options-list`);

        displayWrapper.addEventListener('click', () => {
            optionsContainer.classList.toggle('hidden');
            if (!optionsContainer.classList.contains('hidden')) {
                searchInput.focus();
                // Re-render options to ensure they are up-to-date with current search/filter
                renderOptions(); 
            }
        });

        // Fermer le dropdown si on clique en dehors
        document.addEventListener('click', (event) => {
            if (!targetElement.contains(event.target) && !optionsContainer.classList.contains('hidden')) {
                optionsContainer.classList.add('hidden');
            }
        });

        const debouncedFetchData = debounce((query) => {
            fetchData(query);
        }, debounceDelay);

        searchInput.addEventListener('input', (event) => {
            const query = event.target.value;
            // Toujours utiliser fetchData car le composant est maintenant conçu pour un endpoint unique
            debouncedFetchData(query);
        });

        optionsList.addEventListener('click', (event) => {
            const selectedOption = event.target.closest('li[data-id]');
            if (selectedOption) {
                const id = selectedOption.dataset.id;
                selectOption(id);
                optionsContainer.classList.add('hidden');
            }
        });

        // Charger les données initiales et définir la sélection
        loadInitialData();
    }

    /**
     * Charge les données initiales depuis l'API ou utilise les données préexistantes si fournies.
     * Met à jour la sélection initiale si un ID est fourni.
     */
    async function loadInitialData() {
        // CHANGEMENT: Await fetchData ici pour s'assurer que allData est peuplé
        await fetchData(''); 
        // L'affichage sera mis à jour une fois les données chargées via fetchData -> renderOptions()
        // et updateSelectedDisplay() est appelé à l'intérieur de fetchData si succès
    }

    /**
     * Récupère les données depuis l'API en fonction d'une requête de recherche et du type de liste.
     * @param {string} query - La chaîne de recherche.
     */
    async function fetchData(query) {
        try {
            // Construction d'une URL absolue en utilisant l'origine actuelle comme base
            const baseUrl = window.location.origin;
            // L'URL de l'API est construite avec le listApiBaseUrl et le type de liste
            const url = new URL(apiBaseUrl, baseUrl);
            
            url.searchParams.append('type', listType); // Ajoute le type d'entité (authors, departement, etc.)
            url.searchParams.append('action', 'list'); // L'action par défaut pour la récupération de liste

            if (query) {
                url.searchParams.append(searchParam, query);
            }
            // Ajouter un paramètre pour récupérer toutes les données si l'endpoint est pour une liste paginée
            url.searchParams.append('limit', '9999'); // Demande une grande limite pour tout récupérer

            const response = await apiClient.get(url.toString());
            
            if (response.data.success) {
                allData = response.data.data; 
                filteredData = allData; // Initialiser filteredData avec toutes les données
                
                renderOptions();
                // CHANGEMENT: Appeler updateSelectedDisplay ici APRÈS que allData est peuplé
                updateSelectedDisplay(); 
            } else {
                console.error(`DropdownSearch: Erreur lors du chargement des données de type "${listType}" depuis ${apiBaseUrl}:`, response.data.message);
                optionsList.innerHTML = `<li class="p-2 text-red-500">Erreur de chargement.</li>`;
            }
        } catch (error) {
            console.error(`DropdownSearch: Erreur réseau ou API lors du chargement des données de type "${listType}" depuis ${apiBaseUrl}:`, error);
            optionsList.innerHTML = `<li class="p-2 text-red-500">Erreur réseau.</li>`;
        } finally {
            removeLoader(targetElement); // Retire le loader une fois les données chargées
            if (filteredData.length === 0) {
                optionsList.innerHTML = `<li class="p-2 text-gray-500">Aucune option trouvée.</li>`;
            }
        }
    }

    /**
     * Filtre les options localement en fonction de la requête de recherche.
     * @param {string} query - La chaîne de recherche.
     */
    function filterOptions(query) {
        const lowerCaseQuery = query.toLowerCase();
        filteredData = allData.filter(item =>
            String(item[textField]).toLowerCase().includes(lowerCaseQuery)
        );
        renderOptions();
    }

    /**
     * Rend les options filtrées dans la liste.
     */
    function renderOptions() {
        optionsList.innerHTML = '';
        if (filteredData.length === 0) {
            optionsList.innerHTML = `<li class="p-2 text-gray-500">Aucune option trouvée.</li>`;
            return;
        }
        filteredData.forEach(item => {
            const li = document.createElement('li');
            li.className = `p-2 cursor-pointer hover:bg-green-100 ${String(item[idField]) === String(selectedValue) ? 'bg-green-50 text-green-700' : ''}`;
            li.dataset.id = item[idField];
            li.textContent = item[textField];
            optionsList.appendChild(li);
        });
    }

    /**
     * Sélectionne une option et met à jour l'affichage.
     * @param {string|number} id - L'ID de l'option à sélectionner.
     */
    function selectOption(id) {
        selectedValue = id;
        updateSelectedDisplay();
        renderOptions(); // Pour mettre à jour la classe 'selected'
    }

    /**
     * Met à jour le texte affiché dans le sélecteur principal.
     */
    function updateSelectedDisplay() {
        const selectedItem = allData.find(item => String(item[idField]) === String(selectedValue));
        
        if (selectedItem) {
            selectedDisplay.textContent = selectedItem[textField];
            selectedDisplay.classList.remove('text-gray-700');
            selectedDisplay.classList.add('font-medium');
        } else {
            selectedDisplay.textContent = placeholder;
            selectedDisplay.classList.remove('font-medium');
            selectedDisplay.classList.add('text-gray-700');
        }
    }

    // Initialisation au chargement
    init();
    

    return {
        /**
         * Retourne l'ID de l'option actuellement sélectionnée.
         * @returns {string|number|null}
         */
        getValue: () => selectedValue,
        /**
         * Définit la valeur sélectionnée programmatiquement.
         * @param {string|number|null} id - L'ID à sélectionner.
         */
        setValue: (id) => {
            selectedValue = id;
            updateSelectedDisplay();
            renderOptions();
        },
        /**
         * Efface la sélection et la recherche.
         */
        clear: () => {
            selectedValue = null;
            searchInput.value = '';
            filteredData = allData;
            updateSelectedDisplay();
            renderOptions();
        },
        // Exposer allData pour le débogage ou si nécessaire pour d'autres logiques
        allData: allData, // Ajouté pour faciliter l'accès aux données chargées
        /**
         * Retourne le texte actuellement tapé dans le champ de recherche.
         * @returns {string}
         */
        getTypedValue: () => searchInput.value,
        getAllData: () => allData // Expose allData pour un accès externe
    };
}
