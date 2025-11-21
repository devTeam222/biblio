import { updateNavBar, isAuth, attachGlobalListeners } from "./utils";

/**
 * @file ocho-api.js
 * @description Cette classe permet de gérer les requêtes HTTP avec des fonctionnalités avancées
 * comme la gestion de la progression de téléchargement, les en-têtes personnalisés, les timeouts
 * et la gestion des erreurs HTTP. Elle offre une API Promise-based pour des interactions plus modernes.
 */
const THEME_STORAGE_KEY = "geolib-theme";

/**
 * @class OchoClient
 * @description Classe pour gérer les requêtes HTTP avec prise en charge des requêtes HTTP
 * avec prise en charge de la progression de téléchargement.
 */
export default class OchoClient {
    /**
     * Crée une instance de OchoClient.
     * @constructor
     * @param {string} baseUrl - L'URL de base pour toutes les requêtes.
     * @param {{}} [defaultOptions={}] - Les options par défaut pour toutes les requêtes.
     * @param {{}} [defaultOptions.headers={}] - Les en-têtes HTTP par défaut.
     * @param {FormData|{}|string|null} [defaultOptions.body=null] - Le corps de requête par défaut (peut être FormData, un objet, une chaîne JSON ou null).
     * @param {boolean} [defaultOptions.throwHttpErrors=true] - Si `true`, rejette la promesse pour les codes d'état HTTP >= 400.
     * @param {number} [defaultOptions.timeout=0] - Le délai d'attente en millisecondes pour les requêtes (0 pour aucun délai).
     */
    constructor(baseUrl, defaultOptions = {}) {
        // Vérifie si l'URL de base est valide.
        if (!baseUrl || typeof baseUrl !== "string") {
            throw new Error("baseUrl doit être une chaîne de caractères valide.");
        }

        // Nettoie l'URL de base en supprimant les slashes de fin.
        this.baseUrl = baseUrl.replace(/\/$/, "");
        /**
         * Options par défaut fusionnées avec les options fournies par l'utilisateur.
         * @private
         * @type {object}
         */
        this.defaultOptions = {
            headers: {},
            body: null,
            throwHttpErrors: true,
            timeout: 0, // Pas de timeout par défaut
            ...defaultOptions, // Permet à l'utilisateur de personnaliser les options par défaut
        };
    }

    /**
     * Envoie une requête HTTP. C'est la méthode principale interne pour gérer les requêtes.
     * @private
     * @param {string} method - La méthode HTTP (GET, POST, PUT, PATCH, DELETE).
     * @param {string} endpoint - Le chemin de l'endpoint relatif à baseUrl ou une URL complète.
     * @param {{}} [options={}] - Options spécifiques à cette requête, qui remplaceront les defaultOptions.
     * @param {{}} [options.headers={}] - En-têtes HTTP spécifiques à cette requête.
     * @param {FormData|{}|string|null} [options.body=null] - Corps de la requête spécifique. Utilisé pour POST, PUT, PATCH.
     * @param {boolean} [options.throwHttpErrors=true] - Si `true`, rejette la promesse pour les codes d'état HTTP >= 400.
     * @param {number} [options.timeout=0] - Le délai d'attente en millisecondes pour cette requête.
     * @param {Function|null} [onProgress=null] - Fonction de callback pour suivre la progression du téléchargement.
     * @returns {Promise<object>} Une promesse qui se résout avec les données de la réponse, le statut, les en-têtes, etc.
     * ou rejette avec une erreur en cas d'échec.
     */
    sendRequest(method, endpoint, options = {}, onProgress = null) {
        // Validation de la méthode HTTP.
        if (
            !method ||
            !["GET", "POST", "PUT", "PATCH", "DELETE"].includes(method.toUpperCase())
        ) {
            throw new Error("Méthode HTTP invalide.");
        }

        // Validation de l'endpoint.
        if (!endpoint || typeof endpoint !== "string") {
            throw new Error("endpoint doit être une chaîne de caractères valide.");
        }

        // Fusionne les options par défaut avec les options spécifiques à la requête.
        const mergedOptions = {
            ...this.defaultOptions,
            ...options,
            headers: { ...this.defaultOptions.headers, ...options.headers }, // Fusion profonde des en-têtes
        };

        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            let finalUrl = endpoint;

            // Construit l'URL finale en combinant baseUrl et l'endpoint si l'endpoint n'est pas une URL absolue.
            if (!/^https?:\/\//.test(endpoint) && !endpoint.startsWith("//")) {
                finalUrl = `${this.baseUrl.replace(/\/$/, "")}/${endpoint.replace(/^\//, "")}`;
            }

            // Ouvre la connexion HTTP.
            xhr.open(method.toUpperCase(), finalUrl);

            // Applique tous les en-têtes HTTP fusionnés.
            Object.entries(mergedOptions.headers).forEach(([key, value]) => {
                xhr.setRequestHeader(key, value);
            });

            // Définit le délai d'attente si spécifié.
            if (mergedOptions.timeout > 0) {
                xhr.timeout = mergedOptions.timeout;
            }

            // Attache le gestionnaire d'événements pour la progression du téléchargement.
            if (typeof onProgress === "function") {
                xhr.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        const progress = (event.loaded / event.total) * 100;
                        onProgress(progress, event);
                    } else {
                        onProgress(null, event); // Taille inconnue si non calculable
                    }
                };
            }

            /**
             * Parse la chaîne d'en-têtes de réponse en un objet.
             * @param {string} headersString - La chaîne d'en-têtes bruts de la réponse.
             * @returns {object} Un objet où les clés sont les noms d'en-tête (en minuscules) et les valeurs sont leurs valeurs.
             */
            const parseHeaders = (headersString) => {
                const headers = {};
                if (headersString) {
                    headersString
                        .trim()
                        .split("\r\n")
                        .forEach((line) => {
                            const [name, ...valueParts] = line.split(": ");
                            const value = valueParts.join(": ");
                            if (name && value) {
                                headers[name.toLowerCase()] = value;
                            }
                        });
                }
                return headers;
            };

            // Gestionnaire d'événements lorsque la requête est terminée (chargement réussi ou échec HTTP).
            xhr.onload = () => {
                if (xhr.readyState === XMLHttpRequest.DONE) {
                    const headers = parseHeaders(xhr.getAllResponseHeaders());
                    if (xhr.status >= 200 && xhr.status < 300) {
                        // Réponse réussie (2xx)
                        try {
                            const data = JSON.parse(xhr.response);
                            const status = xhr.status;
                            const statusText = xhr.statusText;
                            resolve({
                                data,
                                status,
                                statusText,
                                headers,
                            });
                        } catch (error) {
                            // Erreur de parsing JSON pour une réponse 2xx
                            const data = xhr.response;
                            const status = xhr.status;
                            const statusText = xhr.statusText;
                            if (mergedOptions.throwHttpErrors) {
                                console.log({
                                    data,
                                    status,
                                    statusText,
                                    headers,
                                });
                                reject(new Error("Erreur de parsing JSON"));
                            } else {
                                console.error(error);
                                console.log({data});
                                // Retourne la réponse brute si throwHttpErrors est faux
                                resolve({
                                    data,
                                    status,
                                    statusText,
                                    headers,
                                });
                            }
                        }
                    } else if (xhr.status >= 300 && xhr.status < 400) {
                        // Redirection (3xx)
                        const data = JSON.parse(xhr.response);
                        const status = xhr.status;
                        const statusText = xhr.statusText;
                        if (mergedOptions.throwHttpErrors) {
                            console.warn(
                                `Code HTTP ${xhr.status}, Redirection`
                            );
                        }
                        resolve({
                            data,
                            status,
                            statusText,
                            headers,
                        });
                    } else {
                        // Erreur client ou serveur (4xx ou 5xx)
                        console.log(`Code HTTP ${xhr.status}, Erreur`);
                        const cleanEl = document.createElement("div");
                        cleanEl.innerHTML = xhr.responseText;
                        let errorData;
                        try {
                            errorData = JSON.parse(xhr.responseText);
                        } catch (e) {
                            errorData = cleanEl.textContent || "Erreur inconnue";
                        }
                        const errorMessage = errorData || "Erreur inconnue";

                        const data = {errorMessage};
                        const status = xhr.status;

                        const statusText = xhr.statusText;
                        if (mergedOptions.throwHttpErrors) {
                            console.log({
                                data,
                                status,
                                statusText,
                                headers,
                            });
                            reject(new Error(`Erreur HTTP ${xhr.status}: ${xhr.statusText}`));
                        } else {
                            console.error(`Erreur HTTP ${xhr.status}: ${xhr.statusText}`, data);
                            // Retourne la réponse brute si throwHttpErrors est faux
                            resolve({
                                data,
                                status,
                                statusText,
                                headers,
                            });
                        }
                    }
                }
            };

            // Gestionnaire d'événements pour les erreurs réseau (ex: pas de connexion internet).
            xhr.onerror = () => {
                reject(new Error("Erreur réseau"));
            };

            // Gestionnaire d'événements pour le dépassement du délai d'attente.
            xhr.ontimeout = () => {
                reject(new Error(`Requête expirée après ${mergedOptions.timeout} ms`));
            };

            // Envoi du corps de la requête.
            if (mergedOptions.body) {
                if (mergedOptions.body instanceof FormData) {
                    xhr.send(mergedOptions.body);
                } else if (typeof mergedOptions.body === "object") {
                    // Si c'est un objet, le convertir en FormData.
                    const formData = new FormData();
                    Object.entries(mergedOptions.body).forEach(([key, value]) => {
                        formData.append(key, value);
                    });
                    xhr.send(formData);
                } else if (typeof mergedOptions.body === "string") {
                    // Si c'est une chaîne, s'assurer que le Content-Type est JSON.
                    xhr.setRequestHeader("Content-Type", "application/json");
                    xhr.send(mergedOptions.body);
                } else {
                    // Pour d'autres types de corps.
                    xhr.send(mergedOptions.body);
                }
            } else {
                // Pas de corps de requête.
                xhr.send();
            }
        });
    }

    /**
     * Méthode générique pour effectuer une requête HTTP.
     * @param {string} method - La méthode HTTP (GET, POST, PUT, PATCH, DELETE).
     * @param {string} endpoint - Le chemin de l'endpoint.
     * @param {{}} [options={}] - Options spécifiques à cette requête. Ces options seront fusionnées avec les `defaultOptions` du client.
     * @param {{}} [options.headers={}] - En-têtes HTTP à inclure dans la requête.
     * @param {FormData|{}|string|null} [options.body=null] - Le corps de la requête. Applicable pour POST, PUT, PATCH.
     * @param {boolean} [options.throwHttpErrors=true] - Override le comportement par défaut de rejet des erreurs HTTP (codes >= 400).
     * @param {number} [options.timeout=0] - Override le délai d'attente pour cette requête en millisecondes.
     * @param {Function|null} [onProgress=null] - Fonction de callback pour suivre la progression du téléchargement.
     * @returns {Promise<object>} Une promesse qui se résout avec les données de la réponse.
     */
    request(method, endpoint, options = {}, onProgress = null) {
        return this.sendRequest(method, endpoint, options, onProgress);
    }

    /**
     * Effectue une requête GET.
     * @param {string} endpoint - Le chemin de l'endpoint.
     * @param {{}} [options={}] - Options spécifiques à cette requête GET.
     * @param {{}} [options.headers={}] - En-têtes HTTP à inclure dans la requête.
     * @param {boolean} [options.throwHttpErrors=true] - Override le comportement par défaut de rejet des erreurs HTTP (codes >= 400).
     * @param {number} [options.timeout=0] - Override le délai d'attente pour cette requête en millisecondes.
     * @param {Function|null} [onProgress=null] - Fonction de callback pour la progression du téléchargement (moins courant pour GET).
     * @returns {Promise<object>} Une promesse qui se résout avec les données de la réponse.
     */
    get(endpoint, options = {}, onProgress) {
        return this.request("GET", endpoint, options, onProgress);
    }

    /**
     * Effectue une requête POST.
     * @param {string} endpoint - Le chemin de l'endpoint.
     * @param {{}} [options={}] - Options spécifiques à cette requête POST.
     * @param {{}} [options.headers={}] - En-têtes HTTP à inclure dans la requête.
     * @param {FormData|{}|string|null} [options.body=null] - Le corps de la requête (FormData, objet JavaScript, chaîne JSON ou null).
     * @param {boolean} [options.throwHttpErrors=true] - Override le comportement par défaut de rejet des erreurs HTTP (codes >= 400).
     * @param {number} [options.timeout=0] - Override le délai d'attente pour cette requête en millisecondes.
     * @param {Function|null} [onProgress=null] - Fonction de callback pour la progression du téléchargement (utile pour les envois de fichiers).
     * @returns {Promise<object>} Une promesse qui se résout avec les données de la réponse.
     */
    post(endpoint, options = {}, onProgress) {
        return this.request("POST", endpoint, options, onProgress);
    }

    /**
     * Effectue une requête PUT.
     * @param {string} endpoint - Le chemin de l'endpoint.
     * @param {{}} [options={}] - Options spécifiques à cette requête PUT.
     * @param {{}} [options.headers={}] - En-têtes HTTP à inclure dans la requête.
     * @param {FormData|{}|string|null} [options.body=null] - Le corps de la requête (FormData, objet JavaScript, chaîne JSON ou null).
     * @param {boolean} [options.throwHttpErrors=true] - Override le comportement par défaut de rejet des erreurs HTTP (codes >= 400).
     * @param {number} [options.timeout=0] - Override le délai d'attente pour cette requête en millisecondes.
     * @param {Function|null} [onProgress=null] - Fonction de callback pour la progression du téléchargement.
     * @returns {Promise<object>} Une promesse qui se résout avec les données de la réponse.
     */
    put(endpoint, options = {}, onProgress) {
        return this.request("PUT", endpoint, options, onProgress);
    }

    /**
     * Effectue une requête PATCH.
     * @param {string} endpoint - Le chemin de l'endpoint.
     * @param {{}} [options={}] - Options spécifiques à cette requête PATCH.
     * @param {{}} [options.headers={}] - En-têtes HTTP à inclure dans la requête.
     * @param {FormData|{}|string|null} [options.body=null] - Le corps de la requête (FormData, objet JavaScript, chaîne JSON ou null).
     * @param {boolean} [options.throwHttpErrors=true] - Override le comportement par défaut de rejet des erreurs HTTP (codes >= 400).
     * @param {number} [options.timeout=0] - Override le délai d'attente pour cette requête en millisecondes.
     * @param {Function|null} [onProgress=null] - Fonction de callback pour la progression du téléchargement.
     * @returns {Promise<object>} Une promesse qui se résout avec les données de la réponse.
     */
    patch(endpoint, options = {}, onProgress) {
        return this.request("PATCH", endpoint, options, onProgress);
    }

    /**
     * Effectue une requête DELETE.
     * @param {string} endpoint - Le chemin de l'endpoint.
     * @param {{}} [options={}] - Options spécifiques à cette requête DELETE.
     * @param {{}} [options.headers={}] - En-têtes HTTP à inclure dans la requête.
     * @param {boolean} [options.throwHttpErrors=true] - Override le comportement par défaut de rejet des erreurs HTTP (codes >= 400).
     * @param {number} [options.timeout=0] - Override le délai d'attente pour cette requête en millisecondes.
     * @param {Function|null} [onProgress=null] - Fonction de callback pour la progression du téléchargement (moins courant pour DELETE).
     * @returns {Promise<object>} Une promesse qui se résout avec les données de la réponse.
     */
    delete(endpoint, options = {}, onProgress) {
        return this.request("DELETE", endpoint, options, onProgress);
    }
}

// Exemple d'utilisation de la classe OchoClient.
// Cet exemple initialise une instance de OchoClient avec une URL de base '/'
// et des en-têtes d'autorisation et X-Requested-With par défaut.
// throwHttpErrors est défini à false pour gérer manuellement les erreurs HTTP.
export const apiClient = new OchoClient("/", {
    headers: {
        Authorization: "Bearer OchoToken", // Remplacez par votre token d'authentification
        "X-Requested-With": "XMLHttpRequest", // Indique que la requête est faite via AJAX
    },
    throwHttpErrors: false,
});

function applyStoredTheme() {
    const stored = localStorage.getItem(THEME_STORAGE_KEY);
    const prefersDark =
        typeof window !== "undefined" &&
        window.matchMedia &&
        window.matchMedia("(prefers-color-scheme: dark)").matches;
    const shouldUseDark = stored ? stored === "dark" : prefersDark;
    document.documentElement.classList.toggle("dark", shouldUseDark);
}
function refreshIcons() {
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
function syncThemeArtifacts() {
    const isDark = document.documentElement.classList.contains("dark");
    const icon = document.getElementById("darkModeIcon");
    if (icon) {
        icon.setAttribute("data-lucide", isDark ? "sun" : "moon");
    }
    refreshIcons();
}
document.addEventListener("DOMContentLoaded",async () => {
    
    applyStoredTheme(); 
    syncThemeArtifacts();
      // Dark mode
    const darkBtn = document.getElementById('btnDarkModeToggle');
    
    if (darkBtn) {
        darkBtn.addEventListener('click', toggleDarkMode);
    }
        updateNavBar()
    const mobileMenuBtn = document.getElementById('btnMobileMenu');
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', () => {
            const nav = document.getElementById('nav-menu');
            const icon = document.getElementById('menuIcon');
            nav.classList.toggle('hidden');
            if (nav.classList.contains('hidden')) {
                icon.setAttribute('data-lucide', 'menu');
            } else {
                icon.setAttribute('data-lucide', 'x');
            }
            refreshIcons();
        });
    }
    // Mettre à jour la barre de navigation
    const authResult = await isAuth();
    const userRole = authResult?.user?.role || 'guest';
    updateNavBar(userRole, window.location.pathname);
    setUserBadge(authResult?.user || null);
    attachGlobalListeners()
});


function setUserBadge(user) {
    const avatar = document.getElementById("userAvatar");
    const chip = document.getElementById("userNameChip");
    if (!avatar) return;

    
    
    if (user?.name) {
        const initials = user.name
            .split(" ")
            .filter(Boolean)
            .slice(0, 2)
            .map((chunk) => chunk[0])
            .join("")
            .toUpperCase();
        avatar.textContent = initials || "✓";
        avatar.classList.add("bg-blue-600", "text-white");
        avatar.title = user.name;
        
        if (chip) chip.textContent = user.name;
    } else {
        avatar.innerHTML = `<i data-lucide="user-round" class="w-4 h-4"></i>`;
        avatar.classList.remove("bg-blue-600", "text-white");
        avatar.title = "Utilisateur invité";
        if (chip) chip.textContent = "Invité";
        refreshIcons();
    }
}

function toggleDarkMode() {
    const html = document.documentElement;
    const shouldBeDark = !html.classList.contains('dark');
    html.classList.toggle('dark', shouldBeDark);
    localStorage.setItem(THEME_STORAGE_KEY, shouldBeDark ? 'dark' : 'light');
    syncThemeArtifacts();
}

window.toggleMobileMenu = function() {
    const nav = document.getElementById('nav-menu');
    const icon = document.getElementById('menuIcon');
    nav.classList.toggle('hidden');
    if (nav.classList.contains('hidden')) {
        icon.setAttribute('data-lucide', 'menu');
    } else {
        icon.setAttribute('data-lucide', 'x');
    }
    refreshIcons();
};

/*
 * Ce bloc de commentaires décrit les options possibles pour le constructeur de apiClient
 * ainsi que le format attendu pour la fonction de callback `onProgress`.
 *
 * `possible options in the apiClient constructor:`
 * {
 * headers: {
 * Authorization: "AuthToken",
 * "X-Requested-With": "XMLHttpRequest",
 * },
 * body: null, // Corps de la requête, peut être un objet, un formdata ou une chaîne JSON
 * throwHttpErrors: true, // Lève une erreur en cas de code HTTP >= 400
 * timeout: 0, // Timeout en millisecondes, 0 pour pas de timeout
 * }
 *
 * `progress callback must be in the form of:`
 * function onProgress(progress, event) {
 * if (progress !== null) {
 * console.log(`Progression: ${progress}%`);
 * } else {
 * console.log("Taille inconnue");
 * }
 * console.log("Événement de progression:", event);
 * }
 * Et il sera appelé comme troisième argument lors d'un appel API comme ceci :
 * apiClient.get('/endpoint', {}, onProgress);
 * Ceci affichera la progression de la requête s'il s'agit d'un téléchargement ou d'un envoi de fichier.
*/
