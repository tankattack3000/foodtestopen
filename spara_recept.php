<?php

// Gör även PHP-fel till JSON så att JavaScript kan läsa dem
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);

function svar($success, $message, $extra = []) {
    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

// ---------------------------------------------------------
// FILER
// ---------------------------------------------------------

$jsonFile = __DIR__ . '/recept.json';
$backupDir = __DIR__ . '/backup';

// ---------------------------------------------------------
// TESTA ATT recept.json FINNS
// ---------------------------------------------------------

if (!file_exists($jsonFile)) {
    svar(false, 'recept.json kunde inte hittas.');
}

// ---------------------------------------------------------
// LÄS INSKICKAD DATA
// ---------------------------------------------------------

$input = file_get_contents('php://input');

if ($input === false || trim($input) === '') {
    svar(false, 'Ingen data skickades.');
}

$data = json_decode($input, true);

if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    svar(false, 'Den data som skickades är inte giltig JSON: ' . json_last_error_msg());
}

// ---------------------------------------------------------
// LÄS BEFINTLIG recept.json
// ---------------------------------------------------------

$json = file_get_contents($jsonFile);

if ($json === false) {
    svar(false, 'Kunde inte läsa recept.json.');
}

$recipes = json_decode($json, true);

if ($recipes === null && json_last_error() !== JSON_ERROR_NONE) {
    svar(false, 'recept.json innehåller ogiltig JSON: ' . json_last_error_msg());
}

// ---------------------------------------------------------
// SPARA RECEPT
// ---------------------------------------------------------

if (!isset($data['newName']) || !isset($data['recipe'])) {
    svar(false, 'newName eller recipe saknas i den skickade datan.');
}

$oldName = isset($data['oldName']) ? trim($data['oldName']) : '';
$newName = trim($data['newName']);
$recipe = $data['recipe'];

if ($newName === '') {
    svar(false, 'Receptnamnet får inte vara tomt.');
}

// Om receptet bytt namn
if ($oldName !== '' && $oldName !== $newName) {

    if (isset($recipes[$oldName])) {
        unset($recipes[$oldName]);
    }
}

// Lägg in/uppdatera receptet
$recipes[$newName] = $recipe;

// ---------------------------------------------------------
// SKAPA BACKUP
// ---------------------------------------------------------

if (!is_dir($backupDir)) {

    if (!mkdir($backupDir, 0775, true)) {
        svar(false, 'Kunde inte skapa backup-mappen.');
    }
}

$backupFile = $backupDir . '/recept_' . date('Y-m-d_H-i-s') . '.json';

if (!copy($jsonFile, $backupFile)) {
    svar(false, 'Kunde inte skapa backup. Inget recept sparades.');
}

// ---------------------------------------------------------
// SKAPA NY JSON
// ---------------------------------------------------------

$newJson = json_encode(
    $recipes,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($newJson === false) {
    svar(false, 'Kunde inte skapa JSON: ' . json_last_error_msg());
}

// ---------------------------------------------------------
// SKRIV recept.json
// ---------------------------------------------------------

$result = file_put_contents(
    $jsonFile,
    $newJson . PHP_EOL,
    LOCK_EX
);

if ($result === false) {
    svar(false, 'Kunde inte skriva till recept.json. Kontrollera skrivbehörigheten.');
}

// ---------------------------------------------------------
// KLART
// ---------------------------------------------------------

svar(true, 'Receptet sparades!', [
    'recipe' => $newName
]);

?>