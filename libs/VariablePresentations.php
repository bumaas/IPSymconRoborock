<?php

declare(strict_types=1);

namespace libs;

class VariablePresentations
{
    public static function switch(): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
        ];
    }

    public static function webContent(): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT
        ];
    }

    public static function color(int $encoding = 0): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_COLOR,
            'ENCODING'     => $encoding
        ];
    }

    public static function slider(
        int|float $min,
        int|float $max,
        int|float $stepSize,
        string $suffix = '',
        ?int $usageType = null,
        int $digits = 0,
        bool $autoPercentage = true
    ): array {
        $applyPercentage = $autoPercentage && self::shouldApplyPercentage($min, $max, $stepSize, $suffix);
        if ($applyPercentage) {
            $suffix = ' %';
        }

        $presentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
            'MIN'          => $min,
            'MAX'          => $max,
            'STEP_SIZE'    => $stepSize,
            'DIGITS'       => $digits
        ];

        if ($suffix !== '') {
            $presentation['SUFFIX'] = $suffix;
        }
        if ($usageType !== null) {
            $presentation['USAGE_TYPE'] = $usageType;
        }
        if ($applyPercentage) {
            $presentation['PERCENTAGE'] = true;
        }

        return $presentation;
    }

    public static function value(
        int|float $min = 0,
        int|float $max = 0,
        int|float $stepSize = 0,
        string $suffix = '',
        int $digits = 0
    ): array {
        $presentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'MIN'          => $min,
            'MAX'          => $max,
            'STEP_SIZE'    => $stepSize,
            'DIGITS'       => $digits
        ];

        if ($suffix !== '') {
            $presentation['SUFFIX'] = $suffix;
        }

        return $presentation;
    }

    public static function enumeration(array $options): array
    {
        $normalizedOptions = [];
        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }

            $normalizedOptions[] = [
                'Value'      => (int)($option['Value'] ?? 0),
                'Caption'    => (string)($option['Caption'] ?? ''),
                'IconActive' => (bool)($option['IconActive'] ?? false),
                'IconValue'  => (string)($option['IconValue'] ?? ''),
                'Color'      => (int)($option['Color'] ?? -1)
            ];
        }

        if (count($normalizedOptions) === 0) {
            $normalizedOptions[] = [
                'Value'      => 0,
                'Caption'    => '',
                'IconActive' => false,
                'IconValue'  => '',
                'Color'      => -1
            ];
        }

        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'OPTIONS'      => json_encode($normalizedOptions, JSON_THROW_ON_ERROR)
        ];
    }

    /**
     * Read-only Aufzählung: bildet Integer-Werte über die Wertanzeige (INTERVALS) auf Texte ab.
     * Anders als enumeration() (VARIABLE_PRESENTATION_ENUMERATION) benötigt die Wertanzeige
     * keine Variablenaktion und ist damit für read-only Statusvariablen geeignet.
     * Erwartet dieselbe Options-Struktur wie enumeration(): Value, Caption, IconValue, Color.
     */
    public static function valueEnumeration(array $options): array
    {
        $intervals = [];
        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }

            $value = (int)($option['Value'] ?? 0);
            $icon  = (string)($option['IconValue'] ?? '');
            $color = (int)($option['Color'] ?? -1);

            $intervals[] = [
                'IntervalMinValue' => $value,
                'IntervalMaxValue' => $value,
                'ConstantActive'   => true,
                'ConstantValue'    => (string)($option['Caption'] ?? ''),
                'ConversionFactor' => 1,
                'PrefixActive'     => false,
                'PrefixValue'      => '',
                'SuffixActive'     => false,
                'SuffixValue'      => '',
                'DigitsActive'     => false,
                'DigitsValue'      => 0,
                'IconActive'       => $icon !== '',
                'IconValue'        => $icon,
                'ColorActive'      => $color !== -1,
                'Color'            => $color
            ];
        }

        return [
            'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'INTERVALS_ACTIVE' => true,
            'INTERVALS'        => json_encode($intervals, JSON_THROW_ON_ERROR)
        ];
    }

    public static function timeOnly(): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
            'DATE'         => 0,
            'TIME'         => 2
        ];
    }

    private static function shouldApplyPercentage(int|float $min, int|float $max, int|float $stepSize, string $suffix): bool
    {
        if ($suffix !== '') {
            return false;
        }
        if (!is_int($min) || !is_int($max) || !is_int($stepSize)) {
            return false;
        }

        return ($min === 0 && $max === 255) || ($min === 0 && $max === 100);
    }
}
