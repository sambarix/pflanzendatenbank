/**
 * PDB Liste - JavaScript for column picker, profile switching, and state management
 *
 * Nutzt window.PDB_BASE_URL (von der Twig-Vorlage gesetzt) statt hartem /pdb/-Pfad,
 * damit das Skript unabhaengig vom Installationsort funktioniert.
 */

// --- Profile column definitions (must match PHP) ---
var pdbProfiles = {
    botanik:    ['botanical_name', 'genus', 'species', 'cultivar', 'name_de', 'category_path', 'growth_cycle'],
    gaertnerei: ['botanical_name', 'name_de', 'category_path', 'flower_color', 'hardiness_zone', 'light', 'growth_cycle'],
    planung:    ['botanical_name', 'name_de', 'category_path', 'height_cm', 'width_cm', 'flower_color', 'light', 'hardiness_zone']
};

// Current state
var pdbState = {
    profile: 'botanik',
    customColumns: null,
    sort: 'botanical_name',
    dir: 'ASC'
};

// Kategorie aus der Adresszeile; wird gesetzt, sobald das Dropdown geladen ist
var pdbPendingCategory = '';
// Letzte Listen-Adresse (mit Auswahl) fuer "Zurueck zur Liste" im Portrait
var PDB_LISTE_KEY = 'pdb_liste_url';

function pdbApiBase() {
    return (window.PDB_BASE_URL || '') + '/pdb-api/';
}

/**
 * Auswahl der Liste in der Adresszeile merken (history.replaceState, kein neuer Verlaufseintrag)
 * und die Adresse fuer das Portrait ablegen. So bleiben Suche, Kategorie, Seite, Sortierung und
 * Spalten erhalten, wenn man ein Portrait oeffnet und zurueckkehrt (Link oder Browser-Zurueck).
 */
function pdbSaveState(apiUrl) {
    var q = new URLSearchParams(apiUrl.indexOf('?') !== -1 ? apiUrl.split('?')[1] : '');
    q.delete('format');
    Array.from(q.keys()).forEach(function(k) { if (q.get(k) === '') q.delete(k); });
    var rel = window.location.pathname + (q.toString() ? '?' + q.toString() : '');
    try { history.replaceState(history.state, '', rel); } catch (e) {}
    try { sessionStorage.setItem(PDB_LISTE_KEY, rel); } catch (e) {}
}

/**
 * Erste Abfrage der Liste: Auswahl aus der Adresszeile (?search=..&category=..&page=..) uebernehmen,
 * sonst Standard (Profil Botanik, 20 Zeilen, Seite 1).
 */
function pdbInitialLoad() {
    var p = new URLSearchParams(window.location.search);
    var q = new URLSearchParams();
    q.set('format', 'full');

    var perPage = p.get('per_page');
    if (['20', '50', '100', '500'].indexOf(perPage) === -1) perPage = '20';
    document.getElementById('pdb-per-page').value = perPage;
    q.set('per_page', perPage);

    var page = parseInt(p.get('page'), 10);
    q.set('page', page > 0 ? String(page) : '1');

    var search = p.get('search') || '';
    document.getElementById('pdb-search').value = search;
    q.set('search', search);

    if (p.get('sort')) pdbState.sort = p.get('sort');
    if (p.get('dir') === 'ASC' || p.get('dir') === 'DESC') pdbState.dir = p.get('dir');
    q.set('sort', pdbState.sort);
    q.set('dir', pdbState.dir);

    var category = p.get('category') || '';
    pdbPendingCategory = category;
    q.set('category', category);

    var columns = p.get('columns') || '';
    var profile = p.get('profile') || 'botanik';
    if (columns !== '') {
        pdbState.customColumns = columns.split(',');
        pdbState.profile = 'custom';
        document.getElementById('pdb-profile').value = 'custom';
        document.getElementById('pdb-profile-hidden').value = 'custom';
        document.getElementById('pdb-columns-hidden').value = columns;
        q.set('columns', columns);
    } else {
        if (!pdbProfiles[profile]) profile = 'botanik';
        pdbState.profile = profile;
        document.getElementById('pdb-profile').value = profile;
        document.getElementById('pdb-profile-hidden').value = profile;
        pdbSyncCheckboxesToProfile(profile);
        q.set('profile', profile);
    }

    pdbSyncHiddenFields();
    htmx.ajax('GET', pdbApiBase() + 'liste?' + q.toString(), {target: '#pdb-table', swap: 'innerHTML'});
}

/**
 * Load categories from database into the category dropdown
 */
function pdbLoadCategories() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', pdbApiBase() + 'categories');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var categories = JSON.parse(xhr.responseText);
                var sel = document.getElementById('pdb-category');
                if (!sel) return;
                // Keep "Alle" option
                sel.innerHTML = '<option value="">Alle</option>';
                categories.forEach(function(cat) {
                    var opt = document.createElement('option');
                    opt.value = cat.path;
                    // Show last segment as label with indent
                    var parts = cat.path.split('/');
                    var indent = '';
                    for (var i = 1; i < parts.length; i++) indent += '\u00A0\u00A0';
                    opt.textContent = indent + parts[parts.length - 1] + ' (' + cat.count + ')';
                    sel.appendChild(opt);
                });
                // Kategorie aus der Adresszeile wieder einstellen
                if (pdbPendingCategory) {
                    sel.value = pdbPendingCategory;
                    document.getElementById('pdb-category-hidden').value = sel.value;
                }
            } catch (e) {
                // Silently fail - categories just won't load
            }
        }
    };
    xhr.send();
}

/**
 * Switch column profile
 */
function pdbChangeProfile(profile) {
    if (profile === 'custom') {
        pdbToggleColumnPicker();
        return;
    }

    pdbState.profile = profile;
    pdbState.customColumns = null;

    // Update hidden fields
    document.getElementById('pdb-profile-hidden').value = profile;
    document.getElementById('pdb-columns-hidden').value = '';

    // Update checkboxes to match profile
    pdbSyncCheckboxesToProfile(profile);

    // Reload table
    pdbReload();
}

/**
 * Sync column picker checkboxes to a profile
 */
function pdbSyncCheckboxesToProfile(profile) {
    var cols = pdbProfiles[profile] || pdbProfiles.botanik;
    var checkboxes = document.querySelectorAll('#pdb-column-picker input[data-col]');
    checkboxes.forEach(function(cb) {
        cb.checked = cols.indexOf(cb.value) !== -1;
    });
}

/**
 * Toggle column picker visibility
 */
function pdbToggleColumnPicker() {
    var picker = document.getElementById('pdb-column-picker');
    if (!picker) return;

    var isVisible = picker.style.display !== 'none';
    picker.style.display = isVisible ? 'none' : 'block';

    // When opening, sync checkboxes to current state
    if (!isVisible) {
        if (pdbState.customColumns) {
            var checkboxes = document.querySelectorAll('#pdb-column-picker input[data-col]');
            checkboxes.forEach(function(cb) {
                cb.checked = pdbState.customColumns.indexOf(cb.value) !== -1;
            });
        } else {
            pdbSyncCheckboxesToProfile(pdbState.profile);
        }
    }
}

/**
 * Apply custom column selection
 */
function pdbApplyColumns() {
    var selected = ['botanical_name']; // Always first
    var checkboxes = document.querySelectorAll('#pdb-column-picker input[data-col]:checked');
    checkboxes.forEach(function(cb) {
        if (selected.indexOf(cb.value) === -1) {
            selected.push(cb.value);
        }
    });

    pdbState.customColumns = selected;
    pdbState.profile = 'custom';

    // Update UI
    document.getElementById('pdb-profile').value = 'custom';
    document.getElementById('pdb-profile-hidden').value = 'custom';
    document.getElementById('pdb-columns-hidden').value = selected.join(',');

    // Hide picker
    document.getElementById('pdb-column-picker').style.display = 'none';

    // Reload
    pdbReload();
}

/**
 * Sync visible controls to hidden form fields (for HTMX hx-include)
 */
function pdbSyncHiddenFields() {
    document.getElementById('pdb-per-page-hidden').value = document.getElementById('pdb-per-page').value;
    document.getElementById('pdb-category-hidden').value = document.getElementById('pdb-category').value;
    document.getElementById('pdb-sort').value = pdbState.sort;
    document.getElementById('pdb-dir').value = pdbState.dir;
}

/**
 * Reload the table with current settings
 */
function pdbReload() {
    // Sync hidden fields first
    pdbSyncHiddenFields();

    var search = document.getElementById('pdb-search').value;
    var perPage = document.getElementById('pdb-per-page').value;
    var category = document.getElementById('pdb-category').value;

    var url = pdbApiBase() + 'liste?format=full&page=1'
        + '&search=' + encodeURIComponent(search)
        + '&per_page=' + perPage
        + '&sort=' + encodeURIComponent(pdbState.sort)
        + '&dir=' + pdbState.dir
        + '&category=' + encodeURIComponent(category);

    if (pdbState.customColumns) {
        url += '&columns=' + encodeURIComponent(pdbState.customColumns.join(','));
    } else {
        url += '&profile=' + encodeURIComponent(pdbState.profile);
    }

    htmx.ajax('GET', url, {target: '#pdb-table', swap: 'innerHTML'});
}

/**
 * Initialize on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    // Reihenfolge: erst Auswahl aus der Adresszeile lesen (setzt pdbPendingCategory), dann Kategorien laden
    pdbInitialLoad();
    pdbLoadCategories();

    // Sync hidden fields before every HTMX request (so search includes per_page, category)
    document.body.addEventListener('htmx:configRequest', function(evt) {
        pdbSyncHiddenFields();
    });

    // Listen for HTMX events to track sort state
    document.body.addEventListener('htmx:afterRequest', function(evt) {
        var url = evt.detail.pathInfo ? evt.detail.pathInfo.requestPath : '';
        if (!url) {
            // Try xhr responseURL
            url = evt.detail.xhr ? evt.detail.xhr.responseURL : '';
        }
        if (url && url.indexOf('sort=') !== -1) {
            var qstr = url.split('?')[1] || '';
            var params = new URLSearchParams(qstr);
            if (params.get('sort')) pdbState.sort = params.get('sort');
            if (params.get('dir')) pdbState.dir = params.get('dir');
        }
        // Auswahl in der Adresszeile merken (nur erfolgreiche Listenabfragen)
        var xhr = evt.detail.xhr;
        var ru = xhr ? xhr.responseURL : '';
        if (evt.detail.successful && ru && ru.indexOf('/pdb-api/liste') !== -1 && ru.indexOf('format=full') !== -1) {
            pdbSaveState(ru);
        }
    });
});
