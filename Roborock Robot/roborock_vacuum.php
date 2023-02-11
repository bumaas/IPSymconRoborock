<?php

class Consumable extends stdClass
{
    //these properties are used as ident
    public const MAINBRUSH     = 'main_brush'; //Hauptbürste
    public const SIDEBRUSH     = 'side_brush'; //Seitenbürste
    public const FILTER        = 'filter';  //(Staub-)Filter
    public const FILTERELEMENT = 'filter_element'; //???
    public const SENSOR        = 'sensor'; //Sensoren
    public const STRAINER      = 'strainer'; //(Wasser?)Sieb
    public const DUSTCOLLECTOR = 'dust_collector'; //Staubsammler?
    public const CLEANINGBRUSH = 'cleaning_brush'; //Reinigungsbürste

    public static function GetName(string $consumable): string
    {
        //the caption of the properties
        return strtr($consumable, [
            self::MAINBRUSH     => 'Main Brush',
            self::SIDEBRUSH     => 'Side Brush',
            self::FILTER        => 'Filter',
            self::FILTERELEMENT => 'Filter Element',
            self::SENSOR        => 'Sensor',
            self::STRAINER      => 'Strainer',
            self::DUSTCOLLECTOR => 'Dust Collector',
            self::CLEANINGBRUSH => 'Cleaning Brush'
        ]);
    }

    public static function GetMaxWorkTime(string $consumable): int
    {
        switch ($consumable) {
            case self::MAINBRUSH:
                return 300;
            case self::SIDEBRUSH:
                return 200;
            case self::FILTER:
                return 150;
            case self::FILTERELEMENT:
                return 999;
            case self::SENSOR:
                return 30; //Sensoren
            case self::STRAINER:
                return 999;
            case self::DUSTCOLLECTOR:
                return 999; //Staubbeutel?
            case self::CLEANINGBRUSH:
                return 999; //Reinigungsbürste
        }
        trigger_error('Unexpected consumable: ' . $consumable, E_USER_WARNING);
        return 0;
    }
}

class Fanpower extends stdClass
{
    public const GENTLE   = 'Gentle';
    public const SILENT   = 'Silent';
    public const STANDARD = 'Standard';
    public const TURBO    = 'Turbo';
    public const MAXIMUM  = 'Max.';

    public const V1 = [
        self::SILENT   => 38,
        self::STANDARD => 60,
    ];

    public const V2 = [
        self::GENTLE   => 105, //schonend
        self::SILENT   => 101, //leise
        self::STANDARD => 102, //normal
        self::TURBO    => 103, //Turbo
        self::MAXIMUM  => 104, //Max.
    ];

}
class roborock_vacuum
{
    public const CONSUMABLES = [
        Consumable::MAINBRUSH => 'mainbrush',
        Consumable::SIDEBRUSH => 'sidebrush',
        Consumable::FILTER    => 'filter',
        Consumable::SENSOR    => 'sensors'
    ];

    public const DEVICELIST = [
        'roborock.vacuum'     => 'Roborock Generic Vacuum Cleaner',
        'roborock.vacuum.s5'  => 'Roborock S5',
        'roborock.vacuum.s5e' => 'Roborock S5 Max',
        'roborock.vacuum.a10' => 'Roborock S6 MaxV',
        'roborock.vacuum.a15' => 'Roborock S7',
        'roborock.vacuum.a27' => 'Roborock S7 MaxV',
    ];

    public const FANPOWER = Fanpower::V2;

    public function GetName(string $classname): string
    {
        return self::DEVICELIST[str_replace('_', '.', $classname)];
    }

    public function GetModelType(string $classname): string
    {
        $arr = explode('_', $classname);
        if (isset($arr[2])){
            return $arr[2];
        }
        return 'generic';
    }
}
class roborock_vacuum_s5 extends roborock_vacuum
{
    public const CONSUMABLES = [
        Consumable::MAINBRUSH => 'main_brush_work_time',
        Consumable::SIDEBRUSH => 'side_brush_work_time',
        Consumable::FILTER    => 'filter_work_time',
        Consumable::SENSOR    => 'sensor_dirty_time'
    ];

}

class roborock_vacuum_s5e extends roborock_vacuum
{
    public const CONSUMABLES = [
        Consumable::SIDEBRUSH     => 'side_brush_work_time',
        Consumable::FILTER        => 'filter_work_time',
        Consumable::FILTERELEMENT => 'filter_element_work_time',
        Consumable::SENSOR        => 'sensor_dirty_time'
    ];

}

class roborock_vacuum_a10 extends roborock_vacuum //S6 MaxV
{
    public const CONSUMABLES = [
        Consumable::MAINBRUSH     => 'main_brush_work_time',
        Consumable::SIDEBRUSH     => 'side_brush_work_time',
        Consumable::FILTER        => 'filter_work_time',
        Consumable::FILTERELEMENT => 'filter_element_work_time',
        Consumable::SENSOR        => 'sensor_dirty_time'
    ];

    public const FANPOWER = Fanpower::V2;

}

class roborock_vacuum_a15 extends roborock_vacuum //S7
{
    public const CONSUMABLES = [
        Consumable::MAINBRUSH     => 'main_brush_work_time',
        Consumable::SIDEBRUSH     => 'side_brush_work_time',
        Consumable::FILTER        => 'filter_work_time',
        Consumable::FILTERELEMENT => 'filter_element_work_time',
        Consumable::SENSOR        => 'sensor_dirty_time',
        Consumable::DUSTCOLLECTOR => 'dust_collection_work_times'
    ];

    public const FANPOWER = [
        Fanpower::SILENT   => 101, //leise
        Fanpower::STANDARD => 102, //normal
        Fanpower::TURBO    => 103, //Turbo
        Fanpower::MAXIMUM  => 104, //Max.
    ];
}

class roborock_vacuum_a27 extends roborock_vacuum
{
    //S7 MaxV
    public const CONSUMABLES = [
        Consumable::MAINBRUSH     => 'main_brush_work_time',
        Consumable::SIDEBRUSH     => 'side_brush_work_time',
        Consumable::FILTER        => 'filter_work_time',
        Consumable::FILTERELEMENT => 'filter_element_work_time',
        Consumable::SENSOR        => 'sensor_dirty_time',
        Consumable::STRAINER      => 'strainer_work_times',
        Consumable::DUSTCOLLECTOR => 'dust_collection_work_times',
        Consumable::CLEANINGBRUSH => 'cleaning_brush_work_times'
    ];

}