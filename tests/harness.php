<?php

declare(strict_types=1);

/*
 * Gemeinsamer Testrahmen: bindet das Modul „Roborock" (Roborock Robot) an den offiziellen
 * Kernel-Stub (symcon/SymconStubs, Submodul tests/stubs, gepinnt). Der Kernel trägt
 * Properties, Attribute, Variablen, Timer und Status; der Harness ersetzt nur die
 * Roborock IO: SendDataToParent() liefert eine fest eingestellte Antwort.
 *
 * Was die echte IO liefert (Roborock IO/module.php, ForwardData → Send):
 *   - Roboter antwortet nicht   → json_encode(false), also der String "false"
 *   - Auftrag in die Warteschlange (nicht immediate) → ''
 *
 * Einbinden mit require_once __DIR__ . '/harness.php'; Instanzen über neueInstanz().
 */

require_once __DIR__ . '/stubs/autoload.php';

// PHP-Warnungen/-Notices des Moduls sollen Tests abbrechen, nicht still durchlaufen.
// E_USER_NOTICE bleibt außen vor (der Stub meldet so einen unbekannten Ident), ebenso
// E_DEPRECATED.
set_error_handler(static function (int $nr, string $text, string $datei, int $zeile): bool {
    if (!(error_reporting() & $nr)) {
        return false; // mit @ unterdrückt — kein Testfehler
    }
    if ($nr & (E_USER_ERROR | E_USER_WARNING | E_WARNING | E_NOTICE)) {
        throw new ErrorException($text, 0, $nr, $datei, $zeile);
    }
    return false;
});

require_once dirname(__DIR__) . '/Roborock Robot/module.php';

final class RoborockHarness extends Roborock
{
    public const MODULE_ID = '{E65614FB-B37A-219A-4876-E5676C948C33}'; // Roborock Robot/module.json

    /** Antwort der nachgebildeten IO auf jede Anfrage; "false" = Roboter antwortet nicht */
    public string $ioAntwort = 'false';
    /** @var array<string, string> Antwort je Methode (z. B. 'miIO.info'), sonst gilt $ioAntwort */
    public array $ioAntworten = [];
    /** @var list<string> Methoden aller an die IO gesendeten Anfragen */
    public array $anfragen = [];
    /** @var list<bool> je Anfrage: sofort gesendet (immediate) oder in die Warteschlange */
    public array $sofort = [];

    public function id(): int
    {
        return $this->InstanceID;
    }

    protected function SendDataToParent(string $Data): string
    {
        $buffer           = json_decode($Data, true, 512, JSON_THROW_ON_ERROR)['Buffer'];
        $methode          = $buffer['method'];
        $this->anfragen[] = $methode;
        $this->sofort[]   = (bool)$buffer['immediate'];
        if (!$buffer['immediate']) {
            return ''; // wie die echte IO: Auftrag in die Warteschlange, Antwort kommt später über ReceiveData
        }
        return $this->ioAntworten[$methode] ?? $this->ioAntwort;
    }

    /** nachgebildete Roborock IO ist aktiv (wie auf der Anlage) */
    public bool $parentAktiv = true;

    protected function HasActiveParent(): bool
    {
        return $this->parentAktiv;
    }

    /** feste Uhrzeit für den Stub (Timer) */
    protected function getTime(): int
    {
        return 1_790_000_000;
    }

    public function tokenSetzen(string $token): void
    {
        $this->WriteAttributeString('token', $token);
    }

    public function timerIntervall(string $name): int
    {
        return $this->GetTimerInterval($name);
    }
}

/**
 * Instanz anlegen und konfigurieren wie über das Formular: IP, Token, ggf. Zugangsdaten,
 * danach ApplyChanges.
 */
function neueInstanz(string $ip = '192.168.178.144', string $token = '0123456789abcdef0123456789abcdef', string $ioAntwort = 'false', array $ioAntworten = []): RoborockHarness
{
    $id = IPS\ObjectManager::registerObject(1 /* Instance */);
    IPS\InstanceManager::createInstance($id, [
        'ModuleID'   => RoborockHarness::MODULE_ID,
        'ModuleName' => 'Roborock',
        'ModuleType' => 3,
        'Class'      => RoborockHarness::class,
    ]);
    /** @var RoborockHarness $m */
    $m            = IPS\InstanceManager::getInstanceInterface($id);
    $m->ioAntwort = $ioAntwort;
    $m->ioAntworten = $ioAntworten;
    IPS_SetProperty($id, 'ip', $ip);
    $m->tokenSetzen($token);
    IPS_ApplyChanges($id);
    return $m;
}

IPS\Kernel::reset(); // einmal je Testlauf; weitere Instanzen entstehen im selben Kernel

$pruefungen = 0;
$fehler     = [];
function pruefe(bool $ok, string $text): void
{
    global $pruefungen, $fehler;
    $pruefungen++;
    if (!$ok) {
        $fehler[] = $text;
    }
    echo($ok ? '  ok   ' : '  FEHL ') . $text . "\n";
}

function ergebnis(): never
{
    global $pruefungen, $fehler;
    echo "\n" . $pruefungen . ' Prüfungen, ' . count($fehler) . " Fehler\n";
    exit($fehler === [] ? 0 : 1);
}

function status(RoborockHarness $m): int
{
    return IPS_GetInstance($m->id())['InstanceStatus'];
}

/** @return list<array{Message: string, Type: int}> Logeinträge der Instanz seit $ab */
function logSeit(RoborockHarness $m, int $ab): array
{
    return array_slice(IPS\LogServer::getLogMessages((string)$m->id()), $ab);
}

function logAnzahl(RoborockHarness $m): int
{
    return count(IPS\LogServer::getLogMessages((string)$m->id()));
}

/** Roborock IO für Tests: nur getTime() für den Stub; anlegen mit neueIO() */
function neueIO(): RoborockIO
{
    require_once dirname(__DIR__) . '/Roborock IO/module.php';
    if (!class_exists('RoborockIOHarness', false)) {
        // RegisterHook: im Stub (bf2950f) als bool deklariert, gibt aber nichts zurück → TypeError
        eval('final class RoborockIOHarness extends RoborockIO { protected function getTime(): int { return 1_790_000_000; } protected function RegisterHook(string $HookPath): bool { return true; } public function id(): int { return $this->InstanceID; } }');
    }
    $id = IPS\ObjectManager::registerObject(1 /* Instance */);
    IPS\InstanceManager::createInstance($id, [
        'ModuleID'   => '{4743ED9C-720B-D5EA-9B0C-0585803284F3}', // Roborock IO/module.json
        'ModuleName' => 'RoborockIO',
        'ModuleType' => 1,
        'Class'      => 'RoborockIOHarness',
    ]);
    return IPS\InstanceManager::getInstanceInterface($id);
}
