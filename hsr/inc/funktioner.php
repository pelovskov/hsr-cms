<?php
/* Fælles funktioner til hjemmesiden.
   Ingen database. Alt indhold hentes fra /data/*.json. */

define('DATA_STI', __DIR__ . '/../data/');

/* ---------- Indlæsning ---------- */

function hent_data($filnavn) {
    static $husket = [];
    if (isset($husket[$filnavn])) return $husket[$filnavn];

    $sti = DATA_STI . $filnavn;
    if (!is_readable($sti)) {
        error_log("Datafil kan ikke læses: $sti");
        return $husket[$filnavn] = [];
    }
    $data = json_decode(file_get_contents($sti), true);
    if (!is_array($data)) {
        error_log("Datafil er ikke gyldig JSON: $sti");
        return $husket[$filnavn] = [];
    }
    return $husket[$filnavn] = $data;
}

function site()          { return hent_data('site.json'); }
function alle_sider()    { $d = hent_data('sider.json');        return $d['sider'] ?? []; }
function menupunkter()   { $d = hent_data('sider.json');        return $d['menu'] ?? []; }
function alle_arr()      { $d = hent_data('arrangementer.json'); return $d['arrangementer'] ?? []; }
function alle_nyheder()  { $d = hent_data('nyheder.json');      return $d['nyheder'] ?? []; }

/* ---------- Udvælgelse ---------- */

function kommende_arr($antal = null) {
    $idag = date('Y-m-d');
    $liste = array_filter(alle_arr(), fn($a) =>
        ($a['synlig'] ?? true) && ($a['dato'] ?? '') >= $idag);
    usort($liste, fn($a, $b) => strcmp($a['dato'], $b['dato']));
    return $antal ? array_slice($liste, 0, $antal) : $liste;
}

function afholdte_arr($antal = null) {
    $idag = date('Y-m-d');
    $liste = array_filter(alle_arr(), fn($a) =>
        ($a['synlig'] ?? true) && ($a['dato'] ?? '') < $idag);
    usort($liste, fn($a, $b) => strcmp($b['dato'], $a['dato']));
    return $antal ? array_slice($liste, 0, $antal) : $liste;
}

function nyheder_sorteret($antal = null) {
    $liste = array_filter(alle_nyheder(), fn($n) => $n['synlig'] ?? true);
    usort($liste, function ($a, $b) {
        $f = ($b['fremhaevet'] ?? false) <=> ($a['fremhaevet'] ?? false);
        return $f !== 0 ? $f : strcmp($b['dato'], $a['dato']);
    });
    return $antal ? array_slice($liste, 0, $antal) : $liste;
}

function arrangement($id, $ogsaa_skjulte = false) {
    foreach (alle_arr() as $a) {
        if (($a['id'] ?? '') === $id && ($ogsaa_skjulte || ($a['synlig'] ?? true))) return $a;
    }
    return null;
}

function nyhed($id, $ogsaa_skjulte = false) {
    foreach (alle_nyheder() as $n) {
        if (($n['id'] ?? '') === $id && ($ogsaa_skjulte || ($n['synlig'] ?? true))) return $n;
    }
    return null;
}

function sider_under($menu_id) {
    $liste = array_filter(alle_sider(), fn($s) =>
        ($s['menu'] ?? '') === $menu_id && ($s['synlig'] ?? true));
    usort($liste, fn($a, $b) => ($a['raekkefoelge'] ?? 0) <=> ($b['raekkefoelge'] ?? 0));
    return $liste;
}

function side_med_id($id) {
    foreach (alle_sider() as $s) {
        if (($s['id'] ?? '') === $id && ($s['synlig'] ?? true)) return $s;
    }
    return null;
}

/* ---------- Visning ---------- */

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

const MAANEDER = [1=>'januar','februar','marts','april','maj','juni',
                  'juli','august','september','oktober','november','december'];

function dansk_dato($iso, $kort = false) {
    $t = strtotime($iso);
    if (!$t) return '';
    $m = MAANEDER[(int)date('n', $t)];
    if ($kort) $m = substr($m, 0, 3) . '.';
    return (int)date('j', $t) . '. ' . $m . ' ' . date('Y', $t);
}

function maaned_kort($iso) {
    $t = strtotime($iso);
    /* Alle danske månedsnavne er rene ASCII-bogstaver, så substr er sikker her. */
    return $t ? strtoupper(substr(MAANEDER[(int)date('n', $t)], 0, 3)) : '';
}

function dag_tal($iso) {
    $t = strtotime($iso);
    return $t ? (int)date('j', $t) : '';
}

/* Viser intet, hvis feltet er tomt eller billedfilen er væk.
   Så giver en slettet fil et roligt layout i stedet for et brudt ikon. */
function billede($sti, $alt = '', $klasse = '') {
    if (!$sti) return '';
    if (!preg_match('#^https?://#', $sti) && !is_file(__DIR__ . '/../' . $sti)) return '';
    return '<img src="' . e($sti) . '" alt="' . e($alt) . '" class="' . e($klasse) . '" loading="lazy">';
}

/* ---------- Markdown (samme delmængde som byggerne) ---------- */

function markdown($tekst) {
    $tekst = str_replace("\r\n", "\n", (string)$tekst);
    $ud = '';
    $liste_aaben = false;

    foreach (explode("\n\n", $tekst) as $blok) {
        $blok = trim($blok);
        if ($blok === '') continue;

        $linjer = explode("\n", $blok);
        $er_liste = true;
        foreach ($linjer as $l) {
            if (!preg_match('/^\s*[-*]\s+/', $l)) { $er_liste = false; break; }
        }

        if ($er_liste) {
            $ud .= "<ul>";
            foreach ($linjer as $l) {
                $ud .= '<li>' . inline_markdown(preg_replace('/^\s*[-*]\s+/', '', $l)) . '</li>';
            }
            $ud .= "</ul>\n";
        } elseif (preg_match('/^(#{1,3})\s+(.*)$/', $linjer[0], $m)) {
            $niveau = strlen($m[1]) + 1;   // # bliver til h2
            $ud .= "<h$niveau>" . inline_markdown($m[2]) . "</h$niveau>\n";
        } elseif (str_starts_with($blok, '>')) {
            $ren = preg_replace('/^>\s?/m', '', $blok);
            $ud .= '<blockquote>' . inline_markdown(nl2br(e_bevar($ren))) . "</blockquote>\n";
        } else {
            $ud .= '<p>' . inline_markdown(nl2br(e_bevar($blok))) . "</p>\n";
        }
    }
    return $ud;
}

/* Escaper først, så inline-mærkerne kan sættes ind bagefter uden at åbne for HTML. */
function e_bevar($s) {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inline_markdown($s) {
    if (!str_contains($s, '<')) $s = e_bevar($s);
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/s', '<em>$1</em>', $s);
    $s = preg_replace_callback('/\[(.+?)\]\((https?:\/\/[^\s)]+|[^\s)]+\.html)\)/',
        fn($m) => '<a href="' . e($m[2]) . '">' . $m[1] . '</a>', $s);
    return $s;
}

/* Link til en sidefil, med en hjem-adresse sendt med.
   Byggerne læser ?hjem= og viser deres egen Hjem-knap med den adresse, så en
   selvstændig HTML-fil kan finde tilbage til det site den er åbnet fra.
   Stien regnes fra filens egen mappe, altså sider/. */
function side_url($fil, $hjem = '../index.php') {
    if (!$fil) return '';
    return $fil . '?hjem=' . rawurlencode($hjem);
}
