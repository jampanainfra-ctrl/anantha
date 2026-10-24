<?php
require_once __DIR__.'/_bootstrap.php';
api_require_admin();api_require_db();
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
$stmt=db()->prepare('SELECT aadhaar_image FROM event_bookings WHERE id=?');$stmt->execute([(int)($_GET['booking_id'] ?? 0)]);
$path=(string)$stmt->fetchColumn();$root=realpath(__DIR__.'/../uploads/clients');$file=$path!==''?realpath(__DIR__.'/../'.$path):false;
if(!$root||!$file||!str_starts_with(str_replace('\\','/',$file),str_replace('\\','/',$root).'/')||!is_file($file))json_response(['ok'=>false,'error'=>'Document not found.'],404);
$info=@getimagesize($file);$mime=$info['mime'] ?? '';
if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))json_response(['ok'=>false,'error'=>'Document is not a supported image.'],415);
header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header('Content-Disposition: inline; filename="document.'.image_type_to_extension($info[2],false).'"');readfile($file);
