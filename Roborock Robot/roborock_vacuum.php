<?php

class Consumable extends stdClass
{
    //these properties are used as ident
    public const MAINBRUSH     = 'main_brush'; //Hauptbürste
    public const SIDEBRUSH     = 'side_brush'; //Seitenbürste
    public const FILTER        = 'filter';  //(Staub-)Filter
    public const FILTERELEMENT = 'filter_element'; //???
    public const SENSOR        = 'sensor'; //Sensoren
    public const STRAINER      = 'strainer'; //(Wasser?) Sieb
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

    public static function GetMaxWorkTime(string $consumable): array
    {
        //max work times in hours
        switch ($consumable) {
            case self::MAINBRUSH:
                return ['value' => 300, 'unit' => 'hours'];
            case self::SIDEBRUSH:
                return ['value' => 200, 'unit' => 'hours'];
            case self::FILTER:
                return ['value' => 150, 'unit' => 'hours'];
            case self::FILTERELEMENT:
                return ['value' => 999, 'unit' => 'hours'];
            case self::SENSOR:
                return ['value' => 30, 'unit' => 'hours']; //Sensoren
            case self::STRAINER:
                return ['value' => 200, 'unit' => 'counter']; //Wasserfilter beim S7 MaxV
            case self::DUSTCOLLECTOR://Staubbeutel?
            case self::CLEANINGBRUSH://Reinigungsbürste
                return ['value' => 999, 'unit' => 'counter'];
        }
        trigger_error('Unexpected consumable: ' . $consumable, E_USER_WARNING);
        return [];
    }
}

class Fanpower extends stdClass
{
    public const GENTLE   = 'Gentle';
    public const SILENT   = 'Silent';
    public const STANDARD = 'Standard';
    public const MEDIUM   = 'Medium';
    public const TURBO    = 'Turbo';
    public const MAXIMUM  = 'Max.';

    public const V1 = [
        self::SILENT   => 38,
        self::STANDARD => 60,
        self::MEDIUM => 77,
        self::TURBO => 90,
    ];

    public const S1 = [
        self::SILENT   => 101, //leise
        self::STANDARD => 102, //normal
        self::MEDIUM   => 103, //medium
        self::TURBO    => 104, //Turbo
    ];

    public const V2 = [
        self::GENTLE   => 105, //schonend
        self::SILENT   => 101, //leise
        self::STANDARD => 102, //normal
        self::TURBO    => 103, //Turbo
        self::MAXIMUM  => 104, //Max.
    ];
}

class Waterquantity extends stdClass
{
    public const OFF     = 'Off';
    public const LOW     = 'Low';
    public const HIGH    = 'Medium';
    public const MAXIMUM = 'High';
    public const CUSTOM  = 'Custom';

    public const V2 = [
        self::OFF     => 200, //aus
        self::LOW     => 201, //wenig
        self::HIGH    => 202, //mittel
        self::MAXIMUM => 203, //hoch
        self::CUSTOM  => 204, //benutzerdefiniert
    ];
}

class Features extends stdClass
{
    public const GET_CONSUMABLES = 1; //get_consumables

    public const SUPPORT_101 = 101; //V1
    public const SUPPORT_102 = 102; //V1, S5
    public const SUPPORT_CLEAN_TIME = 103; //S5
    public const SUPPORT_104 = 104; //V1, S5
    public const SUPPORT_105 = 105; //V1, S5
    public const SUPPORT_111 = 111; //a10, S5, S8
    public const SUPPORT_112 = 112; //a10, S5, S8
    public const SUPPORT_113 = 113; //a10, S5, S8
    public const SUPPORT_114 = 114; //a10, S5, S8
    public const SUPPORT_115 = 115; //a10, S5, S8
    public const SUPPORT_116 = 116; //a10, S5, S8
    public const SUPPORT_117 = 117; //a10, S5, S8
    public const SUPPORT_118 = 118; //a10, S5, S8
    public const SUPPORT_119 = 119; //a10, S5, S8
    public const MULTI_FLOOR_SUPPORT = 120; //get_multi_maps_list available , S8
    public const SUPPORT_121 = 121; //a10, S8
    public const SUPPORT_122 = 122; //a10, S5, S8
    public const SUPPORT_123 = 123; //a10, S5, S8
    public const SUPPORT_124 = 124; //a10, S8
    public const SUPPORT_125 = 125; //a10, S5, S8

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
        // see https://robotinfo.dev/
        'roborock.vacuum'     => 'Roborock Generic Vacuum Cleaner',
        'roborock.vacuum.m1s' => 'Mi Robot 1S',
        'rockrobo.vacuum.v1'  => 'Roborock V1',
        'roborock.vacuum.s5'  => 'Roborock S5',
        'roborock.vacuum.s5e' => 'Roborock S5 Max',
        'roborock.vacuum.s6'  => 'Roborock S6',
        'roborock.vacuum.a10' => 'Roborock S6 MaxV',
        'roborock.vacuum.a15' => 'Roborock S7',
        'roborock.vacuum.a27' => 'Roborock S7 MaxV',
        'roborock.vacuum.a51' => 'Roborock S8',
        'roborock.vacuum.a62' => 'Roborock S7 Pro Ultra',
        'roborock.vacuum.a65' => 'Roborock S7 Max Ultra',
        'roborock.vacuum.a70' => 'Roborock S8 Pro Ultra',
        'roborock.vacuum.a72' => 'Roborock Q5 Pro',
    ];

    public const FANPOWER      = Fanpower::V2;
    public const WATERQUANTITY = Waterquantity::V2;

    public const FEATURES = [
        Features::GET_CONSUMABLES,
        Features::MULTI_FLOOR_SUPPORT,
    ];

    public function GetName(string $classname): string
    {
        return self::DEVICELIST[str_replace('_', '.', $classname)];
    }

    public function GetModelType(string $classname): string
    {
        return explode('_', $classname)[2] ?? 'generic';
    }
}
class roborock_vacuum_m1s extends roborock_vacuum //1S
{
    public const CONSUMABLES = [];

    public const FANPOWER = [
        Fanpower::SILENT   => 101, //leise
        Fanpower::STANDARD => 102, //normal
        Fanpower::TURBO    => 103, //Turbo
        Fanpower::MAXIMUM  => 104, //Max.
    ];

    public const FEATURES = [
    ];
}

class rockrobo_vacuum_v1 extends roborock_vacuum //V1
{
    public const CONSUMABLES = [
        Consumable::MAINBRUSH => 'main_brush_work_time',
        Consumable::SIDEBRUSH => 'side_brush_work_time',
        Consumable::FILTER    => 'filter_work_time',
        Consumable::SENSOR    => 'sensor_dirty_time'
    ];

    public const FANPOWER = Fanpower::V1;

    public const FEATURES = [
        Features::SUPPORT_101,
        Features::SUPPORT_102,
        Features::SUPPORT_104,
        Features::SUPPORT_105,
        ];
}

class roborock_vacuum_s5 extends roborock_vacuum
{
    public const CONSUMABLES = [
        Consumable::MAINBRUSH => 'main_brush_work_time',
        Consumable::SIDEBRUSH => 'side_brush_work_time',
        Consumable::FILTER    => 'filter_work_time',
        Consumable::SENSOR    => 'sensor_dirty_time'
    ];
    public const FEATURES = [
        Features::GET_CONSUMABLES,
        Features::SUPPORT_102,
        Features::SUPPORT_CLEAN_TIME,
        Features::SUPPORT_104,
        Features::SUPPORT_105,
        Features::SUPPORT_111,
        Features::SUPPORT_112,
        Features::SUPPORT_113,
        Features::SUPPORT_114,
        Features::SUPPORT_115,
        Features::SUPPORT_116,
        Features::SUPPORT_117,
        Features::SUPPORT_118,
        Features::SUPPORT_119,
        Features::SUPPORT_122,
        Features::SUPPORT_123,
        Features::SUPPORT_124,
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

    public const FEATURES = [
        Features::GET_CONSUMABLES,
        Features::MULTI_FLOOR_SUPPORT,
    ];

}

class roborock_vacuum_s6 extends roborock_vacuum //S6
{
    public const CONSUMABLES = [
        Consumable::MAINBRUSH     => 'main_brush_work_time',
        Consumable::SIDEBRUSH     => 'side_brush_work_time',
        Consumable::FILTER        => 'filter_work_time',
        Consumable::FILTERELEMENT => 'filter_element_work_time',
        Consumable::SENSOR        => 'sensor_dirty_time'
    ];

    public const FANPOWER = Fanpower::V2;

    public const FEATURES = [
        Features::GET_CONSUMABLES,
        Features::MULTI_FLOOR_SUPPORT,
    ];

}
class roborock_vacuum_a10 extends roborock_vacuum_s6 //S6 MaxV
{
    public const FEATURES = [
        Features::GET_CONSUMABLES,
        Features::SUPPORT_111,
        Features::SUPPORT_112,
        Features::SUPPORT_113,
        Features::SUPPORT_114,
        Features::SUPPORT_115,
        Features::SUPPORT_116,
        Features::SUPPORT_117,
        Features::SUPPORT_118,
        Features::SUPPORT_119,
        Features::MULTI_FLOOR_SUPPORT,
        Features::SUPPORT_121,
        Features::SUPPORT_122,
        Features::SUPPORT_123,
        Features::SUPPORT_124,
        Features::SUPPORT_125,
    ];

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

    public const FEATURES = [
        Features::GET_CONSUMABLES,
        Features::SUPPORT_111,
        Features::SUPPORT_112,
        Features::SUPPORT_113,
        Features::SUPPORT_114,
        Features::SUPPORT_115,
        Features::SUPPORT_116,
        Features::SUPPORT_117,
        Features::SUPPORT_118,
        Features::SUPPORT_119,
        Features::MULTI_FLOOR_SUPPORT,
        Features::SUPPORT_121,
        Features::SUPPORT_122,
        Features::SUPPORT_123,
        Features::SUPPORT_124,
        Features::SUPPORT_125,
    ];

}

class roborock_vacuum_a51 extends roborock_vacuum_a15 //S8
{
}

class roborock_vacuum_a27 extends roborock_vacuum //S7 MaxV
{
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

    public const FEATURES = [
        Features::GET_CONSUMABLES,
        Features::SUPPORT_111,
        Features::SUPPORT_112,
        Features::SUPPORT_113,
        Features::SUPPORT_114,
        Features::SUPPORT_115,
        Features::SUPPORT_116,
        Features::SUPPORT_117,
        Features::SUPPORT_118,
        Features::SUPPORT_119,
        Features::MULTI_FLOOR_SUPPORT,
        Features::SUPPORT_121,
        Features::SUPPORT_122,
        Features::SUPPORT_123,
        Features::SUPPORT_124,
        Features::SUPPORT_125,
    ];

}

class roborock_vacuum_a65 extends roborock_vacuum_a27 //S7 Max Ultra
{
}

class roborock_vacuum_a72 extends roborock_vacuum_a15 //Q5 Pro / Q5 Pro+
{
    // Consumables, Fanpower (101-104) und die genutzten Features (GET_CONSUMABLES,
    // MULTI_FLOOR_SUPPORT) sind identisch zum S7 (a15) und werden geerbt.
    // Der A72 kennt jedoch kein get/set_water_box_custom_mode (Firmware liefert
    // 'unknown_method'); die Wischwassermenge wird geräteseitig über mop_mode
    // gesteuert. Daher hier keine Wassermengen-Auswahl anbieten.
    public const WATERQUANTITY = [];
}