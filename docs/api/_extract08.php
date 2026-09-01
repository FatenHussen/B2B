<?php

$path = 'c:\\Users\\FATEN\\Downloads\\Telegram Desktop\\DOC_08_نظام_الصلاحيات_ولوحة_السوبر_أدمن_2.docx';
if (! is_file($path)) {
    // NFC vs NFD filename
    foreach (glob('c:\\Users\\FATEN\\Downloads\\Telegram Desktop\\DOC_08*.docx') as $f) {
        $path = $f;
        break;
    }
}
echo "using $path\n";
$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    fwrite(STDERR, "cannot open\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();
$xml = str_replace(['</w:p>', '</w:tr>'], "\n", $xml);
$text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
$text = preg_replace("/[ \t]+/", ' ', $text);
$text = preg_replace("/\n{3,}/", "\n\n", $text);
file_put_contents(__DIR__.'/doc08.txt', $text);
echo strlen($text)." chars\n";
