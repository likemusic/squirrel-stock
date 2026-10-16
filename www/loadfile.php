<?php

require_once 'init.php';

$_REQUEST['id'] = intval($_REQUEST['id'] ?? 0);


$user = \App\System::getUser();
if ($user->user_id == 0) {
    http_response_code(403);
    die;
}
// SquirrelStock: файл отдается тому, кто видит объект, к которому он прикреплен (App\SQS\FileAccess)
$access = \App\SQS\FileAccess::file($_REQUEST['id']);
if ($access == null || !\App\SQS\FileAccess::canView($access, $user)) {
    http_response_code($access == null ? 404 : 403);
    die;
}

$file = \App\Helper::loadFile($_REQUEST['id']);
if ($file == null) {
    die;
}

$pos = strrpos($file['filename'], '.');
if ($pos !== false) {
    //$type = substr($file['filename'], $pos + 1);
}
$size = strlen($file['filedata']??'');
if ($size > 0) {
    if (strlen($file['mime'] ?? '') > 0 && ($_REQUEST['im'] ?? 0) > 0) {
        header('Content-Type: ' . $file['mime']);
    } else {
        header('Content-Type: application/octet-stream');
         // SquirrelStock: имя не латиницей — в filename* (RFC 6266), в filename — запасное латиницей
         $fname = str_replace(['"', "\\", "\r", "\n"], '', (string)($file['filename'] ?? '')) ?: 'file';
         $ascii = preg_replace('/[^\x20-\x7E]/', '_', $fname);
         header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($fname));
        header('Content-Transfer-Encoding: binary');
    }

    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $size);

    
    echo $file['filedata'];
    flush();
}
die;
