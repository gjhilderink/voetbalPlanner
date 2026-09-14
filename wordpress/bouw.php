<?php
/**
 * Zipt de pluginmap. Wordt aangeroepen door bouw.sh als `zip` ontbreekt.
 *
 * Niet met Compress-Archive van Windows: die zet backslashes in de namen, en
 * dan pakt een Linux-server bestanden uit die letterlijk "map\bestand" heten.
 *
 * Gebruik: php bouw.php <bronmap> <doel.zip>
 */

$bron = $argv[1] ?? '';
$doel = $argv[2] ?? '';

if ($bron === '' || $doel === '' || ! is_dir($bron)) {
    fwrite(STDERR, "Gebruik: php bouw.php <bronmap> <doel.zip>\n");
    exit(1);
}

$bron  = rtrim(str_replace('\\', '/', realpath($bron)), '/');
$naam  = basename($bron);
$zip   = new ZipArchive();

if ($zip->open($doel, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Kan $doel niet maken.\n");
    exit(1);
}

$bestanden = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($bron, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($bestanden as $bestand) {
    $pad   = str_replace('\\', '/', $bestand->getPathname());
    $inZip = $naam . '/' . ltrim(substr($pad, strlen($bron)), '/');

    if ($bestand->isDir()) {
        $zip->addEmptyDir($inZip);
    } else {
        $zip->addFile($pad, $inZip);
    }
}

$zip->close();
