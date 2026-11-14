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

function pdbApiBase() {
    return (window.PDB_BASE_URL || '') + '/pdb-api/';
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
    });
});
