'use strict';
let latestId = null;
const $ = selector => document.querySelector(selector);
function log(message) {
  const el = $('#eventLog');
  el.textContent = `[${new Date().toLocaleTimeString('hu-HU')}] ${message}\n` + el.textContent;
}
function remember(popupOrId) { const id = typeof popupOrId === 'object' ? popupOrId.getId() : popupOrId; latestId = id; log(`Létrehozva: ${id}`); return id; }
$('#toggleTheme').addEventListener('click', () => {
  const dark = document.body.classList.toggle('dark-mode');
  document.body.classList.toggle('light-mode', !dark);
  $('#toggleTheme').innerHTML = dark ? '<i class="bi bi-sun"></i> Light mode' : '<i class="bi bi-moon-stars"></i> Dark mode';
  log(`Téma: ${dark ? 'dark-mode' : 'light-mode'}`);
});
$('#openSimple').addEventListener('click', () => {
  const acode = remember(alerty.alert('Hozzáadás', '<h5>Új rekord</h5><p>Ez a popup saját, egyedi azonosítót kapott.</p><label class="form-label">Megnevezés</label><input class="form-control" id="recordName" placeholder="Írj be egy nevet...">'));
  alerty.get(acode).setFooter('<button type="button" class="btn btn-sm btn-success" data-name="btnAlertySave">Mentés</button><button type="button" class="btn btn-sm btn-secondary" data-name="btnAlertyCancel">Bezárás</button>').onSave(function () {
    const name = this.find('#recordName').value.trim();
    if (!name) { this.find('#recordName').focus(); log('Üres megnevezés, nincs mentés.'); return; }
    log(`Mentés: ${name} (popup: ${this.id})`);
    this.close();
  }).onCancel(function () { log(`Bezárás gomb: ${this.id}`); this.close(); }).onClose(function () { log(`Popup bezárult: ${this.id}`); });
});
$('#openConfirm').addEventListener('click', () => {
  remember(alerty.confirm('Megerősítés', '<p>Biztosan szeretnéd végrehajtani a műveletet?</p>', function () { log(`Confirm OK: ${this.id}`); }, function () { log(`Confirm Mégse: ${this.id}`); }));
});
$('#openFooter').addEventListener('click', () => {
  const id = remember(alerty.alert('Egyedi footer', '<p>A footer tetszőleges HTML tartalmat fogadhat, a gombesemények pedig az új footerre is működnek.</p>'));
  alerty.get(id).setFooter(`<div style="display:flex;gap:8px;justify-content:flex-end;align-items:center"><small style="margin-right:auto;opacity:.7">Egyedi HTML footer</small><button class="btn btn-success" data-name="btnAlertySave"><i class="alerty-save-icon" aria-hidden="true"></i> Rendben</button><button class="btn btn-secondary" data-name="btnAlertyCancel">Vissza</button></div>`)
    .onSave(function () { log(`Egyedi footer gomb: ${this.id}`); this.close(); });
});
$('#openMultiple').addEventListener('click', () => {
  for (let i = 1; i <= 3; i++) {
    const id = remember(alerty.alert(`Ablak #${i}`, `<p>Egyedi popup azonosító: <code>${i}</code></p>`));
    alerty.get(id).set('width', `${360 + 20 * i}px`);
  }
  log(`Nyitott ablakok: ${alerty.getAll().length}`);
});
$('#openSettings').addEventListener('click', () => {
  const id = remember(alerty.alert('Eredeti cím', '<p>Az ablak beállításai módosulnak.</p>'));
  alerty.get(id).set({ title: 'Módosított cím ✓', content: '<p>Az <code>set({...})</code> módosította a címet, tartalmat és szélességet.</p>', width: 640, resizable: true, movable: true });
});
$('#closeLatest').addEventListener('click', () => {
  const p = alerty.get(latestId);
  if (p) { p.close(); log(`Bezárva: ${latestId}`); }
  else log('A legutolsó popup már nincs megnyitva.');
});
$('#closeAll').addEventListener('click', () => { alerty.closeAll(); log('Összes popup bezárva.'); });

$('#showMessages').addEventListener('click', () => {
  const id1 = alerty.message('Sikeresen elmentve.', 'success', 6);
  const id2 = alerty.message('A művelet nem sikerült.', 'error', 10);
  const id3 = alerty.message('Ez egy információs üzenet.', 'message', 5);
  log(`Üzenetek: ${id1}, ${id2}, ${id3}`);
});
