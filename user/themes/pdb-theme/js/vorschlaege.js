/* Vorschlaege der Gaertnereien: Liste und Pruefmaske (Umbau A, Stufe 2).
 * Alle Texte aus den Vorschlaegen werden nur ueber textContent/value eingesetzt, nie als HTML. */
(function () {
    'use strict';

    var API = (window.PDB_BASE || '') + '/pdb-api/vorschlaege';
    var listView = document.getElementById('pdb-vs-list-view');
    var maskView = document.getElementById('pdb-vs-mask-view');
    var msgEl = document.getElementById('pdb-vs-msg');
    var filter = 'offen';

    var STATUS = { offen: 'Offen', uebernommen: 'Übernommen', abgelehnt: 'Abgelehnt', zurueckgezogen: 'Zurückgezogen' };
    var KIND = { neu: 'Neue Pflanze', aenderung: 'Änderung' };

    function h(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) { e.className = cls; }
        if (text !== undefined && text !== null) { e.textContent = text; }
        return e;
    }
    function add(parent) {
        for (var i = 1; i < arguments.length; i++) {
            if (arguments[i]) { parent.appendChild(arguments[i]); }
        }
        return parent;
    }
    function clear(node) { while (node.firstChild) { node.removeChild(node.firstChild); } }

    function fmtDate(s) {
        if (!s) { return ''; }
        var d = new Date(String(s).replace(' ', 'T') + 'Z');
        return isNaN(d.getTime()) ? s : d.toLocaleString('de-CH', { dateStyle: 'short', timeStyle: 'short' });
    }

    function showMsg(text, ok) {
        msgEl.textContent = text;
        msgEl.className = 'pdb-vs-msg ' + (ok ? 'pdb-vs-msg-ok' : 'pdb-vs-msg-err');
        msgEl.hidden = false;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function hideMsg() { msgEl.hidden = true; }

    function call(action, query, body) {
        var url = API + '?action=' + encodeURIComponent(action) + (query || '');
        var opt = { credentials: 'same-origin', headers: {} };
        if (body) {
            opt.method = 'POST';
            opt.headers['Content-Type'] = 'application/json';
            opt.body = JSON.stringify(body);
        }
        return fetch(url, opt).then(function (r) {
            return r.json().catch(function () { return { error: 'Ungültige Antwort vom Server (' + r.status + ').' }; });
        }).catch(function () { return { error: 'Verbindungsfehler.' }; });
    }

    function chip(kind, text) { return h('span', 'pdb-vs-chip pdb-vs-chip-' + kind, text); }

    // ---------------------------------------------------------------- Liste
    function showList() {
        maskView.hidden = true;
        listView.hidden = false;
        clear(listView);
        listView.appendChild(h('p', 'pdb-vs-sub', 'Lade …'));
        call('list', '&status=' + encodeURIComponent(filter)).then(function (d) {
            clear(listView);
            if (d.error) { showMsg(d.error, false); return; }
            var tabs = h('div', 'pdb-vs-tabs');
            var total = 0;
            Object.keys(d.counts).forEach(function (k) { total += d.counts[k]; });
            [['offen', 'Offen (' + d.counts.offen + ')'], ['uebernommen', 'Übernommen (' + d.counts.uebernommen + ')'],
             ['abgelehnt', 'Abgelehnt (' + d.counts.abgelehnt + ')'], ['zurueckgezogen', 'Zurückgezogen (' + d.counts.zurueckgezogen + ')'],
             ['alle', 'Alle (' + total + ')']].forEach(function (t) {
                var b = h('button', 'pdb-vs-tab' + (filter === t[0] ? ' pdb-vs-tab-active' : ''), t[1]);
                b.type = 'button';
                b.addEventListener('click', function () { filter = t[0]; hideMsg(); showList(); });
                tabs.appendChild(b);
            });
            listView.appendChild(tabs);

            if (!d.items.length) {
                listView.appendChild(h('div', 'pdb-vs-empty', filter === 'offen' ? 'Keine offenen Vorschläge.' : 'Keine Einträge.'));
                return;
            }
            var table = h('table', 'pdb-vs-table');
            var head = h('tr');
            ['Gärtnerei', 'Pflanze', 'Art', 'Eingereicht', 'Status'].forEach(function (c) { head.appendChild(h('th', null, c)); });
            add(table, add(h('thead'), head));
            var body = h('tbody');
            d.items.forEach(function (it) {
                var tr = h('tr', 'pdb-vs-row');
                tr.appendChild(h('td', null, it.nursery_name || '(unbekannt)'));
                var nameTd = h('td');
                nameTd.appendChild(h('i', null, it.botanical_name || ''));
                tr.appendChild(nameTd);
                add(tr, add(h('td'), chip(it.kind, KIND[it.kind] || it.kind)));
                tr.appendChild(h('td', null, fmtDate(it.status === 'offen' ? it.updated_at : (it.decided_at || it.updated_at))));
                var st = h('td');
                st.appendChild(chip(it.status, STATUS[it.status] || it.status));
                if (it.decision_note) { st.appendChild(h('div', 'pdb-vs-sub', it.decision_note)); }
                tr.appendChild(st);
                tr.addEventListener('click', function () { hideMsg(); openMask(it.id); });
                body.appendChild(tr);
            });
            table.appendChild(body);
            listView.appendChild(table);
        });
    }

    // ---------------------------------------------------------------- Maske
    function masterText(r) {
        if (r.type === 'checkbox') { return r.master === '1' ? 'Ja' : ''; }
        if (r.type === 'multi') { return r.master.split(',').join(', '); }
        return r.master;
    }

    /** Eingabe fuer einen Vorschlagswert; get() liefert den aktuellen Wert als Text, onChange wird bei Aenderung gerufen. */
    function makeControl(r, onChange) {
        var ctl = { node: null, get: null };
        var t = r.type;
        if (t === 'textarea') {
            var ta = h('textarea'); ta.value = r.value;
            ta.addEventListener('input', onChange);
            ctl.node = ta; ctl.get = function () { return ta.value; };
        } else if (t === 'select') {
            var sel = h('select');
            sel.appendChild(h('option', null, '—')).value = '';
            var opts = (r.options || []).slice();
            if (r.value && opts.indexOf(r.value) < 0) { opts.push(r.value); }
            opts.forEach(function (o) { var op = h('option', null, o); op.value = o; sel.appendChild(op); });
            sel.value = r.value;
            sel.addEventListener('change', onChange);
            ctl.node = sel; ctl.get = function () { return sel.value; };
        } else if (t === 'checkbox') {
            var cb = h('input'); cb.type = 'checkbox'; cb.checked = r.value === '1';
            cb.addEventListener('change', onChange);
            var wrap = h('label'); wrap.appendChild(cb); wrap.appendChild(document.createTextNode(' ' + r.label));
            ctl.node = wrap; ctl.get = function () { return cb.checked ? '1' : ''; };
        } else if (t === 'multi') {
            var box = h('div', 'pdb-vs-multi');
            var mine = r.value ? r.value.split(',') : [];
            var opts2 = (r.options || []).slice();
            mine.forEach(function (m) { if (opts2.indexOf(m) < 0) { opts2.push(m); } });
            var cbs = [];
            opts2.forEach(function (o) {
                var l = h('label'); var c = h('input'); c.type = 'checkbox'; c.value = o; c.checked = mine.indexOf(o) >= 0;
                c.addEventListener('change', onChange);
                l.appendChild(c); l.appendChild(document.createTextNode(' ' + o));
                box.appendChild(l); cbs.push(c);
            });
            ctl.node = box;
            ctl.get = function () {
                var on = cbs.filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
                // Reihenfolge des Vorschlags behalten, neu Angehaktes hinten anfuegen
                var first = mine.filter(function (m) { return on.indexOf(m) >= 0; });
                var rest = on.filter(function (m) { return first.indexOf(m) < 0; });
                return first.concat(rest).join(',');
            };
        } else {
            var inp = h('input'); inp.type = (t === 'number') ? 'number' : 'text';
            if (t === 'number') { inp.step = 'any'; }
            inp.value = r.value;
            inp.addEventListener('input', onChange);
            ctl.node = inp; ctl.get = function () { return inp.value; };
        }
        return ctl;
    }

    function openMask(id) {
        listView.hidden = true;
        maskView.hidden = false;
        clear(maskView);
        maskView.appendChild(h('p', 'pdb-vs-sub', 'Lade …'));
        call('load', '&id=' + id).then(function (d) {
            if (d.error) { showMsg(d.error, false); showList(); return; }
            renderMask(d);
        });
    }

    function renderMask(d) {
        clear(maskView);
        var back = h('button', 'pdb-vs-back', '← Zur Liste');
        back.type = 'button';
        back.addEventListener('click', function () { hideMsg(); showList(); });
        maskView.appendChild(back);

        var head = h('div', 'pdb-vs-head');
        head.appendChild(add(h('h2'), h('span', null, d.botanical_name || '')));
        var meta = h('div', 'pdb-vs-meta');
        add(meta, chip(d.status, STATUS[d.status] || d.status), chip(d.kind, KIND[d.kind] || d.kind),
            h('span', null, 'Gärtnerei: ' + (d.nursery_name || '(unbekannt)')),
            h('span', null, 'eingereicht: ' + fmtDate(d.updated_at)));
        head.appendChild(meta);
        maskView.appendChild(head);

        (d.warnings || []).forEach(function (w) { maskView.appendChild(h('div', 'pdb-vs-warn', w)); });
        if (!d.editable) {
            var info = 'Entschieden' + (d.decided_by ? ' von ' + d.decided_by : '') + (d.decided_at ? ' am ' + fmtDate(d.decided_at) : '') + '.';
            if (d.decision_note) { info += ' Hinweis: ' + d.decision_note; }
            maskView.appendChild(h('div', 'pdb-vs-info', info));
        }
        if (d.kind === 'neu') {
            maskView.appendChild(h('div', 'pdb-vs-info', 'Neue Pflanze: Die Namensfelder gehören zur Aufnahme und sind vorgewählt. Sie dürfen sie korrigieren.'));
        } else {
            maskView.appendChild(h('div', 'pdb-vs-info', 'Angezeigt werden nur Felder, die sich von der PDB unterscheiden' +
                (d.same ? ' (' + d.same + ' Felder sind gleich)' : '') + '. Namen bestehender Pflanzen sind gesperrt und werden in «Eingabe PDB» geändert.'));
        }

        var rows = [];            // {r, ctl, cb}
        var cats = [];            // {path, cb}
        var syns = [];            // {key, cb}
        var okBtn;

        function refresh() {
            var n = 0;
            rows.forEach(function (x) { if (x.cb.checked && !x.r.locked) { n++; } x.tr.className = x.cb.checked ? 'pdb-vs-row-on' : ''; });
            cats.forEach(function (x) { if (x.cb.checked) { n++; } });
            syns.forEach(function (x) { if (x.cb.checked) { n++; } });
            if (okBtn) { okBtn.textContent = 'Angehakte übernehmen (' + n + ')'; okBtn.disabled = (n === 0); }
        }

        if (!d.sections.length && !d.cats.length && !d.syns.length) {
            maskView.appendChild(h('div', 'pdb-vs-empty', 'Keine Abweichungen zur öffentlichen PDB.'));
        }

        d.sections.forEach(function (s) {
            var sec = h('div', 'pdb-vs-section');
            sec.appendChild(h('h3', null, s.title));
            var table = h('table', 'pdb-vs-grid');
            var hr = h('tr');
            ['Feld', 'PDB heute', 'Vorschlag', 'Übernehmen'].forEach(function (c) { hr.appendChild(h('th', null, c)); });
            table.appendChild(add(h('thead'), hr));
            var tb = h('tbody');
            s.rows.forEach(function (r) {
                var tr = h('tr');
                var x = { r: r, tr: tr, ctl: null, cb: null };
                var cbTd = h('td', 'pdb-vs-c-take');
                var cb = h('input'); cb.type = 'checkbox'; cb.checked = !!r.checked;
                cb.addEventListener('change', refresh);
                x.cb = cb;
                tr.appendChild(h('td', 'pdb-vs-c-label', r.label));
                var mt = masterText(r);
                var mtd = h('td', 'pdb-vs-c-master', mt || '');
                if (!mt) { mtd.textContent = '–'; mtd.className += ' pdb-vs-none'; }
                tr.appendChild(mtd);
                var vtd = h('td', 'pdb-vs-c-value');
                if (r.locked || !d.editable) {
                    var vt = r.type === 'checkbox' ? (r.value === '1' ? 'Ja' : '') : (r.type === 'multi' ? r.value.split(',').join(', ') : r.value);
                    vtd.appendChild(h('span', r.locked ? 'pdb-vs-locked' : null, vt || '–'));
                    if (r.locked) { vtd.appendChild(h('span', 'pdb-vs-lockhint', 'gesperrt (Name bestehender Pflanze)')); }
                    cb.disabled = true; cb.checked = false;
                } else {
                    x.ctl = makeControl(r, function () { cb.checked = true; refresh(); });
                    vtd.appendChild(x.ctl.node);
                    if (r.required) { cb.checked = true; cb.disabled = true; }
                }
                tr.appendChild(vtd);
                cbTd.appendChild(cb);
                tr.appendChild(cbTd);
                rows.push(x);
                tb.appendChild(tr);
            });
            table.appendChild(tb);
            sec.appendChild(table);
            maskView.appendChild(sec);
        });

        if (d.cats.length || d.syns.length) {
            var sec2 = h('div', 'pdb-vs-section');
            sec2.appendChild(h('h3', null, 'Kategorien und Synonyme (Ergänzungen)'));
            var box = h('div', 'pdb-vs-list-box');
            if (d.cats.length) {
                box.appendChild(h('div', 'pdb-vs-sub', 'Neue Kategorien'));
                d.cats.forEach(function (c) {
                    var l = h('label'); var cb = h('input'); cb.type = 'checkbox'; cb.checked = !!c.checked; cb.disabled = !d.editable;
                    cb.addEventListener('change', refresh);
                    l.appendChild(cb); l.appendChild(document.createTextNode(' ' + c.path));
                    box.appendChild(l); cats.push({ path: c.path, cb: cb });
                });
            }
            if (d.master_cats && d.master_cats.length) {
                box.appendChild(h('div', 'pdb-vs-sub', 'Schon in der PDB: ' + d.master_cats.join('; ')));
            }
            if (d.syns.length) {
                box.appendChild(h('div', 'pdb-vs-sub', 'Neue Synonyme'));
                d.syns.forEach(function (s) {
                    var l = h('label'); var cb = h('input'); cb.type = 'checkbox'; cb.checked = !!s.checked; cb.disabled = !d.editable;
                    cb.addEventListener('change', refresh);
                    var txt = s.name + (s.lang || s.type ? ' (' + [s.lang, s.type].filter(Boolean).join(', ') + ')' : '');
                    l.appendChild(cb); l.appendChild(document.createTextNode(' ' + txt));
                    box.appendChild(l); syns.push({ key: s.key, cb: cb });
                });
            }
            sec2.appendChild(box);
            maskView.appendChild(sec2);
        }

        if (d.editable) {
            var actions = h('div', 'pdb-vs-actions');
            var note = h('input'); note.type = 'text'; note.maxLength = 500; note.placeholder = 'Hinweis an die Gärtnerei (optional)';
            okBtn = h('button', 'pdb-vs-btn pdb-vs-btn-ok', 'Angehakte übernehmen'); okBtn.type = 'button';
            var noBtn = h('button', 'pdb-vs-btn pdb-vs-btn-no', 'Ablehnen'); noBtn.type = 'button';
            var all = h('button', 'pdb-vs-link', 'alle'); all.type = 'button';
            var none = h('button', 'pdb-vs-link', 'keine'); none.type = 'button';
            all.addEventListener('click', function () {
                rows.forEach(function (x) { if (!x.r.locked && x.r.value !== '') { x.cb.checked = true; } });
                cats.forEach(function (x) { x.cb.checked = true; }); syns.forEach(function (x) { x.cb.checked = true; }); refresh();
            });
            none.addEventListener('click', function () {
                rows.forEach(function (x) { if (!x.r.required) { x.cb.checked = false; } });
                cats.forEach(function (x) { x.cb.checked = false; }); syns.forEach(function (x) { x.cb.checked = false; }); refresh();
            });
            add(actions, okBtn, noBtn, note, h('span', 'pdb-vs-sub', 'Auswahl:'), all, none);
            maskView.appendChild(actions);

            okBtn.addEventListener('click', function () {
                var values = {}, checked = [];
                rows.forEach(function (x) {
                    if (x.cb.checked && !x.r.locked && x.ctl) { checked.push(x.r.key); values[x.r.key] = x.ctl.get(); }
                });
                var payload = {
                    id: d.id, values: values, checked: checked, note: note.value,
                    cats: cats.filter(function (x) { return x.cb.checked; }).map(function (x) { return x.path; }),
                    syns: syns.filter(function (x) { return x.cb.checked; }).map(function (x) { return x.key; })
                };
                okBtn.disabled = true;
                call('apply', '', payload).then(function (res) {
                    if (res.error) { showMsg(res.error, false); refresh(); return; }
                    showMsg(res.message || 'Übernommen.', true); showList();
                });
            });
            noBtn.addEventListener('click', function () {
                if (!window.confirm('Diesen Vorschlag ablehnen?')) { return; }
                call('reject', '', { id: d.id, note: note.value }).then(function (res) {
                    if (res.error) { showMsg(res.error, false); return; }
                    showMsg(res.message || 'Abgelehnt.', true); showList();
                });
            });
            refresh();
        }
    }

    showList();
})();
