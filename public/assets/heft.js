/* Lehrgangsheft – baut die Seiten aus den Daten in #daten.
   Modi (body[data-modus]): "blaettern" = Webansicht mit Umblättern,
   "druck-heft" = alle Seiten für den PDF-Export, "druck-kurs" = nur die Doppelseite eines Lehrgangs. */
(function () {
  'use strict';
  const D = JSON.parse(document.getElementById('daten').textContent);
  const C = D.courses, M = D.meta;
  const MODUS = document.body.dataset.modus || 'blaettern';
  const NUR_KURS = +document.body.dataset.kurs || 0;
  const A = 'assets/'.replace(/^/, document.body.dataset.basis || '/');

  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const pd = s => { const [y, m, d] = String(s || '').split('-'); return { y, m, d }; };
  const MON = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
  function range(b, e) {
    if (!b) return '';
    const B = pd(b); if (!e || e === b) return `${B.d}.${B.m}.${B.y}`; const E = pd(e);
    if (B.y === E.y && B.m === E.m) return `${B.d}.–${E.d}.${E.m}.${E.y}`;
    if (B.y === E.y) return `${B.d}.${B.m}.–${E.d}.${E.m}.${E.y}`;
    return `${B.d}.${B.m}.${B.y} – ${E.d}.${E.m}.${E.y}`;
  }
  const de = s => { if (!s) return ''; const x = pd(s); return `${x.d}.${x.m}.${x.y}`; };
  const stClass = s => /freie/.test(s) ? 's-ok' : /ausgebucht/.test(s) ? 's-full' : 's-soon';
  const stText = s => String(s).replace('Anmeldung erst freigeschaltet ab', 'Anmeldung ab').replace(/\s+/g, ' ').replace(/ (\d\d:\d\d)$/, ', $1 Uhr');
  const ul = (a, two) => a && a.length ? `<ul class="b${two ? ' two' : ''}">${a.map(x => `<li>${esc(x)}</li>`).join('')}</ul>` : '';
  const sec = (h, body) => body ? `<div class="sec"><h3>${h}</h3>${body}</div>` : '';

  function qr(url) {
    const q = qrcode(0, 'M'); q.addData(url); q.make();
    const n = q.getModuleCount(), b = 2, w = n + 2 * b; let d = '';
    for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) if (q.isDark(r, c)) d += `M${c + b} ${r + b}h1v1h-1z`;
    return `<svg viewBox="0 0 ${w} ${w}" shape-rendering="crispEdges" role="img" aria-label="QR-Code"><rect width="${w}" height="${w}" fill="#fff"/><path d="${d}" fill="#111"/></svg>`;
  }
  const URL_UEBERSICHT = M.quelle;
  const URL_ABO = 'https://dlrg.net/apps/meineSeminare?page=lehrgaengeEmailabo';
  const URL_BED = M.quelle.replace(/\/?$/, '/') + 'teilnahmebedingungen/';

  // Seitenplan
  const order = [];
  D.cats.forEach(([cat, ids]) => ids.map(i => C[i]).sort((a, b) => String(a.beginn).localeCompare(String(b.beginn))).forEach(c => { c.cat = cat; order.push(c); }));
  let p = 6; order.forEach(c => { c.page = p; p += 2; });
  const closed = Object.values(C).filter(c => !c.offen).sort((a, b) => String(a.beginn).localeCompare(String(b.beginn)));
  const TOTAL = p + 2;
  const jahre = [...new Set(order.map(c => String(c.beginn).slice(0, 4)).filter(Boolean))].sort();
  const JAHR = jahre.length > 1 ? `${jahre[0]} | ${jahre[jahre.length - 1].slice(2)}` : (jahre[0] || String(new Date().getFullYear()));
  const JAHR_KURZ = JAHR.replace(' | ', '/');
  const pages = [];
  const go = n => `href="#seite-${n}" data-go="${n}"`;
  function page(n, cls, inner, run) {
    const side = n % 2 === 0 ? 'l' : 'r';
    const foot = (cls.includes('cover') || cls.includes('back')) ? '' : `<div class="foot"><span><b>${n}</b></span><span>DLRG LV Baden · Seminarheft ${JAHR_KURZ}</span></div>`;
    pages.push({ n, html: `<div class="pg ${side} ${cls}" id="seite-${n}" data-n="${n}"><div class="box"><div class="pc">${run ? `<div class="run">${run}</div>` : ''}${inner}</div>${foot}</div></div>` });
  }
  const BB = `<div class="bb"><span>LV Baden</span><img src="${A}wortmarke.svg" alt="DLRG"></div>`;

  // 1 Titel
  page(1, 'cover', `${BB}<img class="adler" src="${A}adler.png" alt="">
   <div class="txt"><div class="eyebrow">Aus- und Fortbildung</div>
    <div class="big">Lehrgänge</div><div class="yr">${esc(JAHR)}</div>
    <p class="sub">Seminarheft des Landesverbands Baden – ${order.length === 1 ? 'ein Lehrgang' : order.length + ' Lehrgänge und Tagungen'} mit Inhalten, Terminen, Voraussetzungen und QR-Code zur Anmeldung.</p></div>
   <div class="claim"><img src="${A}claim.svg" alt="Wasser lieben – Leben retten. DLRG"></div>`);

  // 2 Anmeldung
  page(2, '', `<h2 class="t">So kommst du zum Lehrgang</h2>
   <p class="lead">Alle Lehrgänge in diesem Heft schreibt der Landesverband Baden aus. Die Anmeldung läuft online über die DLRG-Seminarverwaltung.</p>
   <ol class="steps">
    <li><div><b>Lehrgang aussuchen</b>Zielgruppe und Inhalt stehen links, Voraussetzungen und Termine rechts auf der Doppelseite jedes Lehrgangs.</div></li>
    <li><div><b>Mit der Gliederung absprechen</b>Viele Lehrgänge verlangen eine Befürwortung durch die entsendende Gliederung.</div></li>
    <li><div><b>QR-Code scannen</b>Er führt direkt zur Anmeldung. Ist die Anmeldung noch nicht freigeschaltet, landest du auf der Lehrgangsseite – dort erscheint ab dem genannten Datum „Jetzt teilnehmen“.</div></li>
    <li><div><b>Nachweise rechtzeitig einreichen</b>Voraussetzungen haben eine eigene Frist, die oft vor dem Meldeschluss liegt.</div></li>
   </ol>
   <div class="note">Gebühren stehen beim jeweiligen Lehrgang. Maßgeblich sind immer die Angaben in der Online-Ausschreibung.</div>
   <div class="mini" style="grid-template-columns:28% 1fr;align-items:center"><figure>${qr(URL_BED)}</figure><div><b>Teilnahmebedingungen</b><br><a href="${esc(URL_BED)}" target="_blank" rel="noopener">${esc(URL_BED.replace(/^https?:\/\//, '').replace(/\/$/, ''))}</a></div></div>`,
    '<span>Vorwort</span><b>Anmeldung</b>');

  // 3 Inhalt (Einträge springen direkt zum Lehrgang – im Web und im PDF)
  page(3, '', `<h2 class="t">Inhalt</h2><div class="toc">
   <div><a ${go(4)}><span>Terminübersicht</span><span>4</span></a></div>
   ${D.cats.map(([cat]) => `<div><h3>${esc(cat)}</h3>${order.filter(c => c.cat === cat).map(c => `<a ${go(c.page)}><span>${esc(c.titel)}</span><span>${c.page}</span></a>`).join('')}</div>`).join('')}
   <div><h3>Anhang</h3><a ${go(TOTAL - 2)}><span>Laufende und geschlossene Lehrgänge</span><span>${TOTAL - 2}</span></a><a ${go(TOTAL - 1)}><span>Kontakt & Links</span><span>${TOTAL - 1}</span></a></div>
   </div>`, '<span>Seminarheft</span><b>Inhalt</b>');

  // 4/5 Terminübersicht
  const chrono = [...order].sort((a, b) => String(a.beginn).localeCompare(String(b.beginn)));
  const half = Math.ceil(chrono.length / 2);
  function ovTable(list) {
    let lm = '', rows = '';
    list.forEach(c => {
      const x = pd(c.beginn), key = x.y + x.m;
      if (key !== lm) { rows += `<tr><td class="m" colspan="3">${MON[+x.m - 1] || ''} ${x.y || ''}</td></tr>`; lm = key; }
      rows += `<tr><td>${range(c.beginn, c.ende)}</td><td><a ${go(c.page)}>${esc(c.titel)}</a><span class="n">${esc(c.nr)} · ${esc(c.ort)}${c.meldeschluss_list ? ' · Meldeschluss ' + de(c.meldeschluss_list) : ''}</span></td><td style="text-align:right;font-weight:700"><a ${go(c.page)}>${c.page}</a></td></tr>`;
    });
    return `<table class="ov"><thead><tr><th>Datum</th><th>Lehrgang</th><th style="text-align:right">S.</th></tr></thead><tbody>${rows}</tbody></table>`;
  }
  page(4, '', `<h2 class="t">Terminübersicht</h2>${ovTable(chrono.slice(0, half))}`, '<span>Übersicht</span><b>Termine</b>');
  page(5, '', `<div style="height:.2em"></div>${ovTable(chrono.slice(half))}`, '<span>Übersicht</span><b>Termine</b>');

  // Lehrgänge: je eine Doppelseite
  function kursSeiten(c, vorLinks) {
    const inhalt = c.inhalt || [];
    const intro = inhalt.length && inhalt[0].length > 90 ? inhalt[0] : '';
    const inh = intro ? inhalt.slice(1) : inhalt;
    const voraus = sec('Voraussetzungen' + (c.voraussetzungen_bis ? ` <small style="text-transform:none;letter-spacing:0;font-family:var(--f-body)">(einzureichen bis ${esc(c.voraussetzungen_bis)})</small>` : ''), ul(c.voraussetzungen));
    const left = `<h2 class="t">${esc(c.titel)}</h2>
     <div class="when">${range(c.beginn, c.ende)}</div><div class="where">${esc(c.ort)}</div>
     <span class="pill ${stClass(c.status)}">${esc(stText(c.status))}</span>
     ${sec('Zielgruppe', ul(c.zielgruppe))}
     ${sec('Inhalt', (intro ? `<p>${esc(intro)}</p>` : '') + ul(inh, inh.length > 6))}
     ${sec('Ziel', c.ziele ? `<p>${esc(c.ziele)}</p>` : '')}
     ${vorLinks ? voraus : ''}
     ${c.sonstiges ? `<div class="note">${esc(c.sonstiges)}</div>` : ''}`;
    const people = [c.leitung && ['Leitung', c.leitung], c.referierende && ['Referierende', c.referierende],
      (c.tn_min || c.tn_max) && ['Plätze', [c.tn_min && ('min. ' + c.tn_min), c.tn_max && ('max. ' + c.tn_max)].filter(Boolean).join(' · ')],
      (c.meldeschluss || c.meldeschluss_list) && ['Meldeschluss', c.meldeschluss || de(c.meldeschluss_list)],
      c.verpflegung && ['Verpflegung', c.verpflegung], c.unterbringung && ['Unterkunft', c.unterbringung]].filter(Boolean);
    const voll = /ausgebucht/.test(c.status);
    const qrHead = c.qr_direct ? 'Jetzt anmelden' : (voll ? 'Lehrgang ausgebucht' : 'Anmeldung folgt');
    const qrText = c.qr_direct ? (/Warteliste/.test(c.status) ? 'Der Code führt zur Anmeldung – derzeit auf die Warteliste.' : 'Der Code führt direkt zur Online-Anmeldung.')
      : (voll ? 'Der Code führt zur Lehrgangsseite – dort siehst du, ob wieder Plätze frei werden.'
        : `${esc(stText(c.status))}. Der Code führt zur Lehrgangsseite mit dem Anmeldeknopf.`);
    const right = `${vorLinks ? '' : voraus}
     ${sec('Termine', (c.termine || []).length ? `<ol class="term">${c.termine.map((t, i) => `<li><span>${i + 1}</span>${esc(t)}</li>`).join('')}</ol>` : '')}
     ${sec('Veranstaltungsort', c.veranstaltungsort ? `<p>${esc(c.veranstaltungsort)}</p>` : '')}
     <dl class="facts">${people.map(([k, v]) => `<dt>${k}</dt><dd>${esc(v)}</dd>`).join('')}</dl>
     ${sec('Gebühren', ul(c.gebuehren))}
     ${sec('Mitbringen', ul(c.mitbringen, c.mitbringen && c.mitbringen.length > 3))}
     <div class="aktionen nur-web">
      ${c.qr_direct
        ? `<a class="anmelden" href="${esc(c.qr_url)}" target="_blank" rel="noopener">Jetzt anmelden</a>`
        : `<a class="anmelden spaeter" href="${esc(c.detail_url)}" target="_blank" rel="noopener">${voll ? 'Ausgebucht – zur Lehrgangsseite' : esc(stText(c.status))}</a>`}
      <a class="kurs-pdf" href="${M.web}kurs/${c.id}.pdf">Als PDF ↓</a>
     </div>
     <div class="qr">${qr(c.qr_url)}<div><strong>${qrHead}</strong><p>${qrText}</p><a href="${esc(c.qr_url)}" target="_blank" rel="noopener">${c.qr_direct ? 'Zur Anmeldung' : 'Zur Lehrgangsseite'} ›</a></div></div>`;
    return [[left, `<span>${esc(c.cat)}</span><b>Nr. ${esc(c.nr)}</b>`],
      [right, `<span>${esc(c.titel.length > 38 ? c.titel.slice(0, 36) + '…' : c.titel)}</span><b>Anmeldung</b>`]];
  }
  // Messen, wie stark eine Seite verkleinert werden müsste (1 = passt ohne Verkleinerung)
  const messbox = document.createElement('div');
  messbox.className = 'measure'; messbox.style.cssText = 'position:absolute;left:-9999px;top:0;visibility:hidden';
  function bedarf(inner, run) {
    messbox.innerHTML = `<div class="pg"><div class="box"><div class="pc"><div class="run">${run}</div>${inner}</div></div></div>`;
    const pc = messbox.querySelector('.pc'); let k = 1;
    while (pc.scrollHeight > pc.clientHeight + 1 && k > 0.62) { k -= 0.03; pc.style.setProperty('--k', k.toFixed(2)); }
    return k;
  }
  function kurseSetzen() {
    document.body.appendChild(messbox);
    order.forEach(c => {
      let [l, r] = kursSeiten(c, false);
      if (c.voraussetzungen && c.voraussetzungen.length) {
        const kA = Math.min(bedarf(...l), bedarf(...r));
        if (kA < 0.85) {
          const [l2, r2] = kursSeiten(c, true);
          if (Math.min(bedarf(...l2), bedarf(...r2)) > kA) { l = l2; r = r2; }
        }
      }
      page(c.page, '', l[0], l[1]);
      page(c.page + 1, '', r[0], r[1]);
    });
    messbox.remove();
  }

  // Anhang
  page(TOTAL - 2, '', `<h2 class="t">Laufend oder geschlossen</h2>
   <p>Für diese Lehrgänge ist der Meldeschluss erreicht. Sie sind der Vollständigkeit halber aufgeführt; Details stehen online.</p>
   ${closed.length ? `<table class="ov"><thead><tr><th>Datum</th><th>Lehrgang</th></tr></thead><tbody>
   ${closed.map(c => `<tr><td>${range(c.beginn, c.ende)}</td><td><a href="${esc(c.detail_url)}" target="_blank" rel="noopener">${esc(c.titel)}</a><span class="n">${esc(c.nr)} · ${esc(c.ort)}</span></td></tr>`).join('')}
   </tbody></table>` : '<p><i>Derzeit keine.</i></p>'}`, '<span>Anhang</span><b>Geschlossen</b>');

  const K = M.kontakt || {};
  page(TOTAL - 1, '', `<h2 class="t">Kontakt & Links</h2>
   <div class="sec"><h3>${esc(K.name || 'Kontakt')}</h3><p>${K.email ? `E-Mail: <b>${esc(K.email)}</b><br>` : ''}${K.telefon ? `Telefon: <b>${esc(K.telefon)}</b>` : ''}</p></div>
   <div class="mini">
    <figure>${qr(URL_UEBERSICHT)}<figcaption>Alle Lehrgänge online</figcaption></figure>
    <figure>${qr(URL_ABO)}<figcaption>Mail-Abo für neue Lehrgänge</figcaption></figure>
    <figure>${qr(URL_BED)}<figcaption>Teilnahmebedingungen</figcaption></figure>
   </div>
   <div class="note">Zusammengestellt aus den Ausschreibungen auf baden.dlrg.de, Stand ${esc(M.stand)}. Plätze, Fristen und Status ändern sich laufend – maßgeblich ist immer die Online-Ausschreibung.</div>`, '<span>Anhang</span><b>Kontakt</b>');

  page(TOTAL, 'back', `${BB}<div class="top"><div class="big">Alle Lehrgänge,<br>immer aktuell.</div>
   <div class="qrb">${qr(URL_UEBERSICHT)}</div>
   <p><b>${esc(URL_UEBERSICHT.replace(/^https?:\/\//, '').replace(/\/$/, ''))}</b><br>Mail-Abo für neue Lehrgänge und alle Ausschreibungen online.</p></div>
   <div class="wm"><img src="${A}wortmarke-weiss.svg" alt=""></div>`);

  // Sprungliste in der Werkzeugleiste
  const sel = document.getElementById('sprung');
  if (sel) {
    sel.innerHTML = '<option value="">Lehrgang wählen …</option>' + D.cats.map(([cat]) =>
      `<optgroup label="${esc(cat)}">${order.filter(c => c.cat === cat).map(c => `<option value="${c.page}">${esc(c.titel)} · ${range(c.beginn, c.ende)}</option>`).join('')}</optgroup>`).join('');
  }

  function einpassen(book) {
    book.classList.add('measure');
    book.querySelectorAll('.pg:not(.cover):not(.back) .pc').forEach(pc => {
      let k = 1;
      while (pc.scrollHeight > pc.clientHeight + 1 && k > 0.62) { k -= 0.03; pc.style.setProperty('--k', k.toFixed(2)); }
    });
    book.classList.remove('measure');
  }

  function init() {
    kurseSetzen();
    pages.sort((x, y) => x.n - y.n);
    const book = document.getElementById('book');
    if (!order.length && MODUS === 'blaettern') {
      book.innerHTML = '<p class="leer">Derzeit sind keine Lehrgänge freigegeben.</p>';
      return;
    }
    let liste = pages;
    if (MODUS === 'druck-kurs') {
      const c = order.find(x => x.id === NUR_KURS);
      liste = c ? pages.filter(pg => pg.n === c.page || pg.n === c.page + 1) : [];
    }
    book.innerHTML = liste.map(pg => pg.html).join('');
    einpassen(book);

    if (MODUS !== 'blaettern') {
      document.body.classList.add('druck');
      document.body.dataset.fertig = '1';
      return;
    }

    const els = [...book.querySelectorAll('.pg')];
    const pgl = document.getElementById('pgl');
    let pf = null, cur = 0;
    const label = i => {
      const n = i + 1;
      if (window.innerWidth < 760 || !pf || n === 1 || n === els.length) return n === 1 ? 'Titel' : `Seite ${n} von ${els.length}`;
      const a = n % 2 === 0 ? n : n - 1; return `Seite ${a}–${a + 1} von ${els.length}`;
    };
    const setHash = i => { try { history.replaceState(null, '', i ? '#seite-' + (i + 1) : location.pathname + location.search); } catch (e) { } };
    const startIdx = () => { const m = /^#seite-(\d+)$/.exec(location.hash); return m ? Math.min(els.length, Math.max(1, +m[1])) - 1 : 0; };

    if (window.St && St.PageFlip) {
      pf = new St.PageFlip(book, {
        width: 420, height: 594, size: 'stretch', minWidth: 260, maxWidth: 620, minHeight: 368, maxHeight: 877,
        showCover: true, usePortrait: true, autoSize: true, maxShadowOpacity: .35, mobileScrollSupport: false, flippingTime: 650,
        startPage: startIdx(), disableFlipByClick: true
      });
      pf.loadFromHTML(els);
      pf.on('flip', e => { cur = e.data; pgl.textContent = label(cur); setHash(cur); });
      cur = pf.getCurrentPageIndex(); pgl.textContent = label(cur);
    } else {
      document.body.classList.add('noflip'); cur = startIdx();
    }
    const show = i => {
      i = Math.max(0, Math.min(els.length - 1, i));
      if (pf) pf.flip(i); else { els.forEach((e, j) => e.classList.toggle('on', j === i)); cur = i; pgl.textContent = label(i); setHash(i); }
    };
    if (!pf) show(cur);
    document.getElementById('prev').onclick = () => pf ? pf.flipPrev() : show(cur - 1);
    document.getElementById('next').onclick = () => pf ? pf.flipNext() : show(cur + 1);
    document.getElementById('toToc').onclick = () => show(2);
    if (sel) sel.onchange = () => { if (sel.value) show(+sel.value - 1); sel.value = ''; };
    document.addEventListener('click', e => { const a = e.target.closest('[data-go]'); if (!a) return; e.preventDefault(); show(+a.dataset.go - 1); }, true);
    document.addEventListener('keydown', e => {
      if (e.target.closest('select,input,textarea')) return;
      if (e.key === 'ArrowRight') pf ? pf.flipNext() : show(cur + 1); else if (e.key === 'ArrowLeft') pf ? pf.flipPrev() : show(cur - 1);
    });
    window.addEventListener('hashchange', () => show(startIdx()));
    window.addEventListener('resize', () => { pgl.textContent = label(cur); });
  }
  let done = false; const once = () => { if (!done) { done = true; init(); } };
  (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(once); setTimeout(once, 2500);
})();
