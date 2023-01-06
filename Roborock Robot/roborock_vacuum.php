<?php

class Consumable extends stdClass
{
    public const MAINBRUSH     = 'MainBrush';
    public const SIDEBRUSH     = 'SideBrush';
    public const FILTER        = 'Filter';
    public const FILTERELEMENT = 'FilterElement';
    public const SENSOR        = 'Sensor';
    public const DUSTCOLLECTOR = 'DustCollector';
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
        'roborock.vacuum.a10' => 'Roborock S6 MaxV',
        'roborock.vacuum.a15' => 'Roborock S7',
    ];

    public function GetName(): string{
        return self::DEVICELIST[str_replace('_', '.', __CLASS__)];
    }
}

class roborock_vacuum_a10 extends roborock_vacuum
{
    public const CONSUMABLES = [
        Consumable::MAINBRUSH     => 'main_brush_work_time',
        Consumable::SIDEBRUSH     => 'side_brush_work_time',
        Consumable::FILTER        => 'filter_work_time',
        Consumable::FILTERELEMENT => 'filter_element_work_time',
        Consumable::SENSOR        => 'sensor_dirty_time'
    ];

    public function GetName(): string{
        return roborock_vacuum::DEVICELIST[str_replace('_', '.', __CLASS__)];
    }

}

class roborock_vacuum_a15 extends roborock_vacuum
{
    public const CONSUMABLES = [
        Consumable::MAINBRUSH     => 'main_brush_work_time',
        Consumable::SIDEBRUSH     => 'side_brush_work_time',
        Consumable::FILTER        => 'filter_work_time',
        Consumable::FILTERELEMENT => 'filter_element_work_time',
        Consumable::SENSOR        => 'sensor_dirty_time',
        Consumable::DUSTCOLLECTOR => 'dust_collection_work_times'
    ];

    public function GetName(): string{
        return roborock_vacuum::DEVICELIST[str_replace('_', '.', __CLASS__)];
    }

}