<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Moscow');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function text_length(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function clean_line(string $value): string {
    $value = trim($value);
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return trim($value);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'message' => 'Метод не поддерживается.']);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !in_array($origin, ['https://tulrecept.ru', 'https://www.tulrecept.ru'], true)) {
    respond(403, ['ok' => false, 'message' => 'Запрос отклонён.']);
}

// Honeypot: боты часто заполняют скрытое поле.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    respond(200, ['ok' => true]);
}

$name = clean_line((string)($_POST['name'] ?? ''));
$phone = clean_line((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$companyCity = clean_line((string)($_POST['company_city'] ?? ''));
$consentGiven = (string)($_POST['personal_data_consent'] ?? '') === '1';
$consentVersion = (string)($_POST['consent_version'] ?? '');
$policyVersion = (string)($_POST['privacy_policy_version'] ?? '');
$formStarted = (int)($_POST['form_started'] ?? 0);

$errors = [];

if (text_length($name) < 2 || text_length($name) > 80) {
    $errors[] = 'Укажите имя.';
}
if (text_length($phone) < 5 || text_length($phone) > 40 || !preg_match('/^[0-9+()\-\s]+$/u', $phone)) {
    $errors[] = 'Проверьте номер телефона.';
}
if (text_length($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'Проверьте адрес электронной почты.';
}
if (text_length($companyCity) > 200) {
    $errors[] = 'Слишком длинное значение компании и города.';
}
if (!$consentGiven) {
    $errors[] = 'Необходимо согласие на обработку персональных данных.';
}
if ($consentVersion !== '2026-10-28' || $policyVersion !== '2026-10-28') {
    $errors[] = 'Обновите страницу и повторите отправку.';
}
if ($formStarted <= 0 || $formStarted > time() + 60) {
    $errors[] = 'Обновите страницу и повторите отправку.';
}

if ($errors !== []) {
    respond(422, ['ok' => false, 'message' => $errors[0]]);
}

$requestId = bin2hex(random_bytes(8));
$submittedAt = date(DATE_ATOM);

$record = [
    'request_id' => $requestId,
    'submitted_at' => $submittedAt,
    'name' => $name,
    'phone' => $phone,
    'email' => $email,
    'company_city' => $companyCity,
    'consent_given' => true,
    'consent_version' => $consentVersion,
    'privacy_policy_version' => $policyVersion,
];

$storageDir = '/home/p1073030/data/tulrecept-partner-leads';
if (!is_dir($storageDir) && !mkdir($storageDir, 0700, true) && !is_dir($storageDir)) {
    error_log('Partner form: cannot create storage directory');
    respond(500, ['ok' => false, 'message' => 'Не удалось сохранить заявку.']);
}

$storageFile = $storageDir . '/requests-' . date('Y-m-d') . '.jsonl';
$line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (file_put_contents($storageFile, $line, FILE_APPEND | LOCK_EX) === false) {
    error_log('Partner form: cannot write request ' . $requestId);
    respond(500, ['ok' => false, 'message' => 'Не удалось сохранить заявку.']);
}
@chmod($storageFile, 0600);

// Удаляем суточные журналы старше одного года при каждой новой заявке.
$cutoff = (new DateTimeImmutable('today'))->modify('-1 year')->format('Y-m-d');
foreach (glob($storageDir . '/requests-*.jsonl') ?: [] as $file) {
    if (preg_match('/requests-(\d{4}-\d{2}-\d{2})\.jsonl$/', $file, $m) && $m[1] < $cutoff) {
        @unlink($file);
    }
}

$to = 'tul-recept@mail.ru';
$from = 'site@tulrecept.ru';
$subjectText = 'Новая заявка на сотрудничество — tulrecept.ru';
$subject = '=?UTF-8?B?' . base64_encode($subjectText) . '?=';

$body = "Новая заявка с сайта tulrecept.ru\n\n"
      . "Номер заявки: {$requestId}\n"
      . "Дата и время: {$submittedAt}\n"
      . "Имя: {$name}\n"
      . "Телефон: {$phone}\n"
      . "E-mail: {$email}\n"
      . "Компания и город: " . ($companyCity !== '' ? $companyCity : '—') . "\n\n"
      . "Согласие на обработку ПД: да\n"
      . "Версия согласия: {$consentVersion}\n"
      . "Версия политики: {$policyVersion}\n";

$headers = [
    'From: Тульские Рецепты <' . $from . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
];

$mailSent = false;
if (function_exists('mail')) {
    $mailSent = @mail($to, $subject, $body, implode("\r\n", $headers), '-f' . $from);
}
if (!$mailSent) {
    error_log('Partner form: email notification failed for request ' . $requestId);
}

respond(201, ['ok' => true, 'request_id' => $requestId]);
