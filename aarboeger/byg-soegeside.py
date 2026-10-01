#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
byg-soegeside.py
Laeser aarboger.json og skriver en selvstaendig HTML-soegeside med
data bagt ind i filen. Ingen server, ingen eksterne filer.

    python3 byg-soegeside.py
    python3 byg-soegeside.py --ind aarboger.json --ud aarboger.html
"""

import argparse
import json
import re

PRAEFIKS = "https://tidsskrift.dk/historiskaarbogforroskildeamt/article/view/"
ARKIV = "https://tidsskrift.dk/historiskaarbogforroskildeamt/issue/archive"
SOEG = "https://tidsskrift.dk/historiskaarbogforroskildeamt/search/search"


def kort(url):
    """Skaer den faelles del af adressen vaek - den saettes paa igen i browseren."""
    return url[len(PRAEFIKS):] if url.startswith(PRAEFIKS) else url


def pak(artikler):
    ud = []
    for a in artikler:
        ud.append({
            "t": a["titel"],
            "f": a["forfattere"],
            "y": a["aar"],
            "n": a["nummer"],
            "m": a.get("tema", ""),
            "u": kort(a["url"]),
            "p": kort(a["pdf"]),
            "x": 1 if a["formalia"] else 0,
        })
    return ud


SKABELON = r"""<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Søg i årbøgerne · Historisk Samfund for Roskilde Amt</title>
<meta name="description" content="Søg i indholdet af Historisk Årbog for Roskilde Amt 1910-2024. Artiklerne åbnes hos tidsskrift.dk.">
<style>
  :root{
    --blaek:#17262E;
    --daemp:#546A76;
    --brand:#385261;
    --brand-mork:#253C49;
    --papir:#EEF1F2;
    --kort:#FFFFFF;
    --linje:#CED8DC;
    --marker:#FFE7A3;
    --maks:62rem;
  }
  *{box-sizing:border-box}
  html{-webkit-text-size-adjust:100%}
  body{
    margin:0; background:var(--papir); color:var(--blaek);
    font:1.0625rem/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
  }
  .ramme{max-width:var(--maks); margin:0 auto; padding:0 1.25rem}

  header{background:var(--brand); color:#fff; padding:2rem 0 1.75rem}
  h1{
    font:600 2rem/1.2 Georgia,"Iowan Old Style","Times New Roman",serif;
    margin:0 0 .4rem;
  }
  .undertitel{margin:0; color:#C9D8E0; max-width:44rem}
  .noegletal{margin:1.1rem 0 0; color:#C9D8E0; font-size:.95rem}

  .soegefelt{position:relative; margin:-1.6rem 0 0}
  .soegefelt input{
    width:100%; padding:1rem 1.1rem; font-size:1.25rem; font-family:inherit;
    color:var(--blaek); background:var(--kort);
    border:2px solid var(--brand-mork); border-radius:4px;
    box-shadow:0 2px 6px rgba(23,38,46,.14);
  }
  .soegefelt input:focus{outline:3px solid #8FB3C6; outline-offset:2px}

  /* Dækningsstribe: ét felt pr. år 1910-2024, højden viser antal artikler.
     Tomme år står som en tynd streg, så hullerne er til at se. */
  .daekning{margin:1.5rem 0 .25rem}
  .daekning h2{font-size:1rem; font-weight:600; margin:0 0 .5rem; color:var(--daemp)}
  .stribe{display:flex; align-items:flex-end; gap:1px; height:54px}
  .aar{
    flex:1 1 0; min-width:0; background:var(--linje); border:0; padding:0;
    border-radius:1px 1px 0 0; cursor:pointer; position:relative;
  }
  .aar[data-har="1"]{background:#7B99A8}
  .aar[aria-pressed="true"]{background:#B4541F}
  .aar:hover[data-har="1"]{background:var(--brand)}
  .aar:focus-visible{outline:2px solid var(--brand-mork); outline-offset:1px}
  .aarskala{
    display:flex; justify-content:space-between;
    font-size:.8rem; color:var(--daemp); margin-top:.3rem;
  }

  .styring{
    display:flex; flex-wrap:wrap; gap:1rem 1.5rem; align-items:center;
    padding:1rem 0 .75rem; border-bottom:1px solid var(--linje);
  }
  .styring label{font-size:.95rem}
  select{font:inherit; font-size:.95rem; padding:.3rem .4rem; border:1px solid var(--linje); border-radius:3px; background:var(--kort)}
  input[type=checkbox]{width:1.1rem; height:1.1rem; vertical-align:-2px; margin-right:.35rem}
  .nulstil{
    font:inherit; font-size:.95rem; background:none; border:0; padding:.2rem 0;
    color:var(--brand); text-decoration:underline; cursor:pointer;
  }

  .status{padding:1rem 0 .25rem; color:var(--daemp)}
  .ogsaa{margin:0 0 .5rem; font-size:.95rem; color:var(--daemp)}
  .ogsaa a{color:var(--brand)}
  .status strong{color:var(--blaek)}

  ol.traef{list-style:none; margin:0; padding:0}
  ol.traef li{padding:1.1rem 0; border-bottom:1px solid var(--linje)}
  .titel{
    font:600 1.2rem/1.3 Georgia,"Iowan Old Style","Times New Roman",serif;
    margin:0 0 .25rem;
  }
  .titel a{color:var(--brand-mork); text-decoration:none}
  .titel a:hover, .titel a:focus{text-decoration:underline}
  .linje2{margin:0; color:var(--daemp); font-size:.98rem}
  .aarstal{color:var(--blaek); font-weight:600}
  .tema{font-style:italic}
  .pdf{
    display:inline-block; margin-top:.5rem; font-size:.95rem;
    color:var(--brand); text-decoration:none;
    border:1px solid var(--linje); border-radius:3px; padding:.25rem .6rem; background:var(--kort);
  }
  .pdf:hover,.pdf:focus{border-color:var(--brand); text-decoration:underline}
  mark{background:var(--marker); color:inherit; padding:0 .1em}

  .tomt{background:var(--kort); border:1px solid var(--linje); padding:1.5rem; margin:1.5rem 0}
  .tomt p{margin:0 0 .75rem}
  .tomt p:last-child{margin:0}
  .videre{
    display:inline-block; background:var(--brand); color:#fff; text-decoration:none;
    padding:.6rem 1rem; border-radius:3px; font-size:1rem;
  }
  .videre:hover,.videre:focus{background:var(--brand-mork)}

  .flere{margin:1.5rem 0; text-align:center}
  .flere button{
    font:inherit; padding:.7rem 1.4rem; background:var(--kort);
    border:1px solid var(--brand); color:var(--brand); border-radius:3px; cursor:pointer;
  }
  .flere button:hover{background:var(--brand); color:#fff}

  footer{margin:2.5rem 0 0; padding:1.5rem 0 3rem; border-top:1px solid var(--linje); color:var(--daemp); font-size:.95rem}
  footer a{color:var(--brand)}
  footer p{margin:0 0 .6rem; max-width:44rem}

  a:focus-visible, button:focus-visible{outline:3px solid #8FB3C6; outline-offset:2px}

  @media (max-width:40rem){
    h1{font-size:1.6rem}
    .soegefelt input{font-size:1.1rem}
    .stribe{height:42px}
    .styring{gap:.75rem 1rem}
  }
  @media print{
    header{background:#fff; color:#000}
    .soegefelt,.daekning,.styring,.flere,.pdf{display:none}
  }
</style>
</head>
<body>

<header>
  <div class="ramme">
    <h1>Søg i årbøgerne</h1>
    <p class="undertitel">Historisk Årbog for Roskilde Amt er udkommet siden 1910. Her kan du søge i titler, forfattere og temaer og gå direkte til artiklen hos tidsskrift.dk, hvor den ligger frit tilgængelig.</p>
    <p class="noegletal" id="noegletal"></p>
  </div>
</header>

<main class="ramme">
  <div class="soegefelt">
    <label class="visuelt-skjult" for="q">Søgeord</label>
    <input id="q" type="search" autocomplete="off" spellcheck="false"
           placeholder="Søg på titel, forfatter eller tema &ndash; fx Maglekilde, kloster, Verwohlt">
  </div>

  <section class="daekning" aria-labelledby="daekning-titel">
    <h2 id="daekning-titel">Vælg en årgang</h2>
    <div class="stribe" id="stribe" role="group" aria-label="Årgange"></div>
    <div class="aarskala"><span id="foerste"></span><span id="sidste"></span></div>
  </section>

  <div class="styring">
    <label for="sorter">Sortér:
      <select id="sorter">
        <option value="ny">Nyeste først</option>
        <option value="gammel">Ældste først</option>
        <option value="titel">Titel A&ndash;Å</option>
        <option value="forfatter">Forfatter A&ndash;Å</option>
      </select>
    </label>
    <label><input type="checkbox" id="formalia">Vis også forord, indhold og noter</label>
    <button class="nulstil" id="nulstil" hidden>Ryd søgning og filtre</button>
  </div>

  <p class="status" id="status"></p>
  <p class="ogsaa" id="ogsaa" hidden>Søgningen dækker titler, forfattere og temaer. <a id="fuldtekst2" href="__SOEG__" target="_blank" rel="noopener">Søg efter ordet i selve teksten hos tidsskrift.dk</a></p>
  <ol class="traef" id="traef"></ol>
  <div class="flere" id="flere" hidden><button type="button">Vis flere</button></div>

  <div class="tomt" id="tomt" hidden>
    <p id="tomt-tekst"></p>
    <p>Søgningen her dækker titler, forfattere og temaer. Ordet kan godt stå inde i en artikel uden at være med i titlen.</p>
    <p><a class="videre" id="fuldtekst" href="__SOEG__" target="_blank" rel="noopener">Søg i selve teksten hos tidsskrift.dk</a></p>
  </div>
</main>

<footer class="ramme">
  <p>Artiklerne ligger hos <a href="__ARKIV__" target="_blank" rel="noopener">tidsskrift.dk</a>, Det Kgl. Biblioteks tidsskriftsplatform, og åbnes der. Denne side er kun en indgang.</p>
  <p id="daekning-note"></p>
  <p>Oversigten er hentet __DATO__ og omfatter __ANTAL__ poster.</p>
</footer>

<script id="data" type="application/json">__DATA__</script>
<script>
(function(){
  "use strict";
  var PRAEFIKS = "__PRAEFIKS__";
  var SOEG = "__SOEG__";
  var poster = JSON.parse(document.getElementById("data").textContent);
  var SIDE = 50;

  // Dansk-venlig normalisering: æ ø å og accenter foldes væk,
  // så "Solleröd", "Søllerød" og "sollerod" giver samme resultat.
  // foldMap giver baade den foldede tekst og en tabel over, hvor hvert
  // foldet tegn kom fra. Tabellen er noedvendig, fordi ae fylder to
  // pladser hvor ae fyldte én - uden den sidder gule markeringer forskudt.
  function foldMap(t){
    var s = "", map = [], i, k, c, r;
    t = t || "";
    for (i = 0; i < t.length; i++){
      c = t[i].toLowerCase();
      r = c === "\u00e6" ? "ae" : c === "\u00f8" ? "o" : c === "\u00e5" ? "a"
          : c.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
      for (k = 0; k < r.length; k++){ s += r[k]; map.push(i); }
    }
    return { s: s, map: map };
  }
  function fold(t){ return foldMap(t).s; }
  poster.forEach(function(p){
    p._s = fold(p.t + " " + p.f.join(" ") + " " + p.m);
  });

  var q = document.getElementById("q"),
      stribe = document.getElementById("stribe"),
      sorter = document.getElementById("sorter"),
      visFormalia = document.getElementById("formalia"),
      nulstil = document.getElementById("nulstil"),
      status = document.getElementById("status"),
      liste = document.getElementById("traef"),
      flere = document.getElementById("flere"),
      tomt = document.getElementById("tomt"),
      ogsaa = document.getElementById("ogsaa"),
      fuldtekst2 = document.getElementById("fuldtekst2"),
      tomtTekst = document.getElementById("tomt-tekst"),
      fuldtekst = document.getElementById("fuldtekst");

  var valgtAar = null, vist = SIDE;

  // --- nøgletal og dækningsstribe ---
  var pr = {};
  poster.forEach(function(p){ pr[p.y] = (pr[p.y]||0) + 1; });
  var aarstal = Object.keys(pr).map(Number).sort(function(a,b){return a-b;});
  var foerste = aarstal[0], sidste = aarstal[aarstal.length-1];
  var maks = Math.max.apply(null, aarstal.map(function(y){return pr[y];}));
  var rigtige = poster.filter(function(p){ return !p.x; }).length;

  document.getElementById("noegletal").textContent =
    rigtige + " artikler fra " + aarstal.length + " årgange mellem " + foerste + " og " + sidste + ".";
  document.getElementById("foerste").textContent = foerste;
  document.getElementById("sidste").textContent = sidste;

  var huller = [];
  for (var y = foerste; y <= sidste; y++) if (!pr[y]) huller.push(y);
  document.getElementById("daekning-note").textContent = huller.length
    ? "Der mangler " + huller.length + " årgange i oversigten. De årbøger findes muligvis hos tidsskrift.dk som ét samlet bind uden indholdsfortegnelse på artikelniveau."
    : "";

  var frag = document.createDocumentFragment();
  for (var y2 = foerste; y2 <= sidste; y2++){
    var n = pr[y2] || 0;
    var b = document.createElement("button");
    b.type = "button";
    b.className = "aar";
    b.dataset.aar = y2;
    b.dataset.har = n ? "1" : "0";
    b.setAttribute("aria-pressed","false");
    b.style.height = n ? Math.max(14, Math.round(n / maks * 54)) + "px" : "3px";
    b.title = n ? y2 + ": " + n + (n === 1 ? " post" : " poster") : y2 + ": ingen poster";
    b.setAttribute("aria-label", b.title);
    if (!n) b.disabled = true;
    frag.appendChild(b);
  }
  stribe.appendChild(frag);

  stribe.addEventListener("click", function(e){
    var b = e.target.closest(".aar");
    if (!b || b.disabled) return;
    var aar = Number(b.dataset.aar);
    valgtAar = (valgtAar === aar) ? null : aar;
    Array.prototype.forEach.call(stribe.children, function(el){
      el.setAttribute("aria-pressed", Number(el.dataset.aar) === valgtAar ? "true" : "false");
    });
    vist = SIDE;
    tegn();
  });

  // --- søgning ---
  function filtrer(){
    var ord = ordene();
    var ud = poster.filter(function(p){
      if (!visFormalia.checked && p.x) return false;
      if (valgtAar !== null && p.y !== valgtAar) return false;
      for (var i = 0; i < ord.length; i++) if (!ord[i].test(p._s)) return false;
      return true;
    });
    var s = sorter.value;
    ud.sort(function(a,b){
      if (s === "titel") return a.t.localeCompare(b.t,"da");
      if (s === "forfatter") return (a.f[0]||"").localeCompare(b.f[0]||"","da");
      return s === "gammel" ? a.y - b.y || a.t.localeCompare(b.t,"da")
                            : b.y - a.y || a.t.localeCompare(b.t,"da");
    });
    return ud;
  }

  // Et soegeord skal ramme begyndelsen af et ord, ellers finder "kilde"
  // ogsaa "Roskilde". "kilde" rammer stadig "Kilder" og "kildekraft".
  function ordene(){
    return fold(q.value).split(/\s+/).filter(Boolean).map(function(o){
      return new RegExp("(^|[^a-z0-9])(" + o.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + ")", "g");
    });
  }

  function fremhaev(tekst, ord){
    if (!ord.length) return document.createTextNode(tekst);
    var fm = foldMap(tekst), n = fm.s, brug = [], m;
    function tilbage(i, slut){
      if (i >= fm.map.length) return tekst.length;
      return slut ? fm.map[i - 1] + 1 : fm.map[i];
    }
    ord.forEach(function(re){
      re.lastIndex = 0;
      while ((m = re.exec(n)) !== null){
        var fra = m.index + m[1].length;
        brug.push([tilbage(fra, false), tilbage(fra + m[2].length, true)]);
        if (re.lastIndex === m.index) re.lastIndex++;
      }
    });
    if (!brug.length) return document.createTextNode(tekst);
    brug.sort(function(a, b){ return a[0] - b[0]; });
    var ud = document.createDocumentFragment(), pos = 0;
    brug.forEach(function(r){
      if (r[0] < pos) return;
      ud.appendChild(document.createTextNode(tekst.slice(pos, r[0])));
      var mk = document.createElement("mark");
      mk.textContent = tekst.slice(r[0], r[1]);
      ud.appendChild(mk);
      pos = r[1];
    });
    ud.appendChild(document.createTextNode(tekst.slice(pos)));
    return ud;
  }

  function tegn(){
    var traf = filtrer();
    var ord = ordene();
    var aktiv = q.value.trim() || valgtAar !== null || visFormalia.checked;
    nulstil.hidden = !aktiv;

    liste.textContent = "";
    var del = traf.slice(0, vist);
    var frag = document.createDocumentFragment();

    del.forEach(function(p){
      var li = document.createElement("li");

      var h = document.createElement("p");
      h.className = "titel";
      var a = document.createElement("a");
      a.href = PRAEFIKS + p.u;
      a.target = "_blank"; a.rel = "noopener";
      a.appendChild(fremhaev(p.t, ord));
      h.appendChild(a);
      li.appendChild(h);

      var l2 = document.createElement("p");
      l2.className = "linje2";
      if (p.f.length){
        l2.appendChild(fremhaev(p.f.join(", "), ord));
        l2.appendChild(document.createTextNode(", "));
      }
      var sp = document.createElement("span");
      sp.className = "aarstal"; sp.textContent = "årbog " + p.y;
      l2.appendChild(sp);
      if (p.m){
        l2.appendChild(document.createTextNode(" · "));
        var t = document.createElement("span");
        t.className = "tema";
        t.appendChild(fremhaev(p.m, ord));
        l2.appendChild(t);
      }
      li.appendChild(l2);

      if (p.p){
        var pdf = document.createElement("a");
        pdf.className = "pdf";
        pdf.href = PRAEFIKS + p.p;
        pdf.target = "_blank"; pdf.rel = "noopener";
        pdf.textContent = "Åbn som PDF";
        pdf.setAttribute("aria-label", "Åbn " + p.t + " som PDF");
        li.appendChild(pdf);
      }
      frag.appendChild(li);
    });
    liste.appendChild(frag);

    var findes = traf.length;
    if (findes){
      status.innerHTML = "";
      var st = document.createElement("strong");
      st.textContent = findes + (findes === 1 ? " artikel" : " artikler");
      status.appendChild(st);
      if (valgtAar !== null) status.appendChild(document.createTextNode(" fra årbog " + valgtAar));
      if (del.length < findes) status.appendChild(document.createTextNode(" · viser de første " + del.length));
      tomt.hidden = true;
      if (q.value.trim()){
        fuldtekst2.href = SOEG + "?query=" + encodeURIComponent(q.value.trim());
        ogsaa.hidden = false;
      } else {
        ogsaa.hidden = true;
      }
    } else {
      ogsaa.hidden = true;
      status.textContent = "";
      tomtTekst.textContent = q.value.trim()
        ? "Ingen artikeltitler indeholder \u201c" + q.value.trim() + "\u201d"
          + (valgtAar !== null ? " i årbog " + valgtAar + "." : ".")
        : "Der er ingen poster med de valgte filtre.";
      fuldtekst.href = SOEG + "?query=" + encodeURIComponent(q.value.trim());
      tomt.hidden = false;
    }
    flere.hidden = del.length >= findes;
  }

  function nulstilVist(){ vist = SIDE; tegn(); }

  q.addEventListener("input", nulstilVist);
  sorter.addEventListener("change", nulstilVist);
  visFormalia.addEventListener("change", nulstilVist);
  flere.querySelector("button").addEventListener("click", function(){
    vist += SIDE; tegn();
    liste.children[Math.max(0, vist - SIDE)] &&
      liste.children[Math.max(0, vist - SIDE)].querySelector("a").focus();
  });
  nulstil.addEventListener("click", function(){
    q.value = ""; valgtAar = null; visFormalia.checked = false;
    Array.prototype.forEach.call(stribe.children, function(el){ el.setAttribute("aria-pressed","false"); });
    nulstilVist(); q.focus();
  });

  tegn();
})();
</script>
</body>
</html>
"""


def byg(ind, ud):
    with open(ind, encoding="utf-8") as f:
        d = json.load(f)

    data = json.dumps(pak(d["artikler"]), ensure_ascii=False, separators=(",", ":"))
    # </script> maa aldrig optraede raat inde i et script-element
    data = data.replace("</", "<\\/")

    html = (SKABELON
            .replace("__DATA__", data)
            .replace("__PRAEFIKS__", PRAEFIKS)
            .replace("__ARKIV__", ARKIV)
            .replace("__SOEG__", SOEG)
            .replace("__DATO__", d.get("hoestet", ""))
            .replace("__ANTAL__", str(len(d["artikler"]))))

    # lille hjaelpeklasse der kun bruges ét sted
    html = html.replace('class="visuelt-skjult"',
                        'style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)"')

    with open(ud, "w", encoding="utf-8") as f:
        f.write(html)
    return len(html)


def main():
    p = argparse.ArgumentParser(description="Byg soegeside ud fra aarboger.json")
    p.add_argument("--ind", default="aarboger.json")
    p.add_argument("--ud", default="aarboger.html")
    a = p.parse_args()
    n = byg(a.ind, a.ud)
    print(f"Skrevet {a.ud} ({n/1024:.0f} kB)")


if __name__ == "__main__":
    main()
