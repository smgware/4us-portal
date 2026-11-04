# Alerty 1.2.0

Önálló, vanilla JavaScript popup-kezelő egyedi ablakazonosítóval. Nincs jQuery függőség. A feltöltött Alertify mintából a `set`, `setHeader`, `setContent`, `setFooter` mintát követi, de nem az Alertify teljes API-jának másolata.

## Indítás

Csomagold ki, majd nyisd meg az `index.html` fájlt böngészőben. A demo Bootstrap és ikon CSS-e CDN-ről töltődik, így ezekhez internet szükséges. Maga az Alerty internet és Bootstrap nélkül működik.

A saját projektedbe elég az alábbi két fájl:

```html
<link rel="stylesheet" href="css/alerty.css">
<script defer src="js/alerty.js"></script>
```

A popupokat a DOM elkészülte után nyisd meg. A body-n állítsd a `light-mode` vagy `dark-mode` osztályt; ha egyik sincs, light-mode színeket használ.

## Alapműködés

Az `alerty.alert()` alapértelmezett láblécében csak Bezárás gomb van. A `setFooter(html)` egyedi láblécet állít be, a `setFooter(null)` visszaállítja az alapértelmezettet.

A `setFooter(html)` pontosan a megadott tartalmat jeleníti meg: nem szúr be ikonokat és nem írja át a gombfeliratokat. Egyedi ikonhoz az `<i class="alerty-save-icon" aria-hidden="true"></i>` vagy az `<i class="alerty-cancel-icon" aria-hidden="true"></i>` elemet külön add meg a HTML-ben.

Az `alerty.alert()` popup példányt ad vissza, ezért a beállító metódusai láncolhatók. Az azonosítót a lánc végén a `getId()` vagy `getid()` adja vissza:

```js
const id = alerty.alert('Cím', 'Tartalom')
  .setFooter('<button data-name="btnAlertyCancel">Bezárás</button>')
  .getid();
```

A korábban közvetlenül visszaadott azonosítóhoz most `.getId()` szükséges. Az `alerty.get()` és az `alerty.close()` popup példányt is elfogad. Az `alerty.popup()` az `alerty.alert()` aliasa, ugyanazokkal a paraméterekkel és alapértelmezett Bezárás gombbal. Az `alerty.confirm()` szintén láncolható popup példányt ad vissza. Az `alerty.message()` továbbra is azonosítót ad vissza.

```js
const popupId = alerty.popup('Cím', 'Tartalom')
  .setFooter('<button data-name="btnAlertyCancel">Bezárás</button>')
  .getid();

const confirmId = alerty.confirm('Megerősítés', 'Folytatod?')
  .set('width', 450)
  .getid();
```

```js
const acode = alerty.alert('Hozzáadás', '<p>HTML tartalom</p>').getId();
const popup = alerty.get(acode);
popup.setFooter('<button type="button" data-name="btnAlertySave">Mentés</button><button type="button" data-name="btnAlertyCancel">Bezárás</button>');

popup
  .set('width', 700)
  .onSave(function (event) {
    // this = az adott popup példánya
    console.log('Mentés', this.id);
    this.close(); // nem záródik automatikusan mentéskor
  })
  .onCancel(function (event) {
    console.log('Mégse', this.id);
    this.close(); // saját handler esetén mi döntjük el, bezárjuk-e
  })
  .onClose(function () {
    console.log('Ablak bezárult:', this.id);
  });

popup.save();   // programozott Mentés esemény
popup.cancel(); // programozott Mégse esemény
popup.close();  // azonnali bezárás, onClose eseménnyel

popup.setHeader('<b>Új cím</b>');
popup.setContent('<p>Új tartalom</p>');
popup.setFooter('<button data-name="btnAlertySave">Mentés</button>');
```

A `onSave()` és `onCancel()` kizárólag a megfelelő gomb eseményére, vagy a `save()` / `cancel()` programozott meghívására fut le. Az `onClose()` minden sikeres bezárás után lefut (X, Escape, programozott `close()` stb.).

A Mentés sosem zár automatikusan normál `alert` esetén. A Mégse csak **akkor** zár automatikusan, ha nincs saját `onCancel()` kezelő. Ha van kezelő, a bezárást `this.close()`-zal végezd el. A `confirm()` callbackek alapértelmezetten automatikusan bezárnak, kivéve ha `false` a visszatérési értékük.

A régi `clickSaveBrn`, `clickSaveBtn`, `clickCancelBrn`, `clickCancelBtn` metódusok kompatibilitási aliasként megmaradtak, új kódban az `onSave()` és `onCancel()` használata javasolt.

## Előre összeállított láblécgombok

```js
const id = alerty.popup('Szerkesztés', 'Tartalom')
  .setButtons('buttonsSaveCancel')
  .onSave(function () {
    alerty.message('Sikeres mentés!', 'success', 3);
    this.close();
  })
  .onCancel(function () { this.close(); })
  .getid();
```

- `buttonsSaveCancel`: Mentés és Mégse (`onSave`, `onCancel`).
- `buttonsConfirmCancel`: Megerősítés és Mégse (`onSave` vagy `onOk`, illetve `onCancel`).
- `buttonsOk`: OK (`onOk`, saját kezelő nélkül bezár).
- `buttonsCancel`: Mégse (`onCancel`, saját kezelő nélkül bezár).

A `setButtons()` lecseréli a lábléc tartalmát. Egy későbbi `setFooter(html)` ezt teljesen felülírja. Saját eseménykezelő esetén a bezárásról a kezelő gondoskodik; a `confirm()` callbackjeinek automatikus bezárása megmarad.

## Confirm használata

```js
const acode = alerty.alert('Hozzáadás', '<p>Módosítások vannak.</p>').getId();
alerty.get(acode).setFooter('<button type="button" data-name="btnAlertySave">Mentés</button>');
alerty.get(acode).onSave(function () {
  const popup = this;
  alerty.confirm('Megerősítés', 'Bezárod?', function () {
    popup.close();
  }, function () {
    console.log('Mégsem');
  });
});
```

A confirm az OK vagy Mégse kiválasztása után automatikusan bezár. Ha a callback `false` értéket ad vissza, a confirm nyitva marad (OK és Cancel esetén is).

## Állítható opciók

Az `alerty.alert()` és az `alerty.popup()` mérete százalékosan is megadható a láncolható `resizeTo(szélesség, magasság)` metódussal:

```js
const id = alerty.popup('Cím', 'Tartalom')
  .resizeTo(80, 60) // a böngészőablak szélességének 80%-a és magasságának 60%-a
  .getid();

alerty.get(id).resizeTo('70%', '50%');
```

Mindkét méret kötelező, 0-nál nagyobb és legfeljebb 100 lehet. A méret a böngészőablak átméretezését is követi; a meglévő minimális méret és a képernyőszéli margók továbbra is érvényesek.

768 px alatti ablakszélességnél a `resizeTo()`-val méretezett popup automatikusan 100% széles és 100% magas, margó nélkül. 768 px-től visszaáll a megadott százalékos méretre.

```js
const id = alerty.alert('Cím', 'Tartalom', { width: 650, movable: false }).getId();
alerty.get(id).set('width', 700);
alerty.get(id).set({
  title: 'Új cím',
  message: '<p>Új HTML tartalom</p>',
  modal: true,
  closable: true,
  closableByDimmer: false,
  closeOnEscape: true,
  movable: true,
  resizable: true,
  maximizable: true,
  width: '640px',
  height: 'auto',
  transition: 'zoom', // zoom, fade, none
  padding: true
});
console.log(alerty.get(id).get('width'));
alerty.set('width', 600); // alapértelmezés későbbi ablakokhoz
```

`labels: { ok: 'Igen', cancel: 'Nem' }` állítható confirm esetén. Eseménybeállítások: `onshow`, `onclosing`, `onclose` (az utóbbi a `popup.onClose()` mellett is meghívódik).

## Egyéb API

- `alerty.get(id)` — az adott popup vagy `null` (lezárt popupot eltávolítja a regiszterből).
- `alerty.close(id)` / `alerty.closeAll()` / `alerty.getAll()`.
- `popup.close()` / `popup.destroy()` / `popup.show()` / `popup.bringToFront()`.
- `popup.isOpen()` / `popup.getId()` / `popup.find(cssSelector)` / `popup.maximize()`.
- `popup.setHeader(html|Node)` / `setContent(html|Node)` / `setFooter(html|Node)`.
- `popup.set(key, value)` / `popup.set({...})` / `popup.get(key)` / `popup.setting(key[,value])`.
- `popup.onSave(fn)` / `popup.onCancel(fn)` / `popup.onClose(fn)` — láncolható eseménykezelők.
- `popup.save()` / `popup.cancel()` — gombműveletek kiváltása programból.
- Régi kompatibilis aliasok: `clickSaveBrn`, `clickSaveBtn`, `clickCancelBrn`, `clickCancelBtn`.
- `alerty.confirm(title, html, onok, oncancel[, options])`.

A popup azonosítója nem DOM id-ből származtatott számláló, hanem időbélyeg + számláló kombináció. A `get()` csak jelenleg nyitott popupot ad vissza.

## Biztonság

A HTML-tartalom közvetlenül a DOM-ba kerül (`innerHTML`). Csak megbízható HTML-t adj meg, a felhasználói adatokat escape-eld/szűrd az XSS támadások elkerülése érdekében.

## Fájlok

```
alerty-project/
├─ index.html
├─ README.md
├─ css/
│  ├─ alerty.css
│  └─ demo.css
└─ js/
   ├─ alerty.js
   └─ demo.js
```

## Alertify-szerű értesítések (v1.2)

```js
alerty.message('Sikeres mentés', 'success', 3); // 3 másodperc
alerty.message('Hiba történt', 'error', 10); // 10 másodperc
alerty.message('Tájékoztatás', 'message', 5);
alerty.message('Bal felső értesítés', 'info', 5, 'top-left');
alerty.message('Alul középen', 'success', 3, 'bottom-center');
alerty.message('Marad amíg bezárom', 'warning', 0); // nem jár le
const msgId = alerty.message('Feldolgozás...', 'info', 0);
alerty.get(msgId).close(); // értesítés bezárása egyedi azonosítóval
alerty.closeAllMessages(); // csak a toast üzeneteket zárja
```

A második paraméter `message`, `info`, `success`, `error` vagy `warning` lehet.
A harmadik paraméter másodpercben megadott időtartam: `0` esetén kézzel kell bezárni.
A negyedik paraméter az elhelyezés: `top-left`, `top-center`, `top-right`, `bottom-left`, `bottom-center` vagy `bottom-right`.
Alapértelmezésben (és érvénytelen elhelyezés esetén) `bottom-right`, azaz jobb alsó sarok.
Az egyes elhelyezések külön üzenetcsoportot alkotnak, minden új üzenet az ugyanoda helyezett korábbiak **alá** kerül.
A szöveg biztonságosan, HTML értelmezése nélkül jelenik meg.

Egyszerre több popupnál az új ablak az előzőnél vizuálisan lejjebb helyezkedik el,
de aktív ablak lesz és a többiek fölött marad a kattinthatóság érdekében.
Minden popup footerje a dialógus legalján van, fix magasságnál is.
Az Alerty saját ikonja SVG-ként a CSS-be van ágyazva (nincs külső ikon vagy fontfüggőség).
