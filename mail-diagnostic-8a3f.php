<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
echo "php_version=" . PHP_VERSION . "\n";
echo "mail_exists=" . (function_exists('mail') ? 'yes' : 'no') . "\n";
echo "sendmail_path=" . (string)ini_get('sendmail_path') . "\n";
echo "SMTP=" . (string)ini_get('SMTP') . "\n";
echo "smtp_port=" . (string)ini_get('smtp_port') . "\n";

$from='site@tulrecept.ru';
$headers=[
  'From: Tulskie Recepty <'.$from.'>',
  'MIME-Version: 1.0',
  'Content-Type: text/plain; charset=UTF-8'
];
$subject='IHC mail diagnostic ' . date('Y-m-d H:i:s');
$body="Одноразовая проверка отправки PHP mail() с tulrecept.ru.\n";

$r1 = function_exists('mail') ? @mail('tul-recept@mail.ru', $subject, $body, implode("\r\n",$headers), '-f'.$from) : false;
echo "mail_to_mailru=" . ($r1 ? 'true' : 'false') . "\n";
$r2 = function_exists('mail') ? @mail('site@tulrecept.ru', $subject, $body, implode("\r\n",$headers), '-f'.$from) : false;
echo "mail_to_local=" . ($r2 ? 'true' : 'false') . "\n";
$err=error_get_last();
if($err){ echo "last_error=" . str_replace(["\r","\n"],' ',(string)$err['message']) . "\n"; }
