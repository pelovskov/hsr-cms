<?php
/* Funktioner der kun bruges af admin. Indlæses efter inc/funktioner.php. */

require_once __DIR__ . '/funktioner.php';

define('ROD_STI', dirname(__DIR__) . '/');
define('BRUGERFIL', DATA_STI . 'brugere.php');

/* ---------- Session og login ---------- */

function start_session() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function brugere() {
    return is_file(BRUGERFIL) ? (require BRUGERFIL) : [];
}

function har_brugere() {
    return count(brugere()) > 0;
}

function log_ind($navn, $kodeord) {
    $b = brugere();
    $navn = strtolower(trim($navn));
    if (!isset($b[$navn])) {
        password_verify($kodeord, '$2y$12$ugyldighashsomtagersammetidxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
        return false; // samme svartid uanset om brugeren findes
    }
    if (!password_verify($kodeord, $b[$navn]['hash'])) return false;
    session_regenerate_id(true);
    $_SESSION['bruger'] = $navn;
    $_SESSION['navn']   = $b[$navn]['navn'] ?? ucfirst($navn);
    return true;
}

function log_ud() {
    start_session();
    $_SESSION = [];
    session_destroy();
}

function er_logget_ind() {
    start_session();
    return !empty($_SESSION['bruger']) && isset(brugere()[$_SESSION['bruger']]);
}

function kraev_login() {
    if (!er_logget_ind()) { header('Location: login.php'); exit; }
}

function bruger_navn() {
    return $_SESSION['navn'] ?? 'ukendt';
}

/* Opretter den første bruger, hvis der ingen er. */
function opret_foerste_bruger($brugernavn, $fuldt_navn, $kodeord) {
    $brugernavn = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $brugernavn));
    if ($brugernavn === '' || strlen($kodeord) < 10) return false;

    $data = [$brugernavn => [
        'navn' => $fuldt_navn !== '' ? $fuldt_navn : ucfirst($brugernavn),
        'hash' => password_hash($kodeord, PASSWORD_BCRYPT, ['cost' => 12]),
    ]];
    return skriv_brugere($data);
}

function skriv_brugere(array $data) {
    $ud = "<?php\n/* Brugere til admin. Filen er PHP, så indholdet aldrig kan hentes\n"
        . "   ned via browseren, heller ikke uden .htaccess. */\nreturn " . var_export($data, true) . ";\n";
    return skriv_atomisk(BRUGERFIL, $ud, false);
}

/* ---------- CSRF ---------- */

function csrf_vaerdi() {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_felt() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_vaerdi()) . '">';
}

function tjek_csrf() {
    start_session();
    $sendt = $_POST['csrf'] ?? '';
    if (!$sendt || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sendt)) {
        http_response_code(400);
        exit('Sikkerhedstjek fejlede. Gå tilbage, hent siden igen og prøv en gang til.');
    }
}

/* ---------- Skrivning ---------- */

/* Skriver først til en midlertidig fil i samme mappe og omdøber bagefter.
   Omdøbningen er atomisk, så filen aldrig kan stå halvt skrevet, heller ikke
   hvis strømmen går eller to gemmer samtidig. Den forrige udgave lægges i .bak. */
function skriv_atomisk($sti, $indhold, $tag_backup = true) {
    $mappe = dirname($sti);
    if (!is_dir($mappe) || !is_writable($mappe)) {
        error_log("Mappen kan ikke skrives: $mappe");
        return false;
    }
    if ($tag_backup && is_file($sti)) @copy($sti, $sti . '.bak');

    $midlertidig = $sti . '.' . getmypid() . '.tmp';
    if (file_put_contents($midlertidig, $indhold) === false) { @unlink($midlertidig); return false; }
    if (!rename($midlertidig, $sti)) { @unlink($midlertidig); return false; }
    @chmod($sti, 0644);
    return true;
}

function gem_json($filnavn, array $data) {
    $data['opdateret'] = date('c');
    $data['opdateret_af'] = $_SESSION['bruger'] ?? 'system';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { error_log('Kunne ikke lave JSON af ' . $filnavn); return false; }
    return skriv_atomisk(DATA_STI . $filnavn, $json);
}

/* ---------- Log ---------- */

function skriv_log($tekst) {
    $sti = DATA_STI . 'log.json';
    $log = is_file($sti) ? (json_decode(file_get_contents($sti), true) ?: []) : [];
    array_unshift($log, [
        'tid'    => date('c'),
        'bruger' => bruger_navn(),
        'tekst'  => $tekst,
    ]);
    $log = array_slice($log, 0, 30);
    skriv_atomisk($sti, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), false);
}

function hent_log() {
    $sti = DATA_STI . 'log.json';
    return is_file($sti) ? (json_decode(file_get_contents($sti), true) ?: []) : [];
}

/* ---------- Filmodtagelse ---------- */

function slug($tekst, $maks = 40) {
    $fra = ['æ','ø','å','Æ','Ø','Å','ä','ö','ü','é','è','ê','ô'];
    $til = ['ae','oe','aa','ae','oe','aa','ae','oe','ue','e','e','e','o'];
    $t = str_replace($fra, $til, $tekst);
    $t = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $t));
    return substr(trim($t, '-'), 0, $maks) ?: 'uden-navn';
}

/* Modtager et billede. Filtypen afgøres af billedets eget indhold, ikke af navnet. */
function modtag_billede($feltnavn, $undermappe, $basisnavn) {
    if (empty($_FILES[$feltnavn]['tmp_name']) || $_FILES[$feltnavn]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$feltnavn]['error'] !== UPLOAD_ERR_OK) {
        return ['fejl' => 'Billedet blev ikke modtaget. Prøv igen, eller vælg en mindre fil.'];
    }
    $oplysninger = @getimagesize($_FILES[$feltnavn]['tmp_name']);
    if (!$oplysninger) {
        return ['fejl' => 'Filen er ikke et billede. Vælg en jpg-, png- eller webp-fil.'];
    }
    $endelser = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!isset($endelser[$oplysninger[2]])) {
        return ['fejl' => 'Billedformatet kan ikke bruges. Gem det som jpg og prøv igen.'];
    }

    $mappe = ROD_STI . 'billeder/' . $undermappe . '/';
    if (!is_dir($mappe) && !@mkdir($mappe, 0755, true)) {
        return ['fejl' => 'Billedmappen kunne ikke oprettes på serveren.'];
    }
    $navn = slug($basisnavn) . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $endelser[$oplysninger[2]];
    if (!move_uploaded_file($_FILES[$feltnavn]['tmp_name'], $mappe . $navn)) {
        return ['fejl' => 'Billedet kunne ikke gemmes på serveren.'];
    }
    @chmod($mappe . $navn, 0644);
    return ['sti' => 'billeder/' . $undermappe . '/' . $navn];
}

/* Modtager en HTML-fil fra en af byggerne. */
function modtag_side($feltnavn, $oensket_filnavn = null) {
    if (empty($_FILES[$feltnavn]['tmp_name']) || $_FILES[$feltnavn]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$feltnavn]['error'] !== UPLOAD_ERR_OK) {
        return ['fejl' => 'Filen blev ikke modtaget. Den er måske større end webhotellet tillader.'];
    }
    $indhold = file_get_contents($_FILES[$feltnavn]['tmp_name']);
    if (stripos(substr($indhold, 0, 400), '<html') === false && stripos(substr($indhold, 0, 400), '<!doctype') === false) {
        return ['fejl' => 'Filen ligner ikke en HTML-side. Vælg filen du gemte fra Sidebygger.'];
    }

    $navn = $oensket_filnavn ?: slug(pathinfo($_FILES[$feltnavn]['name'], PATHINFO_FILENAME)) . '.html';
    $navn = preg_replace('/[^a-z0-9._-]/i', '', $navn);
    if (!str_ends_with(strtolower($navn), '.html')) $navn .= '.html';

    $mappe = ROD_STI . 'sider/';
    if (!is_dir($mappe) && !@mkdir($mappe, 0755, true)) {
        return ['fejl' => 'Mappen sider/ kunne ikke oprettes.'];
    }
    if (!skriv_atomisk($mappe . $navn, $indhold)) {
        return ['fejl' => 'Filen kunne ikke gemmes. Tjek at mappen sider/ må skrives.'];
    }
    return ['sti' => 'sider/' . $navn, 'titel' => titel_fra_html($indhold)];
}

/* Trækker titel og beskrivelse ud af en Sidebygger-fil, hvis de er der. */
function titel_fra_html($html) {
    $hoved = substr($html, 0, 8000);
    if (preg_match('/<meta\s+name=["\']DC\.title["\']\s+content=["\'](.*?)["\']/i', $hoved, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    if (preg_match('/<title>(.*?)<\/title>/is', $hoved, $m)) {
        return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }
    return '';
}

/* ---------- Beskeder mellem to sidevisninger ---------- */

function saet_besked($tekst, $type = 'ok') {
    start_session();
    $_SESSION['besked'] = ['tekst' => $tekst, 'type' => $type];
}

function tag_besked() {
    start_session();
    $b = $_SESSION['besked'] ?? null;
    unset($_SESSION['besked']);
    return $b;
}

/* Sender brugeren tilbage til en visning efter en gemning (så genindlæsning
   ikke gemmer det samme igen). */
function videre_til($visning = 'arrangementer') {
    header('Location: index.php?vis=' . urlencode($visning));
    exit;
}

/* ---------- Flere brugere ---------- */

/* Opretter eller opdaterer en bruger. Et tomt kodeord lader den nuværende stå. */
function gem_bruger($brugernavn, $fuldt_navn, $kodeord = '') {
    $brugernavn = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $brugernavn));
    if ($brugernavn === '') return 'Brugernavnet må kun indeholde bogstaver og tal.';

    $b = brugere();
    $findes = isset($b[$brugernavn]);

    if (!$findes && strlen($kodeord) < 10) {
        return 'Adgangskoden skal være mindst 10 tegn.';
    }
    if ($kodeord !== '' && strlen($kodeord) < 10) {
        return 'Adgangskoden skal være mindst 10 tegn.';
    }

    $b[$brugernavn] = [
        'navn' => trim($fuldt_navn) !== '' ? trim($fuldt_navn) : ucfirst($brugernavn),
        'hash' => $kodeord !== ''
            ? password_hash($kodeord, PASSWORD_BCRYPT, ['cost' => 12])
            : $b[$brugernavn]['hash'],
    ];
    ksort($b);

    if (!skriv_brugere($b)) return 'Filen data/brugere.php kunne ikke skrives.';
    return true;
}

/* Sletter en bruger. Man kan hverken slette sig selv eller den sidste bruger -
   ellers kan ingen komme ind i admin igen. */
function slet_bruger($brugernavn) {
    $b = brugere();
    if (!isset($b[$brugernavn]))                return 'Brugeren findes ikke.';
    if ($brugernavn === ($_SESSION['bruger'] ?? '')) return 'Du kan ikke slette dig selv.';
    if (count($b) <= 1)                         return 'Der skal være mindst én bruger tilbage.';

    unset($b[$brugernavn]);
    if (!skriv_brugere($b)) return 'Filen data/brugere.php kunne ikke skrives.';
    return true;
}
