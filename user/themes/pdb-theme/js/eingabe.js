// Grav 2: Die Basis-URL kommt aus dem Template (window.PDB_BASE), damit die Seite auch in einem Unterordner laeuft (z.B. /pdb22).
var PDB_BASE = (window.PDB_BASE || '').replace(/\/+$/, '');
var PDB_API  = PDB_BASE + '/pdb-api/eingabe';
// Ziel: 'public' = Stammdaten (Editor), 'own' = eigene Gaertnerei-DB. Die Rechte prueft der Server, nicht dieser Wert.
var PDB_TARGET = (window.PDB_TARGET === 'own') ? 'own' : 'public';
var PDB_TQ = (PDB_TARGET === 'own') ? '&target=own' : '';

/**
 * PDB Eingabe - Accordion, Live Preview, Autocomplete, Save
 */

// ===========================
// ACCORDION
// ===========================
function pdbToggleSection(headerEl) {
    var section = headerEl.parentElement;
    var isOpen = section.classList.contains('pdb-section-open');
    // Toggle this section (don't close others - allow multiple open)
    if (isOpen) {
        section.classList.remove('pdb-section-open');
        section.querySelector('.pdb-section-body').style.display = 'none';
    } else {
        section.classList.add('pdb-section-open');
        section.querySelector('.pdb-section-body').style.display = 'block';
    }
}

// ===========================
// UMLAUT CONVERSION
// ===========================
var umlautMap = {'ä':'ae','ö':'oe','ü':'ue','Ä':'Ae','Ö':'Oe','Ü':'Ue','ß':'ss'};

function convertUmlauts(text) {
    return text.replace(/[äöüÄÖÜß]/g, function(ch) { return umlautMap[ch] || ch; });
}

var accentMap = {
    'à':'a','á':'a','â':'a','ã':'a','å':'a',
    'è':'e','é':'e','ê':'e','ë':'e',
    'ì':'i','í':'i','î':'i','ï':'i',
    'ò':'o','ó':'o','ô':'o','õ':'o',
    'ù':'u','ú':'u','û':'u',
    'ý':'y','ÿ':'y','ñ':'n','ç':'c'
};

function transliterateAll(text) {
    text = convertUmlauts(text);
    return text.replace(/[àáâãåèéêëìíîïòóôõùúûýÿñç]/g, function(ch) { return accentMap[ch] || ch; });
}

// ===========================
// LIVE PREVIEW
// ===========================
var dupCheckTimer = null;

function pdbUpdatePreview() {
    var genus = document.getElementById('f-genus').value.trim();
    var species = document.getElementById('f-species').value.trim();
    var subspecies = document.getElementById('f-subspecies').value.trim();
    var cultivarOrig = document.getElementById('f-cultivar').value.trim();
    var ecotype = document.getElementById('f-ecotype').value.trim();

    if (!genus) {
        document.getElementById('pdb-preview-botanical').textContent = '—';
        document.getElementById('pdb-preview-webname').textContent = '—';
        document.getElementById('pdb-preview-matchcode').textContent = '—';
        return;
    }

    // Capitalize genus
    genus = genus.charAt(0).toUpperCase() + genus.slice(1).toLowerCase();
    species = species.toLowerCase();
    var cultivar = convertUmlauts(cultivarOrig);

    // Build botanical name
    var botName = genus;
    if (species) botName += ' ' + species;
    if (subspecies) botName += ' subsp. ' + subspecies.toLowerCase();
    if (cultivar) botName += " '" + cultivar + "'";
    if (ecotype) botName += ' [' + ecotype + ']';

    // Build webname
    var parts = [genus.toLowerCase()];
    if (species) parts.push(species);
    if (subspecies) parts.push(subspecies.toLowerCase());
    if (cultivar) parts.push(cultivar.toLowerCase());
    if (ecotype) parts.push(ecotype.toLowerCase());
    var webname = transliterateAll(parts.join('-'));
    webname = webname.replace(/[''\"®™©]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

    // Build matchcode
    var matchcode = (genus.substring(0,3) + species.substring(0,3) + cultivar.substring(0,3)).toUpperCase();

    document.getElementById('pdb-preview-botanical').textContent = botName;
    document.getElementById('pdb-preview-webname').textContent = webname;
    document.getElementById('pdb-preview-matchcode').textContent = matchcode;

    // Debounced duplicate check
    clearTimeout(dupCheckTimer);
    dupCheckTimer = setTimeout(function() {
        pdbCheckDuplicate(botName);
    }, 400);
}

// ===========================
// DUPLICATE CHECK
// ===========================
function pdbCheckDuplicate(botanicalName) {
    var el = document.getElementById('pdb-duplicate-status');
    if (!botanicalName || botanicalName === '—') {
        el.className = 'pdb-duplicate-ok';
        el.innerHTML = '';
        return;
    }

    el.className = 'pdb-duplicate-checking';
    el.innerHTML = '&#8987; Prüfe...';

    var xhr = new XMLHttpRequest();
    var dupUrl = PDB_API + '?action=check_duplicate' + PDB_TQ + '&botanical_name=' + encodeURIComponent(botanicalName);
    if (pdbEditId) { dupUrl += '&exclude_id=' + pdbEditId; }
    xhr.open('GET', dupUrl);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.exists) {
                    el.className = 'pdb-duplicate-error';
                    if (data.where === 'public') {
                        el.innerHTML = '&#10007; Gibt es schon in der öffentlichen PDB (ID: ' + data.id + '). Bitte im Sortiment über «Pflanzen aufnehmen» hinzufügen.';
                    } else {
                        el.innerHTML = '&#10007; Duplikat! Existiert bereits (ID: ' + data.id + ')';
                    }
                } else {
                    el.className = 'pdb-duplicate-ok';
                    el.innerHTML = '&#10003; Kein Duplikat';
                }
            } catch(e) {
                el.className = 'pdb-duplicate-ok';
                el.innerHTML = '';
            }
        }
    };
    xhr.send();
}

// ===========================
// AUTOCOMPLETE: Genus
// ===========================
var acGenusTimer = null;

function pdbAutocompleteGenus(input) {
    var term = input.value.trim();
    var dropdown = document.getElementById('ac-genus');

    clearTimeout(acGenusTimer);
    if (term.length < 1) {
        dropdown.style.display = 'none';
        return;
    }

    acGenusTimer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', PDB_API + '?action=autocomplete_genus' + PDB_TQ + '&term=' + encodeURIComponent(term));
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var items = JSON.parse(xhr.responseText);
                    if (items.length === 0) {
                        dropdown.style.display = 'none';
                        return;
                    }
                    dropdown.innerHTML = '';
                    items.forEach(function(item) {
                        var div = document.createElement('div');
                        div.textContent = item;
                        div.onclick = function() {
                            input.value = item;
                            dropdown.style.display = 'none';
                            pdbUpdatePreview();
                        };
                        dropdown.appendChild(div);
                    });
                    dropdown.style.display = 'block';
                } catch(e) {
                    dropdown.style.display = 'none';
                }
            }
        };
        xhr.send();
    }, 200);
}

// Close autocomplete on click outside
document.addEventListener('click', function(e) {
    var ac = document.getElementById('ac-genus');
    if (ac && !ac.contains(e.target) && e.target.id !== 'f-genus') {
        ac.style.display = 'none';
    }
});

// ===========================
// EDIT MODE: Load & Fill
// ===========================
var pdbEditId = 0;

function pdbLoadPlant(id) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', PDB_API + '?action=load_plant' + PDB_TQ + '&id=' + id);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.error) { alert('Ladefehler: ' + data.error); return; }
                pdbFillForm(data);
            } catch(e) { alert('Ladefehler'); }
        }
    };
    xhr.send();
}

function pdbFillForm(data) {
    pdbResetForm();
    pdbEditId = data.id || 0;

    // Eigene DB: Freigabe-Schalter der Pflanze
    var shareBox = document.getElementById('f-share');
    if (shareBox && data.local) { shareBox.checked = !!parseInt(data.local.share, 10); }
    var shareStatus = document.getElementById('pdb-share-status');
    if (shareStatus) {
        var vs = data.vorschlag;
        var vsLabels = {
            offen: 'Ihr Vorschlag liegt bei den Editoren zur Prüfung.',
            uebernommen: 'Ihr Vorschlag wurde in die öffentliche PDB übernommen.',
            abgelehnt: 'Ihr letzter Vorschlag wurde nicht übernommen.',
            zurueckgezogen: 'Der Vorschlag ist zurückgezogen.'
        };
        shareStatus.textContent = (vs && vsLabels[vs.status])
            ? vsLabels[vs.status] + ((vs.status === 'abgelehnt' && vs.note) ? ' Hinweis: ' + vs.note : '') : '';
    }

    // Edit-Modus UI
    document.querySelector('.pdb-eingabe-title').textContent = 'Pflanze bearbeiten';
    var saveBtn = document.getElementById('pdb-save-btn');
    saveBtn.textContent = '\u00c4nderungen speichern';
    saveBtn.classList.add('pdb-btn-edit');

    // Direkte Felder
    document.getElementById('f-genus').value      = data.genus      || '';
    document.getElementById('f-species').value    = data.species    || '';
    document.getElementById('f-subspecies').value = data.subspecies || '';
    document.getElementById('f-cultivar').value   = data.cultivar   || '';
    document.getElementById('f-ecotype').value    = data.ecotype    || '';
    document.getElementById('f-notes').value      = data.notes      || '';
    document.getElementById('f-description_short').value  = data.description_short  || '';
    document.getElementById('f-description_long').value   = data.description_long   || '';
    document.getElementById('f-care_instructions').value  = data.care_instructions  || '';
    document.getElementById('f-meta_text').value          = data.meta_text          || '';
    pdbUpdatePreview();

    // Sections via data-key (alle ausser categories/synonyms)
    var sections = {
        names: data.names || {}, site: data.site || {}, flower: data.flower || {},
        growth: data.growth || {}, origin: data.origin || {}, fruit: data.fruit || {},
        leaf: data.leaf || {}, root: data.root || {}, usage: data.usage || {},
        nursery: data.nursery || {}
    };

    document.querySelectorAll('[data-key]').forEach(function(el) {
        var parts = el.getAttribute('data-key').split('.');
        if (parts.length !== 2) return;
        var sec = parts[0], field = parts[1];
        if (!sections[sec]) return;
        var val = sections[sec][field];
        if (val === null || val === undefined) return;

        if (el.classList.contains('pdb-multiselect')) {
            var vals = String(val).split(',').map(function(v) { return v.trim(); }).filter(Boolean);
            // Restore selection order from saved string order
            el._selectionOrder = vals.slice();
            el.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                cb.checked = vals.indexOf(cb.value) !== -1;
            });
            pdbUpdateMultiselectLabel(el);
        } else if (el.type === 'checkbox') {
            el.checked = (val == 1 || val === true);
        } else {
            el.value = (val !== null && val !== undefined) ? val : '';
        }
    });

    // Kategorien
    var catList = document.getElementById('pdb-categories-list');
    catList.innerHTML = '';
    categoryCounter = 0;
    var cats = (data.categories && data.categories.length) ? data.categories : [{}];
    cats.forEach(function(cat, i) {
        var row = document.createElement('div');
        row.className = 'pdb-multi-row';
        row.setAttribute('data-index', i);
        row.innerHTML = '<select data-key="categories.' + i + '.category_path" class="pdb-category-select"><option value="">— Kategorie wählen —</option></select>' +
            '<button type="button" class="pdb-btn-remove" onclick="pdbRemoveRow(this)" title="Entfernen">&#10005;</button>';
        catList.appendChild(row);
        var sel = row.querySelector('select');
        pdbPopulateCategorySelect(sel);
        if (cat.category_path) sel.value = cat.category_path;
        categoryCounter = i + 1;
    });

    // Synonyme
    var synList = document.getElementById('pdb-synonyms-list');
    synList.innerHTML = '';
    synonymCounter = 0;
    var syns = (data.synonyms && data.synonyms.length) ? data.synonyms : [{}];
    syns.forEach(function(syn, i) {
        var row = document.createElement('div');
        row.className = 'pdb-multi-row pdb-synonym-row';
        row.setAttribute('data-index', i);
        var langOpts = ['','de','en','fr','la'].map(function(v) {
            return '<option value="' + v + '"' + (syn.language === v && v ? ' selected' : '') + '>' + (v ? v.toUpperCase() : 'Sprache') + '</option>';
        }).join('');
        var typeOpts = [['','Typ'],['common','Volksname'],['historical','Historisch'],['botanical','Botanisch']].map(function(p) {
            return '<option value="' + p[0] + '"' + (syn.synonym_type === p[0] && p[0] ? ' selected' : '') + '>' + p[1] + '</option>';
        }).join('');
        row.innerHTML = '<input type="text" data-key="synonyms.' + i + '.synonym_name" placeholder="Synonymname" class="pdb-syn-name" value="' + (syn.synonym_name || '').replace(/"/g, '&quot;') + '">' +
            '<select data-key="synonyms.' + i + '.language" class="pdb-syn-lang">' + langOpts + '</select>' +
            '<select data-key="synonyms.' + i + '.synonym_type" class="pdb-syn-type">' + typeOpts + '</select>' +
            '<button type="button" class="pdb-btn-remove" onclick="pdbRemoveRow(this)" title="Entfernen">&#10005;</button>';
        synList.appendChild(row);
        synonymCounter = i + 1;
    });

    pdbUpdateDots();
    window.scrollTo(0, 0);
}

// ===========================
// MULTI-SELECT DROPDOWN
// ===========================
function pdbToggleMultiselect(triggerEl) {
    var wrapper = triggerEl.parentElement;
    var dropdown = wrapper.querySelector('.pdb-multiselect-dropdown');
    var isOpen = dropdown.style.display !== 'none';

    // Close all open multiselects first
    document.querySelectorAll('.pdb-multiselect-dropdown').forEach(function(d) {
        d.style.display = 'none';
    });
    document.querySelectorAll('.pdb-multiselect-trigger').forEach(function(t) {
        t.classList.remove('pdb-ms-open');
    });

    if (!isOpen) {
        dropdown.style.display = 'block';
        triggerEl.classList.add('pdb-ms-open');

        // Initialise selection-order array if not yet present
        if (!wrapper._selectionOrder) { wrapper._selectionOrder = []; }

        // Listen for checkbox changes: track click order
        dropdown.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
            cb.onchange = function() {
                if (cb.checked) {
                    // Add to end if not already in list
                    if (wrapper._selectionOrder.indexOf(cb.value) === -1) {
                        wrapper._selectionOrder.push(cb.value);
                    }
                } else {
                    // Remove from list
                    wrapper._selectionOrder = wrapper._selectionOrder.filter(function(v) {
                        return v !== cb.value;
                    });
                }
                pdbUpdateMultiselectLabel(wrapper);
            };
        });
    }
}

function pdbUpdateMultiselectLabel(wrapper) {
    // Use click-order array if available, otherwise fall back to DOM order
    var order = wrapper._selectionOrder;
    var values;
    if (order && order.length > 0) {
        values = order;
    } else {
        values = [];
        wrapper.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) {
            values.push(cb.value);
        });
    }
    var label = wrapper.querySelector('.pdb-multiselect-label');
    if (values.length === 0) {
        label.textContent = '\u2014';
        label.classList.add('pdb-ms-placeholder');
    } else {
        label.textContent = values.join('\u00A0\u00A0');
        label.classList.remove('pdb-ms-placeholder');
    }
}

// Close multiselect on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.pdb-multiselect')) {
        document.querySelectorAll('.pdb-multiselect-dropdown').forEach(function(d) {
            d.style.display = 'none';
        });
        document.querySelectorAll('.pdb-multiselect-trigger').forEach(function(t) {
            t.classList.remove('pdb-ms-open');
        });
    }
});

// ===========================
// MULTI-ROW: Categories
// ===========================
var categoryCounter = 1;

function pdbAddCategory() {
    var list = document.getElementById('pdb-categories-list');
    var idx = categoryCounter++;
    var row = document.createElement('div');
    row.className = 'pdb-multi-row';
    row.setAttribute('data-index', idx);
    row.innerHTML = '<select data-key="categories.' + idx + '.category_path" class="pdb-category-select"><option value="">— Kategorie wählen —</option></select>' +
        '<button type="button" class="pdb-btn-remove" onclick="pdbRemoveRow(this)" title="Entfernen">&#10005;</button>';
    list.appendChild(row);
    // Populate options
    pdbPopulateCategorySelect(row.querySelector('select'));
}

// ===========================
// MULTI-ROW: Synonyms
// ===========================
var synonymCounter = 1;

function pdbAddSynonym() {
    var list = document.getElementById('pdb-synonyms-list');
    var idx = synonymCounter++;
    var row = document.createElement('div');
    row.className = 'pdb-multi-row pdb-synonym-row';
    row.setAttribute('data-index', idx);
    row.innerHTML = '<input type="text" data-key="synonyms.' + idx + '.synonym_name" placeholder="Synonymname" class="pdb-syn-name">' +
        '<select data-key="synonyms.' + idx + '.language" class="pdb-syn-lang"><option value="">Sprache</option><option value="de">DE</option><option value="en">EN</option><option value="fr">FR</option><option value="la">LA</option></select>' +
        '<select data-key="synonyms.' + idx + '.synonym_type" class="pdb-syn-type"><option value="">Typ</option><option value="common">Volksname</option><option value="historical">Historisch</option><option value="botanical">Botanisch</option></select>' +
        '<button type="button" class="pdb-btn-remove" onclick="pdbRemoveRow(this)" title="Entfernen">&#10005;</button>';
    list.appendChild(row);
}

function pdbRemoveRow(btn) {
    var row = btn.closest('.pdb-multi-row');
    // Don't remove the last row
    var parent = row.parentElement;
    if (parent.querySelectorAll('.pdb-multi-row').length > 1) {
        row.remove();
    } else {
        // Clear values instead
        row.querySelectorAll('select, input').forEach(function(el) { el.value = ''; });
    }
}

// ===========================
// LOAD CATEGORIES
// ===========================
function pdbLoadCategoryOptions() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', PDB_API + '?action=category_tree' + PDB_TQ);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                window.pdbCategoryPaths = JSON.parse(xhr.responseText);
                // Populate all existing selects
                document.querySelectorAll('.pdb-category-select').forEach(function(sel) {
                    pdbPopulateCategorySelect(sel);
                });
            } catch(e) {}
        }
    };
    xhr.send();
}

function pdbPopulateCategorySelect(selectEl) {
    var paths = window.pdbCategoryPaths || [];
    var currentVal = selectEl.value;
    selectEl.innerHTML = '<option value="">— Kategorie wählen —</option>';
    paths.forEach(function(path) {
        var opt = document.createElement('option');
        opt.value = path;
        var parts = path.split('/');
        var indent = '';
        for (var i = 1; i < parts.length; i++) indent += '\u00A0\u00A0';
        opt.textContent = indent + parts[parts.length - 1];
        selectEl.appendChild(opt);
    });
    if (currentVal) selectEl.value = currentVal;
}

// ===========================
// COLLECT FORM DATA
// ===========================
function pdbCollectData() {
    var data = {
        // Taxonomie (direct fields)
        genus:      document.getElementById('f-genus').value.trim(),
        species:    document.getElementById('f-species').value.trim(),
        subspecies: document.getElementById('f-subspecies').value.trim(),
        cultivar:   document.getElementById('f-cultivar').value.trim(),
        ecotype:    document.getElementById('f-ecotype').value.trim(),
        notes:              document.getElementById('f-notes').value.trim(),
        description_short:  document.getElementById('f-description_short').value.trim(),
        description_long:   document.getElementById('f-description_long').value.trim(),
        care_instructions:  document.getElementById('f-care_instructions').value.trim(),
        meta_text:          document.getElementById('f-meta_text').value.trim(),
        // Sections collected from data-key attributes
        names: {},
        categories: [],
        site: {},
        flower: {},
        growth: {},
        origin: {},
        fruit: {},
        leaf: {},
        root: {},
        usage: {},
        nursery: {},
        synonyms: []
    };

    // Collect all fields with data-key
    document.querySelectorAll('[data-key]').forEach(function(el) {
        var key = el.getAttribute('data-key');
        var parts = key.split('.');
        var val;

        if (el.classList.contains('pdb-multiselect')) {
            // Use click-order array if available, otherwise fall back to DOM order
            var order = el._selectionOrder;
            if (order && order.length > 0) {
                val = order.join(',');
            } else {
                var checked = [];
                el.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) {
                    checked.push(cb.value);
                });
                val = checked.join(',');
            }
        } else if (el.type === 'checkbox') {
            val = el.checked ? 1 : 0;
        } else {
            val = el.value;
        }

        if (parts.length === 2) {
            // Simple: section.field
            var section = parts[0];
            var field = parts[1];
            if (data[section] !== undefined && typeof data[section] === 'object' && !Array.isArray(data[section])) {
                data[section][field] = val;
            }
        } else if (parts.length === 3) {
            // Multi: section.index.field (categories, synonyms)
            var section = parts[0];
            var idx = parseInt(parts[1]);
            var field = parts[2];
            if (section === 'categories') {
                // Find or create entry
                if (!data.categories[idx]) data.categories[idx] = {};
                data.categories[idx][field] = val;
            } else if (section === 'synonyms') {
                if (!data.synonyms[idx]) data.synonyms[idx] = {};
                data.synonyms[idx][field] = val;
            }
        }
    });

    // Clean categories: remove empty, re-index
    data.categories = data.categories.filter(function(c) {
        return c && c.category_path && c.category_path !== '';
    });

    // Clean synonyms: remove empty
    data.synonyms = data.synonyms.filter(function(s) {
        return s && s.synonym_name && s.synonym_name.trim() !== '';
    });

    return data;
}

// ===========================
// SAVE
// ===========================
function pdbSave() {
    var btn = document.getElementById('pdb-save-btn');
    var msgEl = document.getElementById('pdb-message');

    // Validate genus
    var genus = document.getElementById('f-genus').value.trim();
    if (!genus) {
        pdbShowMessage('Gattung ist ein Pflichtfeld!', 'error');
        document.getElementById('f-genus').focus();
        return;
    }

    // Check duplicate status
    var dupStatus = document.getElementById('pdb-duplicate-status');
    if (dupStatus.classList.contains('pdb-duplicate-error')) {
        pdbShowMessage('Duplikat erkannt! Bitte anderen Namen wählen.', 'error');
        return;
    }

    // Collect data
    var data = pdbCollectData();
    data.action = 'save';
    if (pdbEditId) { data.id = pdbEditId; }
    var shareEl = document.getElementById('f-share');
    if (shareEl) { data.share = shareEl.checked ? 1 : 0; }

    // Disable button
    btn.disabled = true;
    btn.textContent = 'Speichert...';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', PDB_API + '?action=save' + PDB_TQ);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onload = function() {
        btn.disabled = false;
        btn.textContent = 'Pflanze speichern';

        if (xhr.status === 200) {
            try {
                var result = JSON.parse(xhr.responseText);
                if (result.success) {
                    pdbFormDirty = false;
                    pdbShowMessage(result.message, 'success');
                    // Im Edit-Modus: zurück zur Liste (eigene DB: zum Sortiment), sonst Formular leeren
                    setTimeout(function() {
                        if (pdbEditId) {
                            window.location.href = PDB_BASE + (PDB_TARGET === 'own' ? '/bestand' : '/liste');
                        } else {
                            pdbResetForm();
                        }
                    }, 800);
                } else {
                    pdbShowMessage(result.error || 'Unbekannter Fehler', 'error');
                }
            } catch(e) {
                pdbShowMessage('Antwort-Fehler: ' + e.message, 'error');
            }
        } else {
            pdbShowMessage('Server-Fehler: ' + xhr.status, 'error');
        }
    };
    xhr.onerror = function() {
        btn.disabled = false;
        btn.textContent = 'Pflanze speichern';
        pdbShowMessage('Netzwerk-Fehler', 'error');
    };
    xhr.send(JSON.stringify(data));
}

function pdbShowMessage(text, type) {
    var el = document.getElementById('pdb-message');
    el.textContent = text;
    el.className = 'pdb-message pdb-message-' + type;
    el.style.display = 'block';
    // Scroll to top
    el.scrollIntoView({behavior: 'smooth', block: 'start'});
    // Auto-hide after 8 seconds for success
    if (type === 'success') {
        setTimeout(function() { el.style.display = 'none'; }, 8000);
    }
}

function pdbResetForm() {
    pdbFormDirty = false;
    document.getElementById('pdb-form').reset();
    document.getElementById('pdb-preview-botanical').textContent = '—';
    document.getElementById('pdb-preview-webname').textContent = '—';
    document.getElementById('pdb-preview-matchcode').textContent = '—';
    document.getElementById('pdb-duplicate-status').className = 'pdb-duplicate-ok';
    document.getElementById('pdb-duplicate-status').innerHTML = '&#10003; Kein Duplikat';
    // Edit-Modus zurücksetzen
    pdbEditId = 0;
    document.querySelector('.pdb-eingabe-title').textContent = 'Neue Pflanze erfassen';
    var saveBtn = document.getElementById('pdb-save-btn');
    saveBtn.textContent = 'Pflanze speichern';
    saveBtn.classList.remove('pdb-btn-edit');
    // Close all sections except taxonomie
    document.querySelectorAll('.pdb-section').forEach(function(sec) {
        if (!sec.classList.contains('pdb-section-main')) {
            sec.classList.remove('pdb-section-open');
            sec.querySelector('.pdb-section-body').style.display = 'none';
        }
    });
    // Clear dots
    document.querySelectorAll('.pdb-section-dot').forEach(function(dot) {
        dot.classList.remove('pdb-has-data');
    });
    // Focus genus
    document.getElementById('f-genus').focus();
}

// ===========================
// SECTION DOTS (green indicator)
// ===========================
function pdbUpdateDots() {
    var sectionMap = {
        'names':      'dot-names',
        'categories': 'dot-categories',
        'site':       'dot-site',
        'flower':     'dot-flower',
        'growth':     'dot-growth',
        'origin':     'dot-origin',
        'fruit':      'dot-fruit',
        'leaf':       'dot-leaf',
        'root':       'dot-root',
        'usage':      'dot-usage',
        'nursery':    'dot-nursery',
        'synonyms':   'dot-synonyms'
    };

    for (var section in sectionMap) {
        var dotEl = document.getElementById(sectionMap[section]);
        if (!dotEl) continue;
        var hasData = false;
        // Check all fields in this section
        document.querySelectorAll('[data-key^="' + section + '."]').forEach(function(el) {
            if (el.type === 'checkbox') {
                if (el.checked) hasData = true;
            } else {
                if (el.value && el.value.trim() !== '') hasData = true;
            }
        });
        if (hasData) {
            dotEl.classList.add('pdb-has-data');
        } else {
            dotEl.classList.remove('pdb-has-data');
        }
    }
}

// ===========================
// UNSAVED CHANGES WARNING
// ===========================
var pdbFormDirty = false;

function pdbMarkDirty() {
    pdbFormDirty = true;
}

window.addEventListener('beforeunload', function(e) {
    if (pdbFormDirty) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// ===========================
// INIT
// ===========================
document.addEventListener('DOMContentLoaded', function() {
    pdbLoadCategoryOptions();

    // Edit-Modus: URL-Parameter prüfen
    var urlParams = new URLSearchParams(window.location.search);
    var editId = urlParams.get('edit');
    if (editId && parseInt(editId) > 0) {
        pdbLoadPlant(parseInt(editId));
    }

    // Auto-fill hardiness_temp_min from zone selection
    var zoneSelect = document.getElementById('f-hardiness_zone');
    if (zoneSelect) {
        zoneSelect.addEventListener('change', function() {
            var temp = this.options[this.selectedIndex].dataset.temp;
            if (temp !== undefined) {
                document.getElementById('f-hardiness_temp_min').value = temp;
            }
        });
    }

    // Listen for input changes to update dots and dirty flag
    document.getElementById('pdb-form').addEventListener('input', function() {
        pdbUpdateDots();
        pdbMarkDirty();
    });
    document.getElementById('pdb-form').addEventListener('change', function() {
        pdbUpdateDots();
        pdbMarkDirty();
    });
});
