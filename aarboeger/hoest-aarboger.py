#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
hoest-aarboger.py
Henter alle artikelposter for et tidsskrift paa tidsskrift.dk via OAI-PMH
og skriver dem til aarboger.json + aarboger.csv.

Bruger kun Pythons standardbibliotek - ingen pip install noedvendig.

Kald:
    python3 hoest-aarboger.py
    python3 hoest-aarboger.py --sti historiskaarbogforroskildeamt --ud aarboger
    python3 hoest-aarboger.py --fra-fil gemt-svar.xml     (test uden netvaerk)
"""

import argparse
import csv
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import date

BASIS = "https://tidsskrift.dk"
STANDARD_STI = "historiskaarbogforroskildeamt"
PAUSE = 1.0          # sekunder mellem kald - vaer hoeflig mod serveren
TIMEOUT = 60

NS = {
    "oai": "http://www.openarchives.org/OAI/2.0/",
    "dc": "http://purl.org/dc/elements/1.1/",
    "oai_dc": "http://www.openarchives.org/OAI/2.0/oai_dc/",
}

# Poster hvis titel starter med et af disse ord er som regel ikke rigtige
# artikler, men forsider, indholdsfortegnelser, love og lignende.
# De bliver ikke smidt vaek - de faar bare markeringen "formalia": true,
# saa soegesiden kan skjule dem som standard.
FORMALIA = (
    "titelblad", "titelbl", "indhold", "kolofon", "forord", "indledning",
    "forfatterne", "forfatterliste", "love,", "love og", "bestyrelse",
    "aarsberetning", "årsberetning", "beretning", "formandens beretning",
    "regnskab", "generalforsamling", "medlemsliste", "register",
    "boganmeldelse", "anmeldelser", "nye boeger", "nye bøger",
    "litteratur", "noter", "kilder og litteratur", "noter og",
    "om forfatterne", "summary", "meddelelser", "nekrolog",
)

ISSN = re.compile(r"^\d{4}-\d{3}[\dxX]$")
AARSTAL = re.compile(r"\((\d{4})\)")
AARSTAL_FORAN = re.compile(r"^(\d{4})\b")


def hent(url):
    """Hent en URL og returner raa bytes."""
    req = urllib.request.Request(
        url,
        headers={
            "User-Agent": "Aarbogshoest/1.0 (lokalhistorisk forening; "
                          "kontakt via foreningens hjemmeside)"
        },
    )
    with urllib.request.urlopen(req, timeout=TIMEOUT) as svar:
        return svar.read()


def tekst(node):
    return " ".join((node.text or "").split()) if node is not None else ""


def vaelg_kilde(kilder):
    """
    dc:source optraeder flere gange: én pr. sprog plus ISSN-numre.
    Vi vil have den danske variant, fx
    'Historisk Årbog for Roskilde Amt; Årg. 1 Nr. 1 (1994); 5-20'
    """
    rigtige = [k for k in kilder if k and not ISSN.match(k.strip())]
    if not rigtige:
        return ""
    # Foretraek den der ligner dansk (Årg./Nr.), ellers den laengste
    for k in rigtige:
        if "Årg." in k or "Nr." in k:
            return k
    return max(rigtige, key=len)


JOURNAL = re.compile(
    r"(?i)("
    r"[åa]a?rbog\s+(?:udgivet\s+af|fra)\s+historisk\s+samfund[^:.]*"
    r"|[åa]a?rbog\s*\d{4}\s*historisk\s+samfund[^:.]*"
    r"|historisk\s+[åa]a?rbog\s+(?:for|fra)\s+roskilde\s+amt"
    r"|fra\s+københavns\s+amt"
    r")\s*\d{0,4}(?:/\d{2,4})?")


def del_kilde(kilde):
    """
    Del 'Tidsskriftnavn; Nr. 1 (2023): Historisk Årbog ... 2023: Kildernes By'
    op i (nummer, tema, sider).

    Nummeret er etiketten 'Nr. 1 (2023)'. Tema er aarbogens egen titel, naar
    den er andet end tidsskriftets navn gentaget - fx 'Kildernes By - Roskilde'
    eller 'Roskilde Amt under besaettelsen 1940-45'. Er der intet tema,
    bliver feltet tomt.
    """
    dele = [d.strip() for d in kilde.split(";") if d.strip()]
    raa = dele[1] if len(dele) >= 2 else ""
    sider = dele[2] if len(dele) >= 3 else ""

    if ":" in raa:
        nummer, rest = raa.split(":", 1)
        tema = JOURNAL.sub("", rest).strip(" .,:;-–/")
        if len(tema) < 4:
            tema = ""
    else:
        nummer, tema = raa, ""

    return nummer.strip(), tema, sider


def find_aar(nummer, dc_dato):
    """
    Aarstallet skal komme fra nummeret, ikke fra dc:date.
    Retrodigitaliserede aarboeger har ofte en dc:date fra det aar,
    de blev lagt online (fx 2020) selv om aarbogen er fra 1918.
    """
    for kandidat in (nummer,):
        m = AARSTAL.search(kandidat or "")
        if m:
            return int(m.group(1))
        m = AARSTAL_FORAN.match((kandidat or "").strip())
        if m:
            return int(m.group(1))
    m = re.match(r"(\d{4})", dc_dato or "")
    return int(m.group(1)) if m else None


def er_formalia(titel):
    t = (titel or "").strip().lower()
    return any(t.startswith(ord_) for ord_ in FORMALIA)


def laes_poster(xml_bytes):
    """Parse ét OAI-svar. Returnerer (liste af artikler, resumptionToken)."""
    import xml.etree.ElementTree as ET

    rod = ET.fromstring(xml_bytes)

    fejl = rod.find("oai:error", NS)
    if fejl is not None:
        kode = fejl.get("code", "ukendt")
        raise RuntimeError(f"OAI-fejl fra serveren: {kode} - {tekst(fejl)}")

    artikler = []
    for post in rod.iter("{http://www.openarchives.org/OAI/2.0/}record"):
        hoved = post.find("oai:header", NS)
        if hoved is not None and hoved.get("status") == "deleted":
            continue
        dc = post.find(".//oai_dc:dc", NS)
        if dc is None:
            continue

        titel = tekst(dc.find("dc:title", NS))
        forfattere = [tekst(n) for n in dc.findall("dc:creator", NS)]
        forfattere = [f for f in forfattere if f]
        resume = tekst(dc.find("dc:description", NS))
        if resume in ("—", "-", titel):
            resume = ""

        kilder = [tekst(n) for n in dc.findall("dc:source", NS)]
        kilde = vaelg_kilde(kilder)
        nummer, tema, sider = del_kilde(kilde)

        dc_dato = tekst(dc.find("dc:date", NS))
        aar = find_aar(nummer, dc_dato)

        url = ""
        for n in dc.findall("dc:identifier", NS):
            v = tekst(n)
            if v.startswith("http"):
                url = v
                break

        pdf = ""
        for n in dc.findall("dc:relation", NS):
            v = tekst(n)
            if v.startswith("http"):
                pdf = v
                break

        oai_id = tekst(hoved.find("oai:identifier", NS)) if hoved is not None else ""

        artikler.append({
            "id": oai_id,
            "titel": titel,
            "forfattere": forfattere,
            "aar": aar,
            "nummer": nummer,
            "tema": tema,
            "sider": sider,
            "resume": resume,
            "url": url,
            "pdf": pdf,
            "formalia": er_formalia(titel),
        })

    token_node = rod.find(".//oai:resumptionToken", NS)
    token = tekst(token_node)
    return artikler, (token or None)


def hoest(sti):
    grund = f"{BASIS}/{sti}/oai"
    url = f"{grund}?verb=ListRecords&metadataPrefix=oai_dc"
    alle = []
    runde = 0

    while url:
        runde += 1
        print(f"  henter portion {runde} ...", flush=True)
        try:
            data = hent(url)
        except urllib.error.HTTPError as e:
            raise SystemExit(f"Serveren svarede {e.code} paa:\n  {url}")
        except urllib.error.URLError as e:
            raise SystemExit(f"Kunne ikke naa serveren: {e.reason}")

        poster, token = laes_poster(data)
        alle.extend(poster)
        print(f"    {len(poster)} poster (i alt {len(alle)})", flush=True)

        if token:
            url = f"{grund}?verb=ListRecords&resumptionToken=" + \
                  urllib.parse.quote(token, safe="")
            time.sleep(PAUSE)
        else:
            url = None

    return alle


def skriv(artikler, sti, grundnavn):
    # sorter nyeste foerst, derefter efter sidetal
    artikler.sort(key=lambda a: (-(a["aar"] or 0), a["sider"] or ""))

    pakke = {
        "kilde": f"{BASIS}/{sti}",
        "hoestet": date.today().isoformat(),
        "antal": len(artikler),
        "artikler": artikler,
    }

    json_fil = f"{grundnavn}.json"
    with open(json_fil, "w", encoding="utf-8") as f:
        json.dump(pakke, f, ensure_ascii=False, indent=1)

    csv_fil = f"{grundnavn}.csv"
    with open(csv_fil, "w", encoding="utf-8-sig", newline="") as f:
        skriver = csv.writer(f, delimiter=";")
        skriver.writerow(["aar", "titel", "forfattere", "nummer", "tema",
                          "sider", "url", "pdf", "formalia"])
        for a in artikler:
            skriver.writerow([
                a["aar"] or "", a["titel"], " | ".join(a["forfattere"]),
                a["nummer"], a["tema"], a["sider"], a["url"], a["pdf"],
                "ja" if a["formalia"] else "",
            ])

    return json_fil, csv_fil


def samle_filer(sti):
    """--fra-fil kan pege paa én XML-fil eller paa en mappe med flere."""
    if os.path.isdir(sti):
        navne = [n for n in os.listdir(sti) if n.lower().endswith(".xml")]
        # naturlig sortering, saa oai-2 kommer foer oai-10
        def noegle(n):
            return [int(d) if d.isdigit() else d.lower()
                    for d in re.split(r"(\d+)", n)]
        return [os.path.join(sti, n) for n in sorted(navne, key=noegle)]
    return [sti] if os.path.isfile(sti) else []


def main():
    p = argparse.ArgumentParser(description="Hoest artikelposter fra tidsskrift.dk")
    p.add_argument("--sti", default=STANDARD_STI,
                   help="tidsskriftets sti i adressen, fx historiskaarbogforroskildeamt")
    p.add_argument("--ud", default="aarboger",
                   help="grundnavn for outputfilerne (uden endelse)")
    p.add_argument("--fra-fil", default=None,
                   help="laes gemte OAI-svar fra en fil ELLER en mappe med xml-filer")
    args = p.parse_args()

    if args.fra_fil:
        stier = samle_filer(args.fra_fil)
        if not stier:
            raise SystemExit(f"Ingen XML-filer fundet i {args.fra_fil}")
        artikler = []
        for sti_fil in stier:
            with open(sti_fil, "rb") as f:
                poster, _ = laes_poster(f.read())
            print(f"  {os.path.basename(sti_fil)}: {len(poster)} poster")
            artikler.extend(poster)
    else:
        print(f"Hoester fra {BASIS}/{args.sti}/oai")
        artikler = hoest(args.sti)

    if not artikler:
        raise SystemExit("Ingen poster fundet. Er stien rigtig?")

    json_fil, csv_fil = skriv(artikler, args.sti, args.ud)

    rigtige = [a for a in artikler if not a["formalia"]]
    aarstal = sorted({a["aar"] for a in artikler if a["aar"]})
    mangler_pdf = [a for a in artikler if not a["pdf"]]

    print()
    print(f"Skrevet: {json_fil} og {csv_fil}")
    print(f"  {len(artikler)} poster i alt, heraf {len(rigtige)} rigtige artikler")
    if aarstal:
        print(f"  aargange fra {aarstal[0]} til {aarstal[-1]} ({len(aarstal)} aar)")
    if mangler_pdf:
        print(f"  {len(mangler_pdf)} poster uden direkte PDF-link")


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        sys.exit("\nAfbrudt.")
