<?php
require_once __DIR__ . '/../inc/admin-funktioner.php';
kraev_login();

/* ---------- Én sidefil til redigering ---------- */
if (!empty($_GET['fil'])) {
    $navn = basename($_GET['fil']);                 // aldrig stier udefra
    $sti  = ROD_STI . 'sider/' . $navn;
    if (!preg_match('/\.html$/i', $navn) || !is_file($sti)) {
        http_response_code(404);
        exit('Filen findes ikke.');
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $navn . '"');
    header('Content-Length: ' . filesize($sti));
    readfile($sti);
    exit;
}

/* ---------- Zip ---------- */
if (!empty($_GET['alt']) || !empty($_GET['data'])) {
    if (!class_exists('ZipArchive')) {
        saet_besked('Webhotellet har ikke ZipArchive slået til. Hent filerne med FTP i stedet.', 'fejl');
        videre_til('kopi');
    }

    $kun_data = !empty($_GET['data']);
    $filnavn  = ($kun_data ? 'data-' : 'hjemmeside-') . date('Y-m-d') . '.zip';
    $midlertidig = sys_get_temp_dir() . '/' . uniqid('hsr', true) . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($midlertidig, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        saet_besked('Kopien kunne ikke laves. Prøv igen om lidt.', 'fejl');
        videre_til('kopi');
    }

    $mapper = $kun_data ? ['data'] : ['data', 'sider', 'billeder', 'assets', 'inc'];
    foreach ($mapper as $mappe) {
        $rod = ROD_STI . $mappe;
        if (!is_dir($rod)) continue;
        $filer = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rod, FilesystemIterator::SKIP_DOTS));
        foreach ($filer as $f) {
            if (!$f->isFile()) continue;
            if (str_ends_with($f->getFilename(), '.tmp')) continue;
            if ($f->getFilename() === 'brugere.php') continue;      // aldrig med i en kopi
            $zip->addFile($f->getPathname(), $mappe . '/' . substr($f->getPathname(), strlen($rod) + 1));
        }
    }
    if (!$kun_data) {
        foreach (['index.php', 'arrangementer.php', 'nyheder.php', 'menu.php', 'LAESMIG.md'] as $f) {
            if (is_file(ROD_STI . $f)) $zip->addFile(ROD_STI . $f, $f);
        }
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filnavn . '"');
    header('Content-Length: ' . filesize($midlertidig));
    readfile($midlertidig);
    @unlink($midlertidig);
    exit;
}

http_response_code(400);
exit('Der er ikke angivet hvad der skal hentes.');
