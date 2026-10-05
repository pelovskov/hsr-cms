# Søgeside over årbøgerne

To små Python-scripts, der henter indholdsfortegnelserne fra Historisk Årbog
for Roskilde Amt hos tidsskrift.dk og bygger dem om til én selvstændig
HTML-søgeside, som kan lægges på foreningens hjemmeside.

Brugeren søger på titel, forfatter eller tema og klikker direkte videre til
artiklen hos tidsskrift.dk. Ingen filer bliver kopieret — siden er en indgang,
ikke en kopi.

```
hoest-aarboger.py   →  aarboger.json + aarboger.csv
byg-soegeside.py    →  aarboger.html
```

---

## Forudsætninger

Python 3.6 eller nyere. Det er alt. Ingen pakker skal installeres, scripts'ene
bruger kun Pythons standardbibliotek.

Tjek med:

```
python3 --version
```

Får du en fejl i stedet for et versionsnummer, installeres Python med
`xcode-select --install` i Terminal.

---

## Sådan gør du

**1. Læg de to scripts i en mappe**

F.eks. `~/Dokumenter/Aarboger`. De to resultatfiler havner samme sted.

**2. Åbn Terminal og stil dig i mappen**

Skriv `cd` efterfulgt af et mellemrum, træk mappen ind i Terminal-vinduet, og
tryk retur. Dette trin er vigtigt: scripts'ene læser og skriver i den mappe, du
står i, ikke i den mappe de selv ligger i.

**3. Hent data fra tidsskrift.dk**

```
python3 hoest-aarboger.py
```

Tager et par minutter. Undervejs skrives der, hvor mange poster hver portion
indeholder, og til sidst en opsummering:

```
Skrevet: aarboger.json og aarboger.csv
  633 poster i alt, heraf 501 rigtige artikler
  aargange fra 1910 til 2024 (50 aar)
  1 poster uden direkte PDF-link
```

Ser tallene rimelige ud, gik det godt.

**4. Byg søgesiden**

```
python3 byg-soegeside.py
```

Skriver `aarboger.html` på omkring 120 kB.

**5. Læg `aarboger.html` på hjemmesiden**

Filen er selvstændig. Der skal ikke følge billeder, stylesheets eller JSON med
— alt ligger inde i den ene fil. Den virker også fra en USB-nøgle eller ved
blot at dobbeltklikke den lokalt.

---

## De tre resultatfiler

| Fil | Hvad den er til |
|---|---|
| `aarboger.html` | Den færdige søgeside. Den eneste, der skal på nettet. |
| `aarboger.json` | Datagrundlaget. Trin 4 læser den, så behold den i mappen. |
| `aarboger.csv` | Til dig selv. Åbnes i Numbers eller Excel — hurtigste måde at kigge data efter i sømmene. |

---

## Når der kommer en ny årbog

Kør de to kommandoer igen og læg den nye `aarboger.html` op:

```
python3 hoest-aarboger.py
python3 byg-soegeside.py
```

Der er ingen tilstand at holde styr på. Hver kørsel henter alt forfra og
overskriver resultatfilerne.

---

## Når noget driller

**`CERTIFICATE_VERIFY_FAILED` i trin 3**

Python fra python.org kommer med sin egen tomme certifikatsamling, som skal
fyldes én gang pr. maskine:

```
open "/Applications/Python 3.14/Install Certificates.command"
```

Ret versionsnummeret, hvis mappen hedder noget andet. Find det rigtige navn
med `ls /Applications | grep -i python`.

**macOS vil ikke åbne filen**

Gælder kun, hvis man dobbeltklikker en `.command`-fil hentet fra nettet. Klik
*Åbn* i advarslen. Kører du scripts'ene fra Terminal som beskrevet ovenfor,
opstår problemet slet ikke.

**Ingen netværksforbindelse på den maskine, der har Python**

`curl` findes på enhver Mac og bruger systemets egne certifikater. Hent
XML-svarene i en tom mappe:

```bash
sti=historiskaarbogforroskildeamt
grund="https://tidsskrift.dk/$sti/oai"
url="$grund?verb=ListRecords&metadataPrefix=oai_dc"
i=1
while [ -n "$url" ]; do
  curl -s -o "oai-$i.xml" "$url"
  token=$(sed -n 's/.*<resumptionToken[^>]*>\([^<]*\)<\/resumptionToken>.*/\1/p' "oai-$i.xml")
  if [ -n "$token" ]; then
    url="$grund?verb=ListRecords&resumptionToken=$token"; i=$((i+1)); sleep 1
  else
    url=""
  fi
done
```

Flyt XML-filerne over på den anden maskine og kør:

```
python3 hoest-aarboger.py --fra-fil . --ud aarboger
```

---

## Hvad scripts'ene gør indvendigt

Hvis nogen skal vedligeholde det her, er det værd at kende fire valg, der
ikke er indlysende.

**Årstallet tages fra nummeret, ikke fra datoen.** OAI-udtrækket indeholder et
`dc:date`-felt, men for de gamle årbøger er det datoen, hvor bindet blev lagt
online. Årbogen fra 1918 har `dc:date` 2020-12-30. Årstallet læses derfor ud
af nummerbetegnelsen `Nr. 1 (1918)`.

**Temaer trækkes ud af nummerstrengen.** Fjorten af årgangene er temabøger, og
titlen står klemt sammen med nummeretiketten i samme felt. Regulæret `JOURNAL`
øverst i `hoest-aarboger.py` fjerner tidsskriftets eget navn, så kun det
egentlige tema bliver tilbage — f.eks. *Kildernes By*, *Roskilde Kloster*,
*Roskilde Amt under besættelsen 1940-45*.

**Forord, indhold og noter markeres som formalia.** Omkring 130 af posterne er
titelblade, indholdsfortegnelser, litteraturlister og generalforsamlings-
referater. De bliver ikke smidt væk, men markeret, så søgesiden kan skjule dem
som standard. Listen over ord ligger i `FORMALIA` øverst i scriptet og kan
udvides.

**Søgeord skal ramme begyndelsen af et ord.** Ellers giver en søgning på
"kilde" 278 træf, fordi ordet står inde i "Roskilde". Med reglen giver det 24.
Æ, ø og å foldes, så "sollerod" også finder Søllerød, og der følger en
indekstabel med, så den gule markering sidder rigtigt i titler med æ.

**Stavefejl giver et forslag.** Giver en søgning ingen træf, finder siden det
nærmeste ord fra titler, forfattere og temaer og viser en "Mente du:"-knap —
fx *Maglkilde* → *Maglekilde*, *Verwolht* → *Verwohlt*. Ord under fire bogstaver
skal staves rigtigt, ord på 4-7 bogstaver må have én fejl, længere ord to.
Ombyttede nabobogstaver tæller som én fejl. Er der træf, men kun i andre
årgange eller blandt formalia, tilbydes en knap til at udvide søgningen.
Logikken er den samme som i Søgeside · Bygger.

---

## Dækningen er ikke komplet

Pr. september 2026 ligger kun omkring 50 af årgangene mellem 1910 og 2024 hos
tidsskrift.dk på artikelniveau. 1910-1928 er fuldt dækket, hvorefter der
mangler næsten alt frem til 1986 på nær 1948.

De manglende årbøger findes formentlig hos tidsskrift.dk som ét samlet bind
uden indtastet indholdsfortegnelse. OAI-udtrækket viser kun poster på
artikelniveau, så de dukker ikke op. Bliver indholdsfortegnelserne indtastet,
kommer de med helt af sig selv ved næste kørsel.

Søgesiden viser hullerne ærligt: dækningsstriben øverst har ét felt pr. år, og
de år, der mangler, står som en tynd streg.

*Register 1910-2010*, som ligger scannet i arkivet, indeholder i forvejen
indholdsfortegnelserne for de fleste af de manglende årgange. Det er et godt
udgangspunkt, hvis arbejdet skal sættes i gang.

---

## Andre tidsskrifter

Begge scripts tager imod en anden sti. Stien er den del af adressen, der står
efter `tidsskrift.dk/`:

```
python3 hoest-aarboger.py --sti etandettidsskrift --ud etandet
python3 byg-soegeside.py --ind etandet.json --ud etandet.html
```

To ting skal så justeres i hånden: `JOURNAL`-regulæret i `hoest-aarboger.py`,
der fjerner tidsskriftets navn fra temafeltet, og `PRAEFIKS`, `ARKIV` og
`SOEG` øverst i `byg-soegeside.py`, der peger på det rigtige tidsskrift.

Et par ord om rettigheder: OAI-PMH er lavet til at blive høstet, og det er
tilladt at linke til frit tilgængeligt materiale. Det er noget andet at hente
PDF'erne ned og lægge dem på sin egen server — det kræver en aftale. Scriptet
sender en genkendelig User-Agent og holder en pause mellem kaldene. Behold
begge dele. Og sig til foreningen bag et tidsskrift, at du gør det. De plejer
at blive glade.

---

## Kilde

Artiklerne ligger hos [tidsskrift.dk](https://tidsskrift.dk/historiskaarbogforroskildeamt),
Det Kgl. Biblioteks tidsskriftsplatform, drevet på Open Journal Systems.
Metadata hentes via platformens OAI-PMH-grænseflade.
