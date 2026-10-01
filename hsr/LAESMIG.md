# Historisk Samfund for Roskilde Amt · testopsætning

PHP-site uden database. Alt indhold ligger i `data/*.json`.

## Sådan lægges det op

1. Læg mappens indhold op på webhotellet, fx i `/test/`.
2. Åbn `index.php` i browseren. Der skal ikke installeres noget.
3. Gå til `admin/` og opret den første bruger. Skærmbilledet til det
   forsvinder af sig selv, så snart der findes en bruger.

Krav: PHP 8.0 eller nyere. Mapperne `data/`, `sider/` og `billeder/` skal
kunne skrives af PHP (typisk rettighed 755).

## Mapper

    data/        indholdet som JSON. brugere.php oprettes automatisk
    inc/         fælles funktioner, sidehoved, sidefod, admin-funktioner
    admin/       login og redigering
    assets/      style.css til hjemmesiden, admin.css til redigering
    sider/       HTML-filer fra Sidebygger. Indeholder nu kun pladsholdere
    billeder/    fotos. Manglende billeder springes over, layoutet holder

## Hjemmesiden

    index.php            forsiden
    arrangementer.php    hele programmet plus tidligere arrangementer
    nyheder.php          oversigt, og enkelt nyhed med ?id=
    menu.php?m=viden     sider under et menupunkt

## Admin

    admin/login.php      log ind, og opret den første bruger
    admin/index.php      arrangementer, nyheder, sider, sikkerhedskopi
    admin/hent.php       hent en enkelt sidefil eller hele sitet som zip

Sådan gemmes en ændring: der skrives først til en midlertidig fil i samme
mappe, som derefter omdøbes. Omdøbningen er atomisk, så en datafil aldrig kan
stå halvt skrevet. Den forrige udgave lægges ved siden af som `.bak`.

## Sådan rettes en side

1. Klik "Hent til redigering". Filen hentes ned.
2. Åbn den i Sidebygger, ret, og gem.
3. Klik "Erstat fil" og vælg den rettede fil.

Filnavnet beholdes, så alle links bliver ved med at virke.

## Flere brugere

`data/brugere.php` er en almindelig PHP-fil med et navn og en bcrypt-hash
pr. bruger. Nye brugere tilføjes ved at skrive en linje mere i filen.
Hashen laves med:

    php -r 'echo password_hash("koden her", PASSWORD_BCRYPT, ["cost" => 12]);'

## Inden det bliver den rigtige hjemmeside

- `.htaccess` der lukker af for direkte adgang til `data/` og `admin/` udefra.
  Uden den kan `data/*.json` hentes ned af hvem som helst der kender adressen.
  Indholdet er offentligt i forvejen, men det er ikke meningen at det skal ligge frit.
- HTTPS på domænet, så adgangskoder ikke sendes i klartekst.
- Automatisk backup hos webhotellet.
