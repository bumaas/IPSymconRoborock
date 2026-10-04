<?php

declare(strict_types=1);

/**
 * Maskiert Zugangsdaten in jeder Debug-Ausgabe (MCP-Regeln 10 und 13).
 *
 * Das Debug liest jede KI mit Lesezugriff über den MCP-Server. Werte unter den JSON-Schlüsseln aus
 * SECRET_KEYS und die bekannten Geheimnisse des Moduls (DebugSecrets(), z. B. das Geräte-Token)
 * werden zu "***" plus den letzten vier Zeichen — genug, um zu sehen, welches Token im Spiel ist.
 * Binärausgaben (Format 1, verschlüsselte Pakete) bleiben unverändert.
 */
trait DebugMaskTrait
{
    /** @var list<string> JSON-Schlüssel, deren Wert ein Geheimnis ist */
    private static array $SECRET_KEYS = ['token', 'ssecurity', 'serviceToken', 'passToken', '_sign', 'password'];

    protected function SendDebug(string $Message, string $Data, int $Format): bool
    {
        return parent::SendDebug($Message, $Format === 0 ? $this->MaskSecrets($Data) : $Data, $Format);
    }

    /** @return list<string> bekannte Geheimnisse des Moduls, die auch außerhalb von JSON maskiert werden */
    abstract protected function DebugSecrets(): array;

    private function MaskSecrets(string $data): string
    {
        $data = (string)preg_replace_callback(
            '/"(' . implode('|', array_map(static fn(string $k): string => preg_quote($k, '/'), self::$SECRET_KEYS)) . ')"(\s*:\s*)"((?:[^"\\\\]|\\\\.)*)"/i',
            static fn(array $m): string => '"' . $m[1] . '"' . $m[2] . '"' . self::MaskValue($m[3]) . '"',
            $data
        );
        foreach ($this->DebugSecrets() as $secret) {
            if (strlen($secret) >= 8) {
                $data = str_replace($secret, self::MaskValue($secret), $data);
            }
        }
        return $data;
    }

    private static function MaskValue(string $value): string
    {
        return strlen($value) > 8 ? '***' . substr($value, -4) : '***';
    }
}
