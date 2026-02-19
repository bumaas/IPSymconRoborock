<?php

declare(strict_types=1);

require_once __DIR__ . '/roborock_vacuum.php';
require_once __DIR__ . '/RRMapFileParser.php';
require_once __DIR__ . '/RRMapDraw.php';

enum StateCode: int
{
    case UNKNOWN = 0;
    case STARTING_UP = 1;
    case SLEEPING = 2;
    case WAITING = 3;
    case REMOTE_CONTROL = 4;
    case CLEANING = 5;
    case RETURNING_TO_BASE = 6;
    case MANUAL_MODE = 7;
    case CHARGING = 8;
    case CHARGING_PROBLEM = 9;
    case PAUSE = 10;
    case SPOT_CLEANING = 11;
    case MALFUNCTION = 12;
    case SHUTTING_DOWN = 13;
    case SOFTWARE_UPDATE = 14;
    case DOCKING = 15;
    case GO_TO = 16;
    case ZONE_CLEAN = 17;
    case ROOM_CLEAN = 18;
    case DUSTBIN_EMPTYING = 22;
    case MOP_WASHING = 23;
    case RETURNING_TO_BASE_FOR_MOP_WASHING = 26;
    case FULL = 100;

    // Optional: Methode, um den Namen als String zurückzugeben
    public function getDescription(): string
    {
        return match ($this) {
            self::UNKNOWN => 'Unknown',
            self::STARTING_UP => 'Starting up',
            self::SLEEPING => 'Sleeping',
            self::WAITING => 'Waiting',
            self::REMOTE_CONTROL => 'Remote control',
            self::CLEANING => 'Cleaning',
            self::RETURNING_TO_BASE => 'Returning to base',
            self::MANUAL_MODE => 'Manual mode',
            self::CHARGING => 'Charging',
            self::CHARGING_PROBLEM => 'Charging problem',
            self::PAUSE => 'Pause',
            self::SPOT_CLEANING => 'Spot cleaning',
            self::MALFUNCTION => 'Malfunction',
            self::SHUTTING_DOWN => 'Shutting down',
            self::SOFTWARE_UPDATE => 'Software update',
            self::DOCKING => 'Docking',
            self::GO_TO => 'Go To',
            self::ZONE_CLEAN => 'Zone Clean',
            self::ROOM_CLEAN => 'Room Clean',
            self::DUSTBIN_EMPTYING => 'Dustbin Emptying',
            self::MOP_WASHING => 'Mop Washing',
            self::RETURNING_TO_BASE_FOR_MOP_WASHING => 'Returning to base for mop washing',
            self::FULL => 'Full',
        };
    }
}

enum ErrorCode: int
{
    case NONE = 0;
    case LASER_SENSOR_FAULT = 1;
    case COLLISION_SENSOR_ERROR = 2;
    case WHEEL_FLOATING = 3;
    case CLIFF_SENSOR_FAULT = 4;
    case MAIN_BRUSH_BLOCKED = 5;
    case SIDE_BRUSH_BLOCKED = 6;
    case WHEEL_BLOCKED = 7;
    case DEVICE_STUCK = 8;
    case DUST_BIN_MISSING = 9;
    case FILTER_BLOCKED = 10;
    case MAGNETIC_FIELD_DETECTED = 11;
    case LOW_BATTERY = 12;
    case CHARGING_PROBLEM = 13;
    case BATTERY_FAILURE = 14;
    case WALL_SENSOR_FAULT = 15;
    case UNEVEN_SURFACE = 16;
    case SIDE_BRUSH_FAILURE = 17;
    case SUCTION_FAN_FAILURE = 18;
    case UNPOWERED_CHARGING_STATION = 19;
    case UNKNOWN = 20;
    case VERTICAL_BUMPER_PRESSED = 21;
    case DOCK_LOCATOR_DIRTY = 22;
    case DOCK_LOCATION_BEACON_LOST = 23;
    case NO_GO_ZONE_DETECTED = 24;
    case VIBRARISE_SYSTEM_JAMMED = 27;
    case ROBOT_ON_CARPET = 28;
    case STATION_BLOCKED_WITH_AUTO_EMPTY = 34;
    case CLEAN_WATER_TANK_SENSOR = 38;
    case CHECK_DIRTY_WATER_TANK = 39;
    case DUST_CONTAINER_NOT_INSTALLED = 46;

    public function getDescription(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::LASER_SENSOR_FAULT => 'Laser sensor fault',
            self::COLLISION_SENSOR_ERROR => 'Collision sensor error',
            self::WHEEL_FLOATING => 'Wheel floating',
            self::CLIFF_SENSOR_FAULT => 'Cliff sensor fault',
            self::MAIN_BRUSH_BLOCKED => 'Main brush blocked',
            self::SIDE_BRUSH_BLOCKED => 'Side brush blocked',
            self::WHEEL_BLOCKED => 'Wheel blocked',
            self::DEVICE_STUCK => 'Device stuck',
            self::DUST_BIN_MISSING => 'Dust bin missing',
            self::FILTER_BLOCKED => 'Filter blocked',
            self::MAGNETIC_FIELD_DETECTED => 'Magnetic field detected',
            self::LOW_BATTERY => 'Low battery',
            self::CHARGING_PROBLEM => 'Charging problem',
            self::BATTERY_FAILURE => 'Battery failure',
            self::WALL_SENSOR_FAULT => 'Wall sensor fault',
            self::UNEVEN_SURFACE => 'Uneven surface',
            self::SIDE_BRUSH_FAILURE => 'Side brush failure',
            self::SUCTION_FAN_FAILURE => 'Suction fan failure',
            self::UNPOWERED_CHARGING_STATION => 'Unpowered charging station',
            self::UNKNOWN => 'Unknown',
            self::VERTICAL_BUMPER_PRESSED => 'Vertical bumper pressed',
            self::DOCK_LOCATOR_DIRTY => 'Dock locator dirty',
            self::DOCK_LOCATION_BEACON_LOST => 'Dock location beacon lost',
            self::NO_GO_ZONE_DETECTED => 'No-go zone detected',
            self::VIBRARISE_SYSTEM_JAMMED => 'VibraRise system jammed',
            self::ROBOT_ON_CARPET => 'Robot on carpet',
            self::STATION_BLOCKED_WITH_AUTO_EMPTY => 'Ladestation blockiert bei automatischer Entleerung',
            self::CLEAN_WATER_TANK_SENSOR => 'Hallsensor für Reinwassertank ausgelöst',
            self::CHECK_DIRTY_WATER_TANK => 'Überprüfen Sie den Schmutzwassertank.',
            self::DUST_CONTAINER_NOT_INSTALLED => 'Staubbehälter nicht installiert',
        };
    }
}
/**
 * Class Roborock
 * Xiaomi Mi Vacuum Cleaner.
 *
 * a very useful API documentation: https://github.com/marcelrv/XiaomiRobotVacuumProtocol
 *
 * Models: https://dontvacuum.me/robotinfo/
 *
 * also https://python-miio.readthedocs.io/en/latest/device_docs/vacuum.html is very helpful
 *  with vacuum.enums:      https://github.com/rytilahti/python-miio/blob/master/miio/integrations/roborock/vacuum/vacuum_enums.py
 *  with vacuum.containers:
 *  github: https://github.com/rytilahti/python-miio/tree/master/miio/integrations/roborock/vacuum
 *          https://github.com/rytilahti/python-miio/blob/master/miio/integrations/roborock/vacuum/vacuum.py
 *
 * another implementation: https://github.com/iobroker-community-adapters/ioBroker.mihome-vacuum
 * https://github.com/openhab/openhab-addons/tree/main/bundles/org.openhab.binding.miio
 *
 * KNX Xiaomi Roboroc Integration: https://service.knx-user-forum.de/?comm=download&id=19001929&dl=1
 */
class Roborock extends IPSModuleStrict
{

    private const STATUS_INST_REGISTRATION_INCOMPLETE = 201;
    private const STATUS_INST_IP_ADDRESS_IS_INVALID   = 203;
    private const STATUS_INST_TOKEN_IS_INVALID        = 205;
    private const STATUS_INST_NO_ROBOROCK_FOUND       = 206;

    private const ATTRIBUTE_TOKEN                   = 'token';
    private const ATTRIBUTE_LOGIN_LOCATION_DATA     = 'loginLocationData';
    private const ATTRIBUTE_LOGIN_ACCOUNT_DATA      = 'loginAccountData';
    private const ATTRIBUTE_LAST_NOTIFICATION_STATE = 'last_notification_state';
    private const ATTRIBUTE_LAST_NOTIFICATION_ERROR = 'last_notification_error';
    private const ATTRIBUTE_CLEANING_RECORDS        = 'cleaning_records';
    private const ATTRIBUTE_MODEL                   = 'model';
    private const ATTRIBUTE_MAPFILE_URL             = 'mapfile_url';
    private const ATTRIBUTE_MAPS_LIST               = 'maps_list'; //hier sind alle Werte der Maps_List abgelegt
    private const ATTRIBUTE_ROOM_NAMES              = 'room_names'; //hier sind alle Texte unter der Referenznummer abgelegt [[referenz => Raumname], ...]
    private const ATTRIBUTE_ROOM_SELECTION          = 'room_selection'; //hier sind die für einen Reinigungsauftrag selektierten Räume abgelegt
    private const ATTRIBUTE_AGENTID                 = 'agentId';
    private const ATTRIBUTE_CLIENTID                = 'clientId';

    private const BUFFER_VERIFICATION_URL  = 'notification_url';
    private const BUFFER_VERIFICATION_FLAG = 'flag';
    private const BUFFER_IDENTITY_SESSION  = 'identity_session';

    private const PROPERTY_IP                   = 'ip';
    private const PROPERTY_MODEL                = 'model';
    private const PROPERTY_VOLUME               = 'volume';
    private const PROPERTY_FAN_POWER            = 'fan_power';
    private const PROPERTY_WATER_QUANTITY       = 'water_quantity';
    private const PROPERTY_MAP_STATUS           = 'map_status';
    private const PROPERTY_MAP_PICTURE          = 'map_picture';
    private const PROPERTY_MAP_PICTURE_SCALE    = 'map_picture_scale';
    private const PROPERTY_CONSUMABLES          = 'consumables';
    private const PROPERTY_CONSUMABLES_SEPARATE = 'consumables_separate';
    private const PROPERTY_XIAOMI_USER          = 'xiaomi_user';
    private const PROPERTY_XIAOMI_PASSWORD      = 'xiaomi_password';
    private const PROPERTY_REMOTE               = 'remote';
    private const PROPERTY_UPDATE_INTERVAL      = 'UpdateInterval';
    private const PROPERTY_CLEANING_ORDER       = 'CleaningOrder';
    private const PROPERTY_SERVER               = 'Server';
    private const PROPERTY_CLEAN_TIME           = 'clean_time';

    private const PROFILE_COMMAND         = 'Roborock.Command';
    private const PROFILE_ERRORCODE       = 'Roborock.Errorcode';
    private const PROFILE_STATE           = 'Roborock.State';
    private const PROFILE_FINDME          = 'Roborock.Findme';
    private const PROFILE_FANPOWER        = 'Roborock.Fanpower';
    private const PROFILE_WATERQUANTITY   = 'Roborock.WaterQuantity';
    private const PROFILE_MAPS            = 'Roborock.Maps';
    private const PROFILE_CLEANAREA       = 'Roborock.Cleanarea';
    private const PROFILE_TOTALCLEANS     = 'Roborock.Totalcleans';
    private const PROFILE_VOLUME          = 'Roborock.Volume';
    private const PROFILE_BATTERY         = 'Roborock.Battery';
    private const PROFILE_CONSUMABLE      = 'Roborock.Consumable';
    private const PROFILE_DURATION        = 'Roborock.Duration';
    private const PROFILE_ROOMSELECTION   = 'Roborock.Roomselection';
    private const PROFILE_CLEANING_CYCLES = 'Roborock.CleaningCycles';
    private const PROFILE_START_CLEANING  = 'Roborock.StartCleaning';

    private const IDENT_SERIAL_NUMBER             = 'serial_number';
    private const IDENT_TIMEZONE                  = 'timezone';
    private const IDENT_VOLUME                    = 'volume';
    private const IDENT_COMMAND                   = 'command';
    private const IDENT_STATE                     = 'state';
    private const IDENT_FAN_POWER                 = 'fan_power';
    private const IDENT_WATER_QUANTITY            = 'water_quantity';
    private const IDENT_CONSUMABLES               = 'consumables';
    private const IDENT_WATER_BOX_STATUS          = 'water_box_status';
    private const IDENT_WATER_BOX_CARRIAGE_STATUS = 'water_box_carriage_status'; //Anmerkung: der Unterschied zwischen 'water_box_status' und 'water_box_carriage_status' ist unklar
    private const IDENT_MAP_STATUS                = 'map_status';
    private const IDENT_MAP_PICTURE               = 'map_picture';
    private const IDENT_MAP_PICTURE_FILE          = 'map_picture_file';
    private const IDENT_MODEL                     = 'model';
    private const IDENT_REMOTE_CONTROL            = 'remote';
    private const IDENT_ROOMSELECTION             = 'roomselection';
    private const IDENT_ROOMS_SELECTED            = 'rooms_selected';
    private const IDENT_CLEANING_CYCLES           = 'cleaning_cycles';
    private const IDENT_START_CLEANING            = 'start_cleaning';
    private const IDENT_UPDATEROOMNAME            = 'UpdateRoomName';


    // Form Fields
    private const FF_MAPANDROOMLIST        = 'MapAndRoomList';
    private const FF_COL_MAPFLAG           = 'mapFlag';
    private const FF_COL_MAPNAME           = 'MapName';
    private const FF_COL_ROOMID            = 'RoomID';
    private const FF_COL_PARENT_MAP_ID     = 'ParentMapID';
    private const FF_COL_ROOMTEXTREFERENCE = 'RoomTextReference';
    private const FF_COL_ROOMNAME          = 'RoomName';
    private const FF_COL_IGNORE_ROOM       = 'IgnoreRoom';


    private const TIMER_UPDATE     = 'RoborockTimerUpdate';
    private const TIMER_UPDATE_MAP = 'RoborockTimerUpdate_Map';


    private const PUSH_NOTIFICATIONS = [
        [
            'enabled'  => true,
            'state_id' => 'errors', // enable all error codes
            'name'     => 'Error',
            'sound'    => 'alarm'
        ],
        [
            'enabled'  => false,
            'state_id' => StateCode::CLEANING,
            'name'     => 'Cleaning',
            'sound'    => '' // empty = default sound
        ],
        [
            'enabled'  => false,
            'state_id' => StateCode::CHARGING,
            'name'     => 'Charging',
            'sound'    => ''
        ],
        [
            'enabled'  => true,
            'state_id' => StateCode::RETURNING_TO_BASE,
            'name'     => 'Returning to base',
            'sound'    => ''
        ],
        [
            'enabled'  => false,
            'state_id' => StateCode::DOCKING,
            'name'     => 'Docking',
            'sound'    => ''
        ]
    ];

    private const SINGLE_MAP = ['0' => ['mapFlag' => 0, 'MapName' => 'MyMap']];

    private const DEFAULT_VALUE_UPDATE_INTERVAL = 60;
    private const MAX_NUMBER_OF_CLEAN_RECORDS   = 5;

    // helper properties
    private int             $position = 0;

    private roborock_vacuum $device;

    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID);

        if (($model = @$this->ReadAttributeString(self::ATTRIBUTE_MODEL)) && ($modelClassName = str_replace('.', '_', $model))
            && class_exists(
                $modelClassName
            )) {
            $this->device = new $modelClassName();
        } else {
            $this->device = new roborock_vacuum();
        }
    }

    /**
     * create instance.
     *
     * @return void
     */
    public function Create(): void
    {
        parent::Create();

        // Init Buffers
        $this->SetBuffer(self::BUFFER_IDENTITY_SESSION, '');
        $this->SetBuffer(self::BUFFER_VERIFICATION_FLAG, '');
        $this->SetBuffer(self::BUFFER_VERIFICATION_URL, '');

        // register public properties
        $this->RegisterPropertyString(self::PROPERTY_IP, '');
        $this->RegisterPropertyBoolean(self::PROPERTY_FAN_POWER, false);
        $this->RegisterPropertyBoolean(self::PROPERTY_WATER_QUANTITY, false);
        $this->RegisterPropertyBoolean(self::PROPERTY_MAP_STATUS, false);
        $this->RegisterPropertyBoolean(self::PROPERTY_MAP_PICTURE, false);
        $this->RegisterPropertyInteger(self::PROPERTY_MAP_PICTURE_SCALE, 100);
        $this->RegisterPropertyBoolean('error_code', false);
        $this->RegisterPropertyBoolean(self::PROPERTY_CONSUMABLES, false);
        $this->RegisterPropertyBoolean(self::PROPERTY_CONSUMABLES_SEPARATE, false);
        $this->RegisterPropertyBoolean('dnd_mode', false);
        $this->RegisterPropertyBoolean('clean_area', false);
        $this->RegisterPropertyBoolean(self::PROPERTY_CLEAN_TIME, false);
        $this->RegisterPropertyBoolean('total_cleans', false);
        $this->RegisterPropertyBoolean('serial_number', false);
        $this->RegisterPropertyBoolean('timer_details', false);
        $this->RegisterPropertyBoolean('extended_info', false);
        $this->RegisterPropertyBoolean(self::PROPERTY_VOLUME, false);
        $this->RegisterPropertyBoolean('timezone', false);
        $this->RegisterPropertyBoolean(self::PROPERTY_REMOTE, false);
        $this->RegisterPropertyBoolean(self::PROPERTY_CLEANING_ORDER, false);

        $this->RegisterPropertyInteger('notification_instance', 0);
        $this->RegisterPropertyString('notifications', $this->GetPushNotifications());
        $this->RegisterPropertyString('zonecoordinates', '');

        $this->RegisterPropertyString(self::PROPERTY_XIAOMI_USER, '');
        $this->RegisterPropertyString(self::PROPERTY_XIAOMI_PASSWORD, '');

        $this->RegisterPropertyString(self::PROPERTY_SERVER, 'de');

        // register update timer
        $this->RegisterPropertyInteger(self::PROPERTY_UPDATE_INTERVAL, self::DEFAULT_VALUE_UPDATE_INTERVAL);
        $this->RegisterTimer(self::TIMER_UPDATE, 0, 'Roborock_Update(' . $this->InstanceID . ');');
        $this->RegisterTimer(self::TIMER_UPDATE_MAP, 0, 'Roborock_GetMap(' . $this->InstanceID . ');');

        // register kernel messages
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        // register attributes
        $this->RegisterAttributeString(self::ATTRIBUTE_TOKEN, '');
        $this->RegisterAttributeString(self::ATTRIBUTE_LOGIN_LOCATION_DATA, json_encode([], JSON_THROW_ON_ERROR));
        $this->RegisterAttributeString(self::ATTRIBUTE_LOGIN_ACCOUNT_DATA, json_encode([], JSON_THROW_ON_ERROR));
        $this->RegisterAttributeString(self::ATTRIBUTE_LAST_NOTIFICATION_STATE, '');
        $this->RegisterAttributeString(self::ATTRIBUTE_LAST_NOTIFICATION_ERROR, '');
        $this->RegisterAttributeString(self::ATTRIBUTE_CLEANING_RECORDS, '[]');
        $this->RegisterAttributeString(self::ATTRIBUTE_MODEL, '');
        $this->RegisterAttributeString(self::ATTRIBUTE_MAPFILE_URL, '');
        $this->RegisterAttributeString(self::ATTRIBUTE_MAPS_LIST, json_encode([], JSON_THROW_ON_ERROR));
        $this->RegisterAttributeString(self::ATTRIBUTE_ROOM_NAMES, json_encode([], JSON_THROW_ON_ERROR));
        $this->RegisterAttributeString(self::ATTRIBUTE_ROOM_SELECTION, json_encode([], JSON_THROW_ON_ERROR));
        $this->RegisterAttributeString(self::ATTRIBUTE_AGENTID, $this->randomAgentId());
        $this->RegisterAttributeString(self::ATTRIBUTE_CLIENTID, $this->randomClientId());

        //we will wait until the kernel is ready
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        //we will set the instance status when the parent status changes

        if ($this->GetParent() > 0) {
            $this->RegisterMessage($this->GetParent(), IM_CHANGESTATUS);
        }
    }

    public function Destroy(): void
    {
        $this->UnregisterProfile(sprintf('%s.%s', self::PROFILE_ROOMSELECTION, $this->InstanceID));
        $this->UnregisterProfile(self::PROFILE_CONSUMABLE);
        $this->UnregisterProfile(self::PROFILE_COMMAND);
        $this->UnregisterProfile(self::PROFILE_BATTERY);
        $this->UnregisterProfile(self::PROFILE_CLEANAREA);
        $this->UnregisterProfile(self::PROFILE_START_CLEANING);
        $this->UnregisterProfile(self::PROFILE_CLEANING_CYCLES);
        $this->UnregisterProfile(self::PROFILE_DURATION);
        $this->UnregisterProfile(self::PROFILE_ERRORCODE);
        $this->UnregisterProfile(self::PROFILE_FANPOWER);
        $this->UnregisterProfile(self::PROFILE_FINDME);
        $this->UnregisterProfile(self::PROFILE_MAPS);
        $this->UnregisterProfile(self::PROFILE_STATE);
        $this->UnregisterProfile(self::PROFILE_TOTALCLEANS);
        $this->UnregisterProfile(self::PROFILE_VOLUME);
        $this->UnregisterProfile(self::PROFILE_WATERQUANTITY);

        parent::Destroy();
    }


    /**
     * apply changes from configuration form.
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        $classname = get_class($this->device);
        if ($classname !== 'roborock_vacuum') {
            $profileSuffix = '.' . $this->device->GetModelType($classname);
        } else {
            $profileSuffix = '';
        }

        //  register profiles
        $this->RegisterProfileAssociation(
            self::PROFILE_COMMAND,
            'Execute',
            '',
            '',
            0,
            4,
            0,
            0,
            VARIABLETYPE_INTEGER,
            [
                [0, $this->Translate('Start'), 'HollowLargeArrowRight', -1, 1],
                [1, $this->Translate('Pause'), 'Close', -1],
                [2, $this->Translate('Stop'), 'Close', -1],
                [3, $this->Translate('Spot'), 'Climate', -1],
                [4, $this->Translate('Charge'), 'Battery', -1],
                [5, $this->Translate('Locate'), 'Motion', -1]
            ]
        );

        $ass = [];
        foreach (ErrorCode::cases() as $error) {
            $ass[] = [$error->value, $error->getDescription(), '', -1];
        }
        $this->RegisterProfileAssociation(
            self::PROFILE_ERRORCODE,
            'Information',
            '',
            '',
            0,
            0,
            0,
            0,
            VARIABLETYPE_INTEGER,
            $ass
        );

        $ass = [];
        foreach (StateCode::cases() as $state) {
            $ass[] = [$state->value, $state->getDescription(), '', -1];
        }
        $this->RegisterProfileAssociation(
            self::PROFILE_STATE,
            'Information',
            '',
            '',
            0,
            0,
            0,
            0,
            VARIABLETYPE_INTEGER,
            $ass
        );

        $this->RegisterProfileAssociation(
            self::PROFILE_FINDME,
            'Robot',
            '',
            '',
            0,
            0,
            0,
            0,
            VARIABLETYPE_INTEGER,
            [
                [0, $this->Translate('find robot'), '', 0x3ADF00]
            ]
        );

        if ($this->ReadPropertyBoolean(self::PROPERTY_FAN_POWER)) {
            $ass = [];
            foreach ($this->device::FANPOWER as $name => $value) {
                $ass[] = [$value, $this->Translate($name), '', -1];
            }
            $this->RegisterProfileAssociation(self::PROFILE_FANPOWER . $profileSuffix, 'Speedo', '', '', 0, 0, 0, 0, VARIABLETYPE_INTEGER, $ass);
        }

        if ($this->ReadPropertyBoolean(self::PROPERTY_WATER_QUANTITY)) {
            $ass = [];
            foreach ($this->device::WATERQUANTITY as $name => $value) {
                $ass[] = [$value, $this->Translate($name), '', -1];
            }
            $this->RegisterProfileAssociation(self::PROFILE_WATERQUANTITY . $profileSuffix, 'Drops', '', '', 0, 0, 0, 0, VARIABLETYPE_INTEGER, $ass);
        }

        $this->RegisterProfile(self::PROFILE_CLEANAREA, 'Shuffle', '', ' m²', 0, 0, 0, 1, VARIABLETYPE_FLOAT);
        $this->RegisterProfile(self::PROFILE_TOTALCLEANS, 'Gauge', '', '', 0, 0, 0, 2, VARIABLETYPE_INTEGER);
        $this->RegisterProfile(self::PROFILE_VOLUME, 'Speaker', '', ' %', 0, 100, 1, 0, VARIABLETYPE_INTEGER);
        $this->RegisterProfile(self::PROFILE_BATTERY, 'Battery', '', ' %', 0, 100, 1, 0, VARIABLETYPE_INTEGER);
        $this->RegisterProfile(self::PROFILE_CONSUMABLE, 'Gear', '', ' %', 0, 100, 1, 0, VARIABLETYPE_INTEGER);
        $this->RegisterProfile(self::PROFILE_DURATION, '', '', ' s', 0, 0, 0, 0, VARIABLETYPE_INTEGER);
        $this->RegisterProfile(self::PROFILE_MAPS, '', '', '', 0, 0, 0, 0, VARIABLETYPE_INTEGER);

        // Remote Control
        if ($this->ReadPropertyBoolean(self::PROPERTY_REMOTE)) {
            if ($this->RegisterVariableString(self::IDENT_REMOTE_CONTROL, $this->Translate('Remote Control'), '~HTMLBox', $this->_getPosition())) {
                IPS_SetIcon($this->GetIDForIdent(self::IDENT_REMOTE_CONTROL), 'Move');
                $this->SetJoystickHtml();
            }
        } else {
            $this->UnregisterVariable(self::IDENT_REMOTE_CONTROL);
        }

        // command
        $this->RegisterVariableInteger(self::IDENT_COMMAND, $this->Translate('Command'), self::PROFILE_COMMAND, $this->_getPosition());
        $this->EnableAction(self::IDENT_COMMAND);

        // current state
        $this->RegisterVariableInteger(self::IDENT_STATE, $this->Translate('State'), self::PROFILE_STATE, $this->_getPosition());

        // current battery level
        $this->RegisterVariableInteger('battery', $this->Translate('Battery'), self::PROFILE_BATTERY, $this->_getPosition());

        // fan power
        if ($this->ReadPropertyBoolean(self::PROPERTY_FAN_POWER)) {
            $this->RegisterVariableInteger(
                self::IDENT_FAN_POWER,
                $this->Translate('Fan Power'),
                self::PROFILE_FANPOWER . $profileSuffix,
                $this->_getPosition()
            );
            $this->EnableAction(self::IDENT_FAN_POWER);
        } else {
            $this->UnregisterVariable(self::IDENT_FAN_POWER);
        }

        // water quantity
        if ($this->ReadPropertyBoolean(self::PROPERTY_WATER_QUANTITY)) {
            $this->RegisterVariableInteger(
                self::IDENT_WATER_QUANTITY,
                $this->Translate('Water Quantity'),
                self::PROFILE_WATERQUANTITY . $profileSuffix,
                $this->_getPosition()
            );
            $this->RegisterVariableBoolean(self::IDENT_WATER_BOX_STATUS, $this->Translate('Water Box installed'), '~Switch', $this->_getPosition());
            $this->RegisterVariableBoolean(
                self::IDENT_WATER_BOX_CARRIAGE_STATUS,
                $this->Translate('Water Box Carriage Status'),
                '~Switch',
                $this->_getPosition()
            );
            $this->EnableAction(self::IDENT_WATER_QUANTITY);
        } else {
            $this->UnregisterVariable(self::IDENT_WATER_QUANTITY);
            $this->UnregisterVariable(self::IDENT_WATER_BOX_STATUS);
            $this->UnregisterVariable(self::IDENT_WATER_BOX_CARRIAGE_STATUS);
        }

        // map_status
        if ($this->ReadPropertyBoolean(self::PROPERTY_MAP_STATUS) || $this->ReadPropertyBoolean(self::PROPERTY_CLEANING_ORDER)) {
            $this->RegisterVariableInteger(self::IDENT_MAP_STATUS, $this->Translate('Active Map'), self::PROFILE_MAPS, $this->_getPosition());
            $this->EnableAction(self::IDENT_MAP_STATUS);
            //when MAP_STATUS is created, we can update the RoomSelectionProfile
            $this->WriteRoomSelectionProfile();
        } else {
            $this->UnregisterVariable(self::IDENT_MAP_STATUS);
        }

        // map_picture
        if ($this->ReadPropertyBoolean(self::PROPERTY_MAP_PICTURE)) {
            $this->CreateMapPictureVariable(self::IDENT_MAP_PICTURE, 'Map', sprintf('Map_%s.png', $this->InstanceID));
        }

        // volume
        if ($this->ReadPropertyBoolean(self::PROPERTY_VOLUME)) {
            $this->RegisterVariableInteger(self::IDENT_VOLUME, $this->Translate('Volume'), self::PROFILE_VOLUME, $this->_getPosition());
            $this->EnableAction(self::IDENT_VOLUME);
        } else {
            $this->UnregisterVariable(self::IDENT_VOLUME);
        }

        // error code
        if ($this->ReadPropertyBoolean('error_code')) {
            $this->RegisterVariableInteger('error_code', $this->Translate('Error Code'), self::PROFILE_ERRORCODE, $this->_getPosition());
        } else {
            $this->UnregisterVariable('error_code');
        }

        // consumables
        if ($this->ReadPropertyBoolean(self::PROPERTY_CONSUMABLES)) {
            $this->RegisterVariableString(self::IDENT_CONSUMABLES, $this->Translate('Consumables'), '~HTMLBox', $this->_getPosition());
        } else {
            $this->UnregisterVariable(self::IDENT_CONSUMABLES);
        }

        // consumables separate
        if ($this->ReadPropertyBoolean(self::PROPERTY_CONSUMABLES_SEPARATE)) {
            foreach ($this->device::CONSUMABLES as $ident => $consumable) {
                $this->RegisterVariableInteger(
                    $ident,
                    $this->Translate(consumable::GetName($ident)),
                    self::PROFILE_CONSUMABLE,
                    $this->_getPosition()
                );
            }
        } else {
            foreach ($this->device::CONSUMABLES as $ident => $consumable) {
                $this->UnregisterVariable($ident);
            }
        }

        // dnd mode
        if ($this->ReadPropertyBoolean('dnd_mode')) {
            $this->RegisterVariableBoolean('dnd_mode', $this->Translate('DND Mode'), '~Switch', $this->_getPosition());
            $this->EnableAction('dnd_mode');
            $this->RegisterVariableInteger('dnd_starttime', $this->Translate('DND Starttime'), '~UnixTimestampTime', $this->_getPosition());
            $this->EnableAction('dnd_starttime');
            $this->RegisterVariableInteger('dnd_endtime', $this->Translate('DND Endtime'), '~UnixTimestampTime', $this->_getPosition());
            $this->EnableAction('dnd_endtime');
        } else {
            $this->UnregisterVariable('dnd_mode');
            $this->UnregisterVariable('dnd_starttime');
            $this->UnregisterVariable('dnd_endtime');
        }

        // clean area
        if ($this->ReadPropertyBoolean('clean_area')) {
            $this->RegisterVariableFloat('clean_area', $this->Translate('Clean Area'), self::PROFILE_CLEANAREA, $this->_getPosition());
            $this->RegisterVariableFloat('total_clean_area', $this->Translate('Total Clean Area'), self::PROFILE_CLEANAREA, $this->_getPosition());
        } else {
            $this->UnregisterVariable('clean_area');
            $this->UnregisterVariable('total_clean_area');
        }

        // clean_time
        if ($this->ReadPropertyBoolean(self::PROPERTY_CLEAN_TIME)) {
            $this->RegisterVariableInteger('clean_time', $this->Translate('Clean Time'), self::PROFILE_DURATION, $this->_getPosition());
            $this->RegisterVariableInteger('total_clean_time', $this->Translate('Total Clean Time'), self::PROFILE_DURATION, $this->_getPosition());
            $this->RegisterVariableString('cleaning_records', $this->Translate('Cleaning Records'), '~HTMLBox', $this->_getPosition());
        } else {
            $this->UnregisterVariable('clean_time');
            $this->UnregisterVariable('total_clean_time');
            $this->UnregisterVariable('cleaning_records');
        }

        // total cleans
        if ($this->ReadPropertyBoolean('total_cleans')) {
            $this->RegisterVariableInteger('total_cleans', $this->Translate('Total Cleans'), self::PROFILE_TOTALCLEANS, $this->_getPosition());
        } else {
            $this->UnregisterVariable('total_cleans');
        }

        // serial number
        if ($this->ReadPropertyBoolean('serial_number')) {
            if ($this->RegisterVariableString(self::IDENT_SERIAL_NUMBER, $this->Translate('Serial Number'), '', $this->_getPosition())) {
                IPS_SetIcon($this->GetIDForIdent(self::IDENT_SERIAL_NUMBER), 'Robot');
            }
        } else {
            $this->UnregisterVariable(self::IDENT_SERIAL_NUMBER);
        }

        // timer details
        if ($this->ReadPropertyBoolean('timer_details')) {
            if ($this->RegisterVariableString('timer_details', $this->Translate('Timer Details'), '~HTMLBox', $this->_getPosition())) {
                IPS_SetIcon($this->GetIDForIdent('timer_details'), 'Clock');
            }
        } else {
            $this->UnregisterVariable('timer_details');
        }

        // extended info
        if ($this->ReadPropertyBoolean('extended_info')) {
            $this->RegisterVariableString('hw_ver', $this->Translate('hardware version'), '', $this->_getPosition());
            $this->RegisterVariableString('fw_ver', $this->Translate('firmware version'), '', $this->_getPosition());
            $this->RegisterVariableString('ssid', $this->Translate('ssid'), '', $this->_getPosition());
            $this->RegisterVariableString('rssi', $this->Translate('rssi'), '', $this->_getPosition());
            $this->RegisterVariableString('local_ip', $this->Translate('local ip'), '', $this->_getPosition());
            $this->RegisterVariableString(self::IDENT_MODEL, $this->Translate('model'), '', $this->_getPosition());
            $this->RegisterVariableString('mac', $this->Translate('mac'), '', $this->_getPosition());
        } else {
            $this->UnregisterVariable('hw_ver');
            $this->UnregisterVariable('fw_ver');
            $this->UnregisterVariable('ssid');
            $this->UnregisterVariable('rssi');
            $this->UnregisterVariable('local_ip');
            $this->UnregisterVariable(self::IDENT_MODEL);
            $this->UnregisterVariable('mac');
        }

        // Timezone
        if ($this->ReadPropertyBoolean('timezone')) {
            $this->RegisterVariableString(self::IDENT_TIMEZONE, $this->Translate('Timezone'), '', $this->_getPosition());
        } else {
            $this->UnregisterVariable(self::IDENT_TIMEZONE);
        }

        if ($this->ReadPropertyBoolean(self::PROPERTY_CLEANING_ORDER)) {
            $this->RegisterVariableInteger(
                self::IDENT_ROOMSELECTION,
                $this->Translate('Roomselection'),
                sprintf('%s.%s', self::PROFILE_ROOMSELECTION, $this->InstanceID),
                $this->_getPosition()
            );
            $this->EnableAction(self::IDENT_ROOMSELECTION);
            $this->_SetValue(self::IDENT_ROOMSELECTION, -1);


            $this->RegisterVariableString(self::IDENT_ROOMS_SELECTED, $this->Translate('Selected Rooms'), '', $this->_getPosition());

            $ass = [];
            for ($i = 1; $i <= 3; $i++) {
                $ass[] = [$i, sprintf('%sx', $i), '', -1];
            }
            $this->RegisterProfileAssociation(self::PROFILE_CLEANING_CYCLES, '', '', '', 0, 0, 0, 0, VARIABLETYPE_INTEGER, $ass);

            $this->RegisterVariableInteger(
                self::IDENT_CLEANING_CYCLES,
                $this->Translate('Cleaning Cycles'),
                self::PROFILE_CLEANING_CYCLES,
                $this->_getPosition()
            );
            if ((int)$this->GetValue(self::IDENT_CLEANING_CYCLES) === 0) {
                $this->_SetValue(self::IDENT_CLEANING_CYCLES, 1);
            }
            $this->EnableAction(self::IDENT_CLEANING_CYCLES);

            $this->RegisterProfileAssociation(self::PROFILE_START_CLEANING, '', '', '', 0, 0, 0, 0, VARIABLETYPE_INTEGER, [[1, 'Start', '', -1]]);
            $this->RegisterVariableInteger(
                self::IDENT_START_CLEANING,
                $this->Translate('Start Cleaning'),
                self::PROFILE_START_CLEANING,
                $this->_getPosition()
            );
            $this->EnableAction(self::IDENT_START_CLEANING);
        }

        // receive data only for this instance
        $this->SetReceiveDataFilter('.*"InstanceID":' . $this->InstanceID . '.*');

        // set summary
        $this->SetSummary(
            sprintf(
                '%s (%s)',
                $this->ReadPropertyString(self::PROPERTY_IP),
                trim(str_replace('Roborock', '', $this->device->GetName(get_class($this->device))))
            )
        );

        // validate configuration
        $this->ValidateConfiguration();

        // set interval
        $this->SetUpdateInterval();
    }

    /**
     * Handle Kernel Messages.
     *
     * @param int   $TimeStamp
     * @param int   $SenderID
     * @param int   $Message
     * @param array $Data
     *
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->_debug(__FUNCTION__, 'SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data:' . json_encode($Data, JSON_THROW_ON_ERROR));

        switch ($Message) {
            case IPS_KERNELMESSAGE:
                if ($Data[0] === KR_READY) {
                    $this->ApplyChanges();
                }
                break;

            case IM_CHANGESTATUS:
                $this->ApplyChanges();
                break;
        }
    }

    /**
     * validate configuration.
     *
     * @return bool
     */
    private function ValidateConfiguration(): bool
    {
        // check ip address
        $ip = $this->ReadPropertyString(self::PROPERTY_IP);
        if (!$ip || !filter_var(gethostbyname($ip), FILTER_VALIDATE_IP)) {
            $this->SetStatus(self::STATUS_INST_IP_ADDRESS_IS_INVALID);
            $this->SendDebug(__FUNCTION__, (string)$this->GetStatus(), 0);
            return false;
        }

        // check if configuration is complete
        if (!$this->CheckUserAndPassword()) {
            $this->SetStatus(self::STATUS_INST_REGISTRATION_INCOMPLETE);
            $this->SendDebug(__FUNCTION__, (string)$this->GetStatus(), 0);
            return false;
        }

        // check token
        if (!$this->ValidateToken()) {
            $this->SetStatus(self::STATUS_INST_TOKEN_IS_INVALID);
            $this->SendDebug(__FUNCTION__, (string)$this->GetStatus(), 0);
            return false;
        }

        // get device info
        $info = $this->RequestData('miIO.info', [
            'immediate' => true
        ]);

        if (!$info) {
            $this->SetStatus(self::STATUS_INST_NO_ROBOROCK_FOUND);
            $this->SendDebug(__FUNCTION__, (string)$this->GetStatus(), 0);
            return false;
        }

        $this->_debug('info', json_encode($info, JSON_THROW_ON_ERROR));

        // yay, configuration is valid!
        $this->SetStatus(IS_ACTIVE);

        if (get_class($this->device) === 'roborock_vacuum') {
            $this->_debug(
                __FUNCTION__,
                sprintf(
                    'The device ist operational (102), but the model \'%s\' is not yet well supported.',
                    $this->ReadAttributeString(self::ATTRIBUTE_MODEL)
                )
            );
        } else {
            $this->_debug(__FUNCTION__, 'The device ist operational (102)');
        }

        return true;
    }

    /**
     * set / unset update interval.
     *
     */
    private function SetUpdateInterval(): void
    {
        if ($this->GetStatus() === IS_ACTIVE) {
            $interval = $this->ReadPropertyInteger(self::PROPERTY_UPDATE_INTERVAL) * 1000;
        } else {
            $interval = 0;
        }
        $this->SetTimerInterval(self::TIMER_UPDATE, $interval);

        if ($interval === 0) {
            $this->SetTimerInterval(self::TIMER_UPDATE_MAP, 0);
        }
    }


    public function SetDeviceToken(string $token): void
    {
        $this->WriteAttributeString(self::ATTRIBUTE_TOKEN, $token);

        // validate configuration
        $this->ValidateConfiguration();

        // set interval
        $this->SetUpdateInterval();
    }

    /**
     * Update data.
     */
    public function Update(): void
    {
        $this->_debug(__FUNCTION__ . ': start');

        if ($this->ValidateConfiguration()) {
            // Update state
            $this->Get_State();

            // update serial number, once
            if ($this->ReadPropertyBoolean('serial_number') && !$this->GetValue(self::IDENT_SERIAL_NUMBER)) {
                $this->Get_Serial_Number();
            }

            // update consumables
            if (in_array(Features::GET_CONSUMABLES, $this->device::FEATURES, true)
                && ($this->ReadPropertyBoolean(self::PROPERTY_CONSUMABLES)
                    || $this->ReadPropertyBoolean(
                        self::PROPERTY_CONSUMABLES_SEPARATE
                    ))) {
                $this->Get_Consumables();
            }


            // update dnd mode
            if ($this->ReadPropertyBoolean('dnd_mode')) {
                $this->Get_DND_Mode();
            }

            // update extended info
            if ($this->ReadPropertyBoolean('extended_info')) {
                $this->GetDeviceInfo();
            }

            // update timer details
            if ($this->ReadPropertyBoolean('timer_details')) {
                $this->Get_Timer_Details();
            }

            // update volume
            if ($this->ReadPropertyBoolean(self::PROPERTY_VOLUME)) {
                $this->Get_SoundVolume();
            }

            // update timezone, once
            if ($this->ReadPropertyBoolean('timezone') && !$this->GetValue(self::IDENT_TIMEZONE)) {
                $this->GetTimezone();
            }

            // update maps status
            if ($this->ReadPropertyBoolean(self::PROPERTY_MAP_STATUS)) {
                if (in_array(Features::MULTI_FLOOR_SUPPORT, $this->device::FEATURES, true)) {
                    $this->RequestData('get_multi_maps_list');
                } else {
                    $this->UpdateAttributeMapsListWithMaps(self::SINGLE_MAP);
                }
            }

            // update maps picture
            /* deaktiviert da zu viele Aufrufe
            if ($this->ReadPropertyBoolean(self::PROPERTY_MAP_PICTURE)) {
                $this->GetMap();
            }
            */

            if (in_array($this->GetValue(self::IDENT_STATE), [4, 5, 6, 7, 11, 15, 16, 17, 18, 26], true)) {
                $this->SetTimerInterval(self::TIMER_UPDATE_MAP, 10000);
            } elseif ($this->GetTimerInterval(self::TIMER_UPDATE_MAP) !== 0) {
                $this->SetTimerInterval(self::TIMER_UPDATE_MAP, 0);
                // update clean summary
                if ($this->ReadPropertyBoolean(self::PROPERTY_CLEAN_TIME)) {
                    $this->GetCleanSummary();
                }
            }
        }
        $this->_debug(__FUNCTION__ . ': finish');
    }

    /**
     * Send request to parent instance.
     *
     * @param string $method
     * @param array  $options
     *
     * @return array|bool
     */
    public function RequestRawData(string $method, array $options = []): array|bool
    {
        // build payload
        $payload = [
            'InstanceID' => $this->InstanceID,
            'token'      => $this->ReadAttributeString(self::ATTRIBUTE_TOKEN),
            'ip'         => $this->ReadPropertyString(self::PROPERTY_IP),
            'immediate'  => true,
            'method'     => $method,
            'params'     => []
        ];

        // merge payload & options
        $buffer = array_merge($payload, $options);

        // send to i/o device
        $this->_debug('send', json_encode($buffer, JSON_THROW_ON_ERROR));

        $data = json_encode(['DataID' => '{F7DC50D6-DCE6-27CE-49B2-A363593EBB3B}', 'Buffer' => $buffer], JSON_THROW_ON_ERROR);
        if ($io = @$this->SendDataToParent($data)) {
            // return data
            return json_decode($io, true, 512, JSON_THROW_ON_ERROR);
        }

        return false;
    }

    /**
     * Send request to parent instance.
     *
     * @param string $method
     * @param array  $options
     *
     * @return array|bool|string|int|null
     * @throws \JsonException
     */
    private function RequestData(string $method, array $options = []): array|bool|string|int|null
    {
        // build payload
        $payload = [
            'InstanceID' => $this->InstanceID,
            'token'      => $this->ReadAttributeString(self::ATTRIBUTE_TOKEN),
            'ip'         => $this->ReadPropertyString(self::PROPERTY_IP),
            'immediate'  => false,
            'method'     => $method,
            'params'     => []
        ];

        // force immediate option on ips sender

        //wenn ein Aufruf direkt erfolgt und nicht aus der Instanz heraus, dann soll er sofort ausgeführt werden /** @noinspection PhpUndefinedVariableInspection */
        //$this->SendDebug('IPS', json_encode($_IPS, JSON_THROW_ON_ERROR), 0); /** @noinspection PhpUndefinedVariableInspection */
        /** @global array $_IPS */
        if (($_IPS['SELF'] > 0 && $_IPS['SELF'] !== $this->InstanceID)
            || in_array($_IPS['SENDER'], ['Execute', 'Variable', 'RunScript', 'PHPModule'])) {
            $payload['immediate'] = true;
        }

        // merge payload & options
        $buffer = array_merge($payload, $options);

        // send to i/o device
        $this->_debug('send', json_encode($buffer, JSON_THROW_ON_ERROR));

        $data = json_encode(['DataID' => '{F7DC50D6-DCE6-27CE-49B2-A363593EBB3B}', 'Buffer' => $buffer], JSON_THROW_ON_ERROR);
        if ($io_json = @$this->SendDataToParent($data)) {
            // receive data on immediately requests
            $this->_debug('send (return)', $io_json);

            if ($buffer['immediate']) {
                $io = json_decode($io_json, true, 512, JSON_THROW_ON_ERROR);
                if ($io) {
                    // merge buffer
                    $data = array_merge($buffer, $io);

                    // return data
                    return $this->ExecuteCallback($data);
                }
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Receive and update data.
     *
     * @param string $JSONString
     *
     * @return string
     * @throws \JsonException
     */
    public function ReceiveData(string $JSONString): string
    {
        //$this->SendDebug(__FUNCTION__ . ': JSONString', $JSONString, 0);
        // convert json payload to array
        $payload = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);

        // extract buffer
        $buffer = $payload['Buffer'];

        // check token and save, if diffs from current one
        $current_token = $this->ReadAttributeString(self::ATTRIBUTE_TOKEN);
        if (isset($buffer['token']) && ($buffer['token'] !== $current_token) && (strlen($buffer['token']) === 32)) {
            $this->WriteAttributeString(self::ATTRIBUTE_TOKEN, $buffer['token']);
        }

        // execute callback
        if (is_array($buffer)) {
            $this->ExecuteCallback($buffer);
        }
        return '';
    }

    /**
     * Check if a callback exist and execute method.
     *
     * @param array $buffer
     *
     * @return array|string|int|bool|null
     * @throws \JsonException
     */
    private function ExecuteCallback(array $buffer): array|string|int|bool|null
    {
        // check if callback exists
        $callback = strtr(strtolower($buffer['method']), ['.' => '_']) . '_callback';
        if (method_exists($this, $callback)) {
            $this->_debug('receive', $callback . ': ' . json_encode($buffer, JSON_THROW_ON_ERROR));
            return $this->$callback($buffer);
        }

        $this->_debug('receive - no callback', $buffer['method'] . ': ' . json_encode($buffer, JSON_THROW_ON_ERROR));

        // return original buffer, when no callback was found
        $this->_debug('receive', json_encode($buffer, JSON_THROW_ON_ERROR));
        return $buffer;
    }

    /**
     * validate token.
     *
     * @return bool
     */
    private function ValidateToken(): bool
    {
        $token = $this->ReadAttributeString(self::ATTRIBUTE_TOKEN);

        // convert token on 96 byte length
        if (strlen($token) === 96) {
            $secret = str_repeat("\0", 16);
            $token  = openssl_decrypt(hex2bin($token), 'aes-128-ecb', $secret, OPENSSL_RAW_DATA);

            // save attribute
            $this->WriteAttributeString(self::ATTRIBUTE_TOKEN, $token);
        }

        // return true, when token length is 32 byte
        return strlen($token) === 32;
    }

    /**
     * start cleaning.
     *
     * @return void
     * @throws \JsonException
     */
    public function Start(): void
    {
        $this->_SetValue(self::IDENT_COMMAND, 0);
        $this->RequestData('app_start');
    }

    /**
     * stop cleaning.
     *
     * @return void
     * @throws \JsonException
     */
    public function Stop(): void
    {
        $this->_SetValue(self::IDENT_COMMAND, 2);
        $this->RequestData('app_stop');
    }

    /**
     * start spot cleaning.
     *
     * @return void
     * @throws \JsonException
     */
    public function CleanSpot(): void
    {
        $this->_SetValue(self::IDENT_COMMAND, 3);
        $this->RequestData('app_spot');
    }

    /**
     * pause cleaning.
     *
     * @return void
     * @throws \JsonException
     */
    public function Pause(): void
    {
        $this->_SetValue(self::IDENT_COMMAND, 1);
        $this->RequestData('app_pause');
    }

    /**
     * return to dock.
     *
     * @return void
     * @throws \JsonException
     */
    public function Charge(): void
    {
        $this->_SetValue(self::IDENT_COMMAND, 4);
        $this->RequestData('app_charge');
    }

    /**
     * locate vacuum cleaner by voice message.
     *
     * @return void
     */
    public function Locate(): void
    {
        $this->_SetValue(self::IDENT_COMMAND, 5);
        $this->RequestData('find_me');
    }

    public function StartCleaning(): void
    {
        $roomSelection  = json_decode($this->ReadAttributeString(self::ATTRIBUTE_ROOM_SELECTION), true, 512, JSON_THROW_ON_ERROR);
        $segments       = array_keys($roomSelection);
        $cleaningCycles = (int)$this->GetValue(self::IDENT_CLEANING_CYCLES);
        if ($cleaningCycles === 1) {
            $this->Start_Segment_Clean_Ex(json_encode($segments, JSON_THROW_ON_ERROR));
        } else {
            $this->Start_Segment_Clean_Ex(json_encode([['segments' => $segments, 'repeat' => $cleaningCycles]], JSON_THROW_ON_ERROR));
        }
    }
    // Consumables time remaining in %

    /**
     * get consumables time remaining in %.
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function Get_Consumables(): array|bool
    {
        return $this->RequestData('get_consumable');
    }

    /**
     * reset conmsumables.
     *
     * @param string $part filter|mainbrush|sidebrush|sensors
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function Reset_Consumable(string $part): array|bool
    {
        return $this->RequestData('reset_consumable', [
            'params' => [$part]
        ]);
    }

    /**
     * reset filter.
     *
     * @return array|bool
     */
    public function Reset_Filter(): array|bool
    {
        return $this->Reset_Consumable($this->device::CONSUMABLES[Consumable::FILTER]);
    }

    /**
     * reset mainbrush.
     *
     * @return array|bool
     */
    public function Reset_Mainbrush(): array|bool
    {
        return $this->Reset_Consumable($this->device::CONSUMABLES[Consumable::MAINBRUSH]);
    }

    /**
     * reset sidebrush.
     *
     * @return array|bool
     */
    public function Reset_Sidebrush(): array|bool
    {
        return $this->Reset_Consumable($this->device::CONSUMABLES[Consumable::SIDEBRUSH]);
    }

    /**
     * reset sensor.
     *
     * @return array|bool
     */
    public function Reset_Sensors(): array|bool
    {
        return $this->Reset_Consumable($this->device::CONSUMABLES[Consumable::SENSOR]);
    }

    /**
     * get clean summary.
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function GetCleanSummary(): array|bool
    {
        return $this->RequestData('get_clean_summary');
    }

    /**
     * get clean record by record id.
     *
     * @param int|array $record_id
     *
     */
    private function GetCleanRecord(int|array $record_id): void
    {
        $this->RequestData('get_clean_record', [
            'params' => is_array($record_id) ? $record_id : [$record_id]
        ]);
    }

    /**
     * get clean record map.
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function GetCleanRecordMap(): array|bool
    {
        return $this->RequestData('get_clean_record_map');
    }

    private function loadMapFileFromFile(string $filename): bool
    {
        if (file_exists($filename)) {
            $result = file_get_contents($filename);
        } else {
            $this->_debug(__FUNCTION__, sprintf('File does not exist: %s', $filename));
            return false;
        }
        if ($result) {
            $data = gzdecode($result);
        } else {
            $this->_debug(__FUNCTION__, sprintf('gzdecode failed: %s', $filename));
            return false;
        }

        ini_set('memory_limit', '48M');
        $pic = new RRMapFileParser($data, function (string $message, string $data) {
            $this->_debug($message, $data);
        });
        if (!$pic->isValid()) {
            $this->_debug(__FUNCTION__, sprintf('pic is invalid: %s', $filename));
            return false;
        }

        $draw    = new RRMapDraw($pic);
        $picture = $draw->getImage($this->ReadPropertyInteger(self::PROPERTY_MAP_PICTURE_SCALE) / 100);

        if ($picture === '') {
            $this->_debug(__FUNCTION__, sprintf('picture is empty: %s', $filename));
            return false;
        }
        $this->_debug(__FUNCTION__, 'fertig');
        $this->CreateMapPictureVariable(self::IDENT_MAP_PICTURE_FILE, 'Karte aus Datei', sprintf('Map_File_%s.png', $this->InstanceID));

        return IPS_SetMediaContent(IPS_GetObjectIDByIdent(self::IDENT_MAP_PICTURE_FILE, $this->InstanceID), base64_encode($picture));
    }

    private function getMapdata(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        $this->_debug(__FUNCTION__, sprintf('curl_exec: %s', $result === false ? 'false' : $result));

        $responsecode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->_debug(__FUNCTION__, sprintf('curl_getinfo: %s', $responsecode));

        curl_close($ch);
        if ($responsecode !== 200) {
            $this->_debug(
                __FUNCTION__,
                sprintf('%s: responsecode: %s, curl_getinfo: %s', __FUNCTION__,
                        json_encode($responsecode, JSON_THROW_ON_ERROR),
                        json_encode(curl_getinfo($ch), JSON_THROW_ON_ERROR)
                )
            );
            return '';
        }

        //$fp = fopen('data1.gz', 'wb');
        //fwrite($fp, $result);
        //fclose($fp);

        return gzdecode($result);
    }

    /**
     * get map.
     *
     * @return bool
     */
    public function GetMap(): bool
    {
        /*
        if (!$this->ReadPropertyBoolean(self::PROPERTY_MAP_PICTURE)) {
            return false;
        }
        */
        //$url = $this->ReadAttributeString(self::ATTRIBUTE_MAPFILE_URL);
        $url = '';

        if ($url) {
            parse_str(parse_url($url, PHP_URL_QUERY), $output);
            if ($output['Expires'] / 1000 < time()) {
                $url = '';
            } else {
                $this->_debug(__FUNCTION__ . 'URL (cached)', $url);
            }
        }

        if (!$url) {
            //trigger_error('get_map_v1 wurde aufgerufen. Die Anzahl der Zugriffe ist vermutlich pro Tag (?) beschränkt', E_USER_WARNING);
            $count = 0;
            do {
                $mapName = $this->RequestData('get_map_v1');
                $this->_debug(__FUNCTION__, sprintf('mapName: %s', json_encode($mapName, JSON_THROW_ON_ERROR)));
                $count++;
            } while ((!$mapName || ((string)$mapName === 'retry')) && $count < 3);

            if ($mapName === 'retry') {
                //SetValueInteger(24034, GetValueInteger(24034) - 1);
                return false;
            }
            //SetValueInteger(24034, GetValueInteger(24034) + 1);

            $data = $this->getApiIO('/home/getmapfileurl', ['obj_name' => $mapName]);

            $this->_debug(__FUNCTION__, sprintf('getmapfile: %s', json_encode($data, JSON_THROW_ON_ERROR)));

            if (!isset($data['result']['url'])) {
                return false;
            }
            $url = ($data['result']['url']);

            $this->_debug(__FUNCTION__ . 'URL', $url);
            $this->WriteAttributeString(self::ATTRIBUTE_MAPFILE_URL, $url);
        }


        $data = $this->getMapdata($url);
        //var_dump($data);
        if (!$data) {
            return false;
        }

        //$data = $this->loadMapFileFromFile(IPS_GetKernelDir() . 'logs\s7karte');

        ini_set('memory_limit', '48M');
        $pic = new RRMapFileParser($data, function (string $message, string $data) {
            $this->_debug($message, $data);
        });
        if (!$pic->isValid()) {
            return false;
        }

        $draw    = new RRMapDraw($pic);
        $picture = $draw->getImage($this->ReadPropertyInteger(self::PROPERTY_MAP_PICTURE_SCALE) / 100);

        if ($picture === '') {
            return false;
        }

        $this->CreateMapPictureVariable(self::IDENT_MAP_PICTURE, 'Map', sprintf('Map_%s.png', $this->InstanceID));

        IPS_SetMediaContent(IPS_GetObjectIDByIdent(self::IDENT_MAP_PICTURE, $this->InstanceID), base64_encode($picture));

        return true;
    }

    private function CreateMapPictureVariable(string $ident, string $name, string $FilePath = ''): void
    {
        if (!@IPS_GetObjectIDByIdent($ident, $this->InstanceID)) {
            $media_id = IPS_CreateMedia(MEDIATYPE_IMAGE);
            IPS_SetMediaFile($media_id, $FilePath, false);
            IPS_SetParent($media_id, $this->InstanceID);
            IPS_SetIdent($media_id, $ident);
            IPS_SetName($media_id, $this->Translate($name));
        }
    }

    /**
     * get current state.
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function Get_State(): array|false
    {
        return $this->RequestData('get_status');
    }

    /**
     * get serial number.
     *
     * @return array|bool
     */
    public function Get_Serial_Number(): string|bool
    {
        return $this->RequestData('get_serial_number');
    }

    /**
     * get current dnd mode.
     *
     * @return array|bool
     */
    public function Get_DND_Mode(): array|bool
    {
        return $this->RequestData('get_dnd_timer');
    }

    /**
     * set dnd timer, 24 hour notation.
     *
     * @param int $starthour
     * @param int $startminutes
     * @param int $endhour
     * @param int $endminutes
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function SetDNDTimer(int $starthour, int $startminutes, int $endhour, int $endminutes): array|bool
    {
        return $this->RequestData('set_dnd_timer', [
            'params' => [
                $starthour,
                $startminutes,
                $endhour,
                $endminutes
            ]
        ]);
    }

    /**
     * disable dnd mode.
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function DisableDND(): array|bool
    {
        return $this->RequestData('close_dnd_timer');
    }

    /**
     * Set Timer.
     *
     * @param int    $hour       two digits
     * @param int    $minute     two digits
     * @param string $repetition once|weekdays|weekends|every day
     *
     * @return array|bool
     */
    public function Set_Timer(int $hour, int $minute, string $repetition): array|bool
    {
        $timerid = time();
        return $this->RequestData('set_timer', [
            'params' => [[$timerid, [$minute . ' ' . $hour . ' * * ' . $this->_getTimerRepetition($repetition), ['start_clean', '']]]]
        ]);
    }

    /**
     * enable timer.
     *
     * @param string $timerid
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function EnableTimer(string $timerid): array|bool
    {
        return $this->RequestData('upd_timer', [
            'params' => [$timerid, 'on']
        ]);
    }

    /**
     * disable timer.
     *
     * @param string $timerid
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function DisableTimer(string $timerid): array|bool
    {
        return $this->RequestData('upd_timer', [
            'params' => [$timerid, 'off']
        ]);
    }

    /**
     * get timer details.
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function Get_Timer_Details(): array|bool
    {
        return $this->RequestData('get_timer');
    }

    /**
     * delete a timer.
     *
     * @param string $timerid
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function DeleteTimer(string $timerid): array|bool
    {
        return $this->RequestData('del_timer', [$timerid]);
    }

    /**
     * get timezone.
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function GetTimezone(): array|bool
    {
        return $this->RequestData('get_timezone');
    }

    /**
     * set timezone to europe.
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function SetTimezoneEurope(): array|bool
    {
        return $this->RequestData('set_timezone', ['Europe/Amsterdam']);
    }

    /**
     * install *.pkg sound package by url.
     *
     * @param string $sound_url
     *
     * @return array|bool
     * @throws \JsonException
     */
    protected function InstallSound(string $sound_url): array|bool
    {
        return $this->RequestData('dnld_install_sound', [
            'params' => [$sound_url]
        ]);
    }

    /**
     * set sound level.
     *
     * @param int $level
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function SetSoundLevel(int $level): array|bool
    {
        return $this->RequestData('get_current_sound', [
            'params' => [$level]
        ]);
    }

    /**
     * Get fan power.
     *
     * @return array|bool
     */
    public function Get_Fan_Power(): array|bool
    {
        return $this->RequestData('get_custom_mode');
    }

    /**
     * set fan power (Quiet=38, Balanced=60, Turbo=77, Full Speed=90).
     *
     * @param int $power
     *
     * @return void
     * @throws \JsonException
     */
    public function Set_Fan_Power(int $power): void
    {
        $this->_SetValue(self::IDENT_FAN_POWER, $power);
        $this->RequestData('set_custom_mode', [
            'params' => [$power]
        ]);
    }

    /**
     * Get the water quantity control during the cleaning process.
     *
     * @return array|bool
     */
    public function Get_Water_Quantity_Control(): array|bool
    {
        return $this->RequestData('get_water_box_custom_mode');
    }

    /**
     * set the water quantity control during the cleaning process. (Quiet=38, Balanced=60, Turbo=77, Full Speed=90).
     *
     * @param int $mode
     *
     * @return array|bool
     */
    public function Set_Water_Quantity_Control(int $mode): array|bool
    {
        $this->_SetValue(self::IDENT_WATER_QUANTITY, $mode);
        return $this->RequestData('set_water_box_custom_mode', [
            'params' => [$mode]
        ]);
    }

    /**
     * move robot to direction.
     *
     * @param int $direction -100..100
     * @param int $velocity  0..100
     * @param int $time      in ms
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function Move_Direction(int $direction, int $velocity, int $time = 1000): array|bool
    {
        $this->StartRemoteControl();
        $result = $this->RequestData('app_rc_move', [
            'params' => [
                'omega'    => $direction,
                'velocity' => $velocity,
                'seqnum'   => 'sequence',
                'duration' => $time
            ]
        ]);
        $this->StopRemoteControl();

        return $result;
    }

    /**
     * load map
     *
     * @param int $mapIndex
     *
     * @return bool
     */
    public function LoadMap(int $mapIndex): bool
    {
        if ($this->RequestData('load_multi_map', ['params' => [$mapIndex]])) {
            $this->ProcessSelectedRoom(0); //Auswahl auf 'alle' setzen

            return true;
        }

        return false;
    }


    /**
     * start remote control.
     *
     */
    private function StartRemoteControl(): void
    {
        $this->RequestData('app_rc_start');
    }

    /**
     * stop remote control.
     *
     * @return void
     * @throws \JsonException
     */
    protected function StopRemoteControl(): void
    {
        $this->RequestData('app_rc_end');
    }

    /**
     * Roborock Vacuum 2 clean zone with coordinates for area, use a rectangle with values for the lower left corner and the upper right corner.
     *
     * @param int $lower_left_corner_x
     * @param int $lower_left_corner_y
     * @param int $upper_right_corner_x
     * @param int $upper_right_corner_y
     * @param int $number
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function ZoneClean(
        int $lower_left_corner_x,
        int $lower_left_corner_y,
        int $upper_right_corner_x,
        int $upper_right_corner_y,
        int $number
    ): array|bool {
        return $this->RequestData('app_zoned_clean', [
            'params' => [
                [
                    $lower_left_corner_x,
                    $lower_left_corner_y,
                    $upper_right_corner_x,
                    $upper_right_corner_y,
                    $number
                ]
            ]
        ]);
    }

    public function ZoneCleanRoomname(string $roomname, int $number): array|bool
    {
        $zones  = $this->GetZones();
        $zoneid = -1;
        foreach ($zones as $key => $zone) {
            if ($zone['roomname'] === $roomname) {
                $zoneid = $key;
            }
        }
        if ($zoneid > -1) {
            $zone = $zones[$zoneid];
            $this->_debug('ZoneClean', 'room: ' . $zone['roomname']);
            $lower_left_corner_x  = $zone['lx'];
            $lower_left_corner_y  = $zone['ly'];
            $upper_right_corner_x = $zone['ux'];
            $upper_right_corner_y = $zone['uy'];
            $this->_debug(
                'ZoneClean',
                'left x: ' . $lower_left_corner_x . ', left y: ' . $lower_left_corner_y . ', right x: ' . $upper_right_corner_x . ', right y: '
                . $upper_right_corner_y
            );
            $result = $this->ZoneClean($lower_left_corner_x, $lower_left_corner_y, $upper_right_corner_x, $upper_right_corner_y, $number);
        } else {
            $this->_debug('ZoneClean', 'could not find roomname');
            $result = false;
        }
        return $result;
    }

    public function ZoneCleanRoomnumber(int $roomnumber, int $number): array|bool
    {
        $zones      = $this->GetZones();
        $zoneid     = $roomnumber - 1;
        $zonenumber = $this->GetNumberZones() - 1;
        if ($zonenumber < $roomnumber) {
            $zone = $zones[$zoneid];
            $this->_debug('ZoneClean', 'room: ' . $zone['roomname']);
            $lower_left_corner_x  = $zone['lx'];
            $lower_left_corner_y  = $zone['ly'];
            $upper_right_corner_x = $zone['ux'];
            $upper_right_corner_y = $zone['uy'];
            $this->_debug(
                'ZoneClean',
                'left x: ' . $lower_left_corner_x . ', left y: ' . $lower_left_corner_y . ', right x: ' . $upper_right_corner_x . ', right y: '
                . $upper_right_corner_y
            );
            $result = $this->ZoneClean($lower_left_corner_x, $lower_left_corner_y, $upper_right_corner_x, $upper_right_corner_y, $number);
        } else {
            $this->_debug('ZoneClean', 'could not find roomnumber');
            $result = false;
        }
        return $result;
    }

    /** Roborock Vacuum 2 clean multiple zone with coordinates for area, use a rectangle with values for the lower left corner and the upper right corner
     * $multizone = '[['.$lower_left_corner_x.','. $lower_left_corner_y.','. $upper_right_corner_x.','. $upper_right_corner_y.','. $number.'],['.
     * $lower_left_corner_x1.','. $lower_left_corner_y1.','.    $upper_right_corner_x1.','. $upper_right_corner_y1.','. $number.']]';.
     *
     * @param string $multizone
     *
     * @return array|bool
     */
    public function ZoneCleanMulti(string $multizone): array|bool
    {
        $multizone = json_decode($multizone, true, 512, JSON_THROW_ON_ERROR);
        return $this->RequestData('app_zoned_clean', [
            'params' => $multizone
        ]);
    }

    /** Roborock Vacuum 2 clean multiple zone with coordinates for area, use a rectangle with values for the lower left corner and the upper right corner
     *
     * @param string $multizone
     *
     * @return array|bool
     */
    public function ZoneCleanMultiName(string $multizone): array|bool
    {
        $multizone     = json_decode($multizone, true, 512, JSON_THROW_ON_ERROR);
        $command_zones = [];
        foreach ($multizone as $zone) {
            $command_zones[] = [$zone[0][0], $zone[0][1], $zone[0][2], $zone[0][3], $zone[1]];
        }
        return $this->RequestData('app_zoned_clean', [
            'params' => $command_zones
        ]);
    }

    /**
     * Roborock Vacuum 2 go to coordinates.
     *
     * @param int $x
     * @param int $y
     *
     * @return void
     * @throws \JsonException
     */
    public function GotoTarget(int $x, int $y): void
    {
        $this->RequestData('app_goto_target', [
            'params' => [
                $x,
                $y
            ]
        ]);
    }

    /**
     * get device info.
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function GetDeviceInfo(): array|bool
    {
        return $this->RequestData('miIO.info');
    }

    /**
     * toggle remote control.
     *
     * @param bool $state
     */
    public function Toggle_State(bool $state): void
    {
        if ($state) {
            $this->Start();
        } else {
            $this->Stop();
        }
    }


    /**
     * enable / disable dnd mode.
     *
     * @param bool $state
     */
    public function Set_DND(bool $state): void
    {
        $this->_SetValue('dnd_mode', $state);

        if ($state) {
            $start_time_string = GetValueFormatted($this->GetIDForIdent('dnd_starttime'));
            $time              = explode(':', $start_time_string);
            $start_hour        = (int)$time[0];
            $start_minutes     = (int)$time[1];
            $end_time_string   = GetValueFormatted($this->GetIDForIdent('dnd_endtime'));
            $time              = explode(':', $end_time_string);
            $end_hour          = (int)$time[0];
            $end_minutes       = (int)$time[1];
            $this->SetDNDTimer($start_hour, $start_minutes, $end_hour, $end_minutes);
        } else {
            $this->DisableDND();
        }
    }

    /**
     * set dnd start time.
     *
     * @param string $starttime
     */
    public function Set_DND_Start(string $starttime): void
    {
        $unixtime = strtotime($starttime);
        $this->Set_DND_StartInt($unixtime);
    }

    protected function Set_DND_StartInt($starttime): void
    {
        $start_hour    = (int)date('H', $starttime);
        $start_minutes = (int)date('i', $starttime);
        $this->_SetValue('dnd_starttime', $starttime);
        $end_time_string = GetValueFormatted($this->GetIDForIdent('dnd_endtime'));
        $time            = explode(':', $end_time_string);
        $end_hour        = (int)$time[0];
        $end_minutes     = (int)$time[1];
        $this->SetDNDTimer($start_hour, $start_minutes, $end_hour, $end_minutes);
    }

    /**
     * set dnd end time.
     *
     * @param string $endtime
     */
    public function Set_DND_End(string $endtime): void
    {
        $unixtime = strtotime($endtime);
        $this->Set_DND_EndInt($unixtime);
    }

    protected function Set_DND_EndInt($endtime): void
    {
        $end_hour    = (int)date('H', $endtime);
        $end_minutes = (int)date('i', $endtime);
        $this->_SetValue('dnd_endtime', $endtime);
        $starttime     = GetValueFormatted($this->GetIDForIdent('dnd_starttime'));
        $time          = explode(':', $starttime);
        $start_hour    = (int)$time[0];
        $start_minutes = (int)$time[1];
        $this->SetDNDTimer($start_hour, $start_minutes, $end_hour, $end_minutes);
    }


    /**
     * get sound volume.
     *
     * @return void
     * @throws \JsonException
     */
    private function Get_SoundVolume(): void
    {
        $this->RequestData('get_sound_volume');
    }

    /**
     * set sound volume.
     *
     * @param int $volume
     *
     * @return void
     * @throws \JsonException
     */
    private function Set_SoundVolume(int $volume): void
    {
        $this->_SetValue(self::IDENT_VOLUME, $volume);
        $this->RequestData('change_sound_volume', [
            'params' => [$volume]
        ]);
    }

    /**
     * Roborock Vacuum 1S segment clean.
     *
     * @param int $segmentid
     *
     * @return void
     * @throws \JsonException
     */
    public function Start_Segment_Clean(int $segmentid): void
    {
        $this->RequestData('app_segment_clean', [
            'params' => [
                $segmentid
            ]
        ]);
    }

    /**
     * segment clean Ex
     *
     * @param string $segmentIds json encoded array of segmentids
     *
     * @return void
     * @throws \JsonException
     */
    public function Start_Segment_Clean_Ex(string $segmentIds): void
    {
        $this->RequestData('app_segment_clean', [
            'params' => json_decode($segmentIds, true, 512, JSON_THROW_ON_ERROR)
        ]);
    }

    /**
     * get room mapping
     *
     * @return array
     */
    public function Get_Room_Mapping(): array
    {
        if ($mapping = $this->RequestData('get_room_mapping', ['immediate' => true])) {
            return $mapping['result'];
        }

        return [];
    }

    /**
     * webfront request actions.
     *
     * @param string $Ident
     * @param mixed  $Value
     *
     * @return void
     * @throws \JsonException
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->_debug(__FUNCTION__, sprintf('Ident: %s, Value: %s', $Ident, $Value));
        switch ($Ident) {
            case self::IDENT_COMMAND:
                switch ($Value) {
                    case 0:
                        $this->Start();
                        break;
                    case 1:
                        $this->Pause();
                        break;
                    case 2:
                        $this->Stop();
                        break;
                    case 3:
                        $this->CleanSpot();
                        break;
                    case 4:
                        $this->Charge();
                        break;
                    case 5:
                        $this->Locate();
                        break;
                }
                break;
            case self::IDENT_ROOMSELECTION:
                $this->ProcessSelectedRoom($Value);
                $this->_SetValue($Ident, -1);
                break;
            case 'dnd_mode':
                $this->Set_DND($Value);
                break;
            case 'dnd_starttime':
                $this->Set_DND_StartInt($Value);
                break;
            case 'dnd_endtime':
                $this->Set_DND_EndInt($Value);
                break;
            case self::IDENT_VOLUME:
                $this->Set_SoundVolume($Value);
                break;
            case self::IDENT_FAN_POWER:
                $this->Set_Fan_Power($Value);
                break;
            case self::IDENT_WATER_QUANTITY:
                $this->Set_Water_Quantity_Control($Value);
                break;
            case self::IDENT_MAP_STATUS:
                $this->LoadMap($Value);
                break;
            case self::IDENT_CLEANING_CYCLES:
                $this->_SetValue($Ident, $Value);
                break;
            case self::IDENT_START_CLEANING:
                $this->StartCleaning();
                break;
            case 'ReloadForm':
                $this->ReloadForm();
                break;
            case 'LoadMapFile':
                $this->loadMapFileFromFile($Value);
                break;
            case 'SendPushNotificationTest':
                $this->SendPushNotification('state', (int)StateCode::CLEANING); //CLEANING
                break;
            case 'UpdateMapsAndRooms':
                $this->UpdateFormField(self::FF_MAPANDROOMLIST, 'enabled', false); //Eingabe deaktivieren

                if (in_array(Features::MULTI_FLOOR_SUPPORT, $this->device::FEATURES, true)) {
                    $this->RequestData('get_multi_maps_list', ['immediate' => true]);
                } else {
                    $this->UpdateAttributeMapsListWithMaps(self::SINGLE_MAP);
                }
                $this->RequestData('get_room_mapping', ['immediate' => true]);

                $this->UpdateFormField(self::FF_MAPANDROOMLIST, 'enabled', true); //Eingabe wieder aktivieren
                $this->UpdateFormField(self::FF_MAPANDROOMLIST, 'values', json_encode($this->GetMapAndRoomListFormValues(), JSON_THROW_ON_ERROR));
                break;
            case 'DeleteProp':
                $this->WriteAttributeString(self::ATTRIBUTE_MAPS_LIST, json_encode([], JSON_THROW_ON_ERROR));
                break;
            case 'GetProp':
                $this->_debug(__FUNCTION__, $this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST));
                break;
            case self::IDENT_UPDATEROOMNAME:
                $RoomValues = json_decode($Value, true, 512, JSON_THROW_ON_ERROR);

                //aktualisieren des Raumnamens
                $Texts = json_decode($this->ReadAttributeString(self::ATTRIBUTE_ROOM_NAMES), true, 512, JSON_THROW_ON_ERROR);

                $Texts[$RoomValues[self::FF_COL_ROOMTEXTREFERENCE]] = $RoomValues[self::FF_COL_ROOMNAME]; //update Name of Room

                $this->WriteAttributeString(self::ATTRIBUTE_ROOM_NAMES, json_encode($Texts, JSON_THROW_ON_ERROR));

                //aktualisieren des Ignore Flags
                $savedMapsList = json_decode($this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST), true, 512, JSON_THROW_ON_ERROR);
                //$map = $savedMapsList[$]
                $savedMapsList[$RoomValues[self::FF_COL_PARENT_MAP_ID]]['rooms'][$RoomValues[self::FF_COL_ROOMID]][self::FF_COL_IGNORE_ROOM] =
                    $RoomValues[self::FF_COL_IGNORE_ROOM];
                $this->WriteAttributeString(self::ATTRIBUTE_MAPS_LIST, json_encode($savedMapsList, JSON_THROW_ON_ERROR));

                $this->WriteRoomSelectionProfile();
                $this->UpdateRoomsSelected();

                break;
            default:
                $this->_debug('request action', sprintf('Invalid Ident <%s>, Value: %s', $Ident, $Value));
        }
    }

    private function ProcessSelectedRoom(int $roomId): void
    {
        $roomSelection = json_decode($this->ReadAttributeString(self::ATTRIBUTE_ROOM_SELECTION), true, 512, JSON_THROW_ON_ERROR);

        if ($roomId === 0) { // 0 = all
            $roomSelection = [];
        } else {
            $roomSelection[$roomId] = $roomId;
        }

        $this->WriteAttributeString(self::ATTRIBUTE_ROOM_SELECTION, json_encode($roomSelection, JSON_THROW_ON_ERROR));

        $this->UpdateRoomsSelected();
    }

    private function UpdateRoomsSelected(): void
    {
        $roomSelection = json_decode($this->ReadAttributeString(self::ATTRIBUTE_ROOM_SELECTION), true, 512, JSON_THROW_ON_ERROR);

        $objectID = IPS_GetObjectIDByIdent(self::IDENT_ROOMSELECTION, $this->InstanceID);

        $roomNames = [];

        if (count($roomSelection) === 0) { // all
            $roomNames[] = GetValueFormattedEx($objectID, 0);
        } else {
            foreach ($roomSelection as $roomId => $room) {
                $roomNames[] = GetValueFormattedEx($objectID, $roomId);
            }
        }
        $this->SetValue(self::IDENT_ROOMS_SELECTED, implode(', ', $roomNames));
    }

    private function WriteRoomSelectionProfile(): void
    {
        $roomNames = json_decode($this->ReadAttributeString(self::ATTRIBUTE_ROOM_NAMES), true, 512, JSON_THROW_ON_ERROR);
        $mapsList  = json_decode($this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST), true, 512, JSON_THROW_ON_ERROR);

        $ass = [[0, sprintf('- %s -', $this->Translate('None')), '', -1]];

        if (count($mapsList) > 0) {
            $mapStatus = $this->GetValue(self::IDENT_MAP_STATUS);

            foreach ($mapsList[$mapStatus]['rooms'] as $roomID => $room) {
                if (!isset($room['IgnoreRoom']) || !$room['IgnoreRoom']) {
                    $ass[] = [$roomID, $roomNames[$room['referenceID']] ?? $this->Translate('Room') . ' ' . $room['roomID'], '', -1];
                }
            }
        }

        $profileName = sprintf('%s.%s', self::PROFILE_ROOMSELECTION, $this->InstanceID);
        $this->_debug(__FUNCTION__, sprintf('profile: %s, ass: %s', $profileName, json_encode($ass, JSON_THROW_ON_ERROR)));
        $this->RegisterProfileAssociation($profileName, '', '', '', 0, 0, 0, 0, VARIABLETYPE_INTEGER, $ass);
    }

    /**
     * register profiles.
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $MinValue
     * @param $MaxValue
     * @param $StepSize
     * @param $Digits
     * @param $Vartype
     */
    protected function RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Vartype): void
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, $Vartype); // 0 boolean, 1 int, 2 float, 3 string,
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] !== $Vartype) {
                $this->_debug('profile', 'Variable profile type does not match for profile ' . $Name);
            }
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        if (!IPS_SetVariableProfileText($Name, $Prefix, $Suffix)) {
            $this->_debug('profile', sprintf('Name: %s, Prefix: %s, Suffix: %s', $Name, $Prefix, $Suffix));
        }
        IPS_SetVariableProfileDigits($Name, $Digits); //  Nachkommastellen
        IPS_SetVariableProfileValues(
            $Name,
            $MinValue,
            $MaxValue,
            $StepSize
        ); // string $ProfilName, float $Minimalwert, float $Maximalwert, float $Schrittweite
    }

    /**
     * register profile association.
     *
     * @param       $Name
     * @param       $Icon
     * @param       $Prefix
     * @param       $Suffix
     * @param       $MinValue
     * @param       $MaxValue
     * @param       $Stepsize
     * @param       $Digits
     * @param       $Vartype
     * @param array $Associations
     */
    protected function RegisterProfileAssociation(
        $Name,
        $Icon,
        $Prefix,
        $Suffix,
        $MinValue,
        $MaxValue,
        $Stepsize,
        $Digits,
        $Vartype,
        array $Associations
    ): void {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        }
        $this->RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype);

        //zunächst werden alte Assoziationen gelöscht
        foreach (IPS_GetVariableProfile($Name)['Associations'] as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association['Value'], '', '', -1);
        }

        //dann werden die aktuellen eingetragen
        foreach ($Associations as $Association) {
            $icon  = $Association[2] ?? '';
            $color = $Association[3] ?? -1;
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $icon, $color);
        }
    }

    /**
     * checks, if a token is available.
     *
     * @return bool
     */
    private function CheckUserAndPassword(): bool
    {
        // if token is valid, everything is ok
        if ($this->ReadAttributeString(self::ATTRIBUTE_TOKEN)) {
            return true;
        }

        if (// configuration is not finished
            !$this->ReadPropertyString(self::PROPERTY_XIAOMI_USER)
            || !$this->ReadPropertyString(self::PROPERTY_XIAOMI_PASSWORD)
            || !$this->GetTokenFromXiaomi()) {
            return false;
        }

        return true;
    }


    /**
     * Send push notifications.
     *
     * @param string $type
     * @param int    $id
     * @param bool   $force_send
     *
     * @return void
     * @throws \JsonException
     */
    private function SendPushNotification(string $type, int $id = 0, bool $force_send = false): void
    {
        // get codes by state_id
        if ($type === 'error') {
            $prefix = $this->Translate('Error') . ': ';
            $error  = ErrorCode::tryFrom($id);
            if ($error) {
                $description = $this->Translate($error->getDescription());
            } else {
                $description = (string)$id;
            }

            $notification_attribute = self::ATTRIBUTE_LAST_NOTIFICATION_ERROR;
        } else {
            $prefix = '';
            $state  = StateCode::tryFrom($id);
            if ($state) {
                $description = $this->Translate($state->getDescription());
            } else {
                $description = (string)$id;
            }

            $notification_attribute = self::ATTRIBUTE_LAST_NOTIFICATION_STATE;
        }

        // check notification
        $last_notification = $this->ReadAttributeString($notification_attribute);
        $this->WriteAttributeString($notification_attribute, (string)$id);

        // return, when last notification is the same as current notification or id is 0
        if ((($last_notification === (string)$id) && !$force_send) || ($id === 0)) {
            return;
        }

        // check notification instance (webfront)
        // get notification settings
        if (($instance_id = $this->ReadPropertyInteger('notification_instance'))
            && $notifications = @json_decode($this->ReadPropertyString('notifications'), true, 512, JSON_THROW_ON_ERROR)) {
            // loop notifications and search for current state
            foreach ($notifications as $notification) {
                if ($notification['state_id'] === (string)$id) {
                    // check if notification is enabled
                    if ($notification['enabled'] || $force_send) {
                        // send notification
                        if ($id > 0) {
                            // build message
                            $title   = IPS_GetName($this->InstanceID); // instance name
                            $message = $prefix . $description;

                            // send notification
                            WFC_PushNotification($instance_id, $title, $message, $notification['sound'], 0);
                        }
                    }

                    // break loop
                    return;
                }
            }
        }
    }

    /**
     * Get push notifications.
     *
     * @return string json encoded settings
     */
    protected function GetPushNotifications(): string
    {
        // translate default notifications
        $notifications = self::PUSH_NOTIFICATIONS;

        foreach ($notifications as &$notification) {
            $notification['name'] = $this->Translate($notification['name']);
        }
        unset ($notification);

        // merge with current settings
        if ($current_notifications = @$this->ReadPropertyString('notifications')) {
            $current_notifications = json_decode($current_notifications, true, 512, JSON_THROW_ON_ERROR);
            foreach ($current_notifications as $current) {
                // loop and replace settings
                foreach ($notifications as &$n) {
                    if ($n['state_id'] === $current['state_id']) {
                        $n['sound']   = $current['sound'];
                        $n['enabled'] = $current['enabled'];

                        break;
                    }
                }
            }
        }

        return json_encode($notifications, JSON_THROW_ON_ERROR);
    }

    /***********************************************************
     * Configuration Form
     ***********************************************************/

    /**
     * build configuration form.
     *
     */
    public function GetConfigurationForm(): string
    {
        $form = json_encode([
                                'elements' => $this->FormElements(),
                                'actions'  => $this->FormActions(),
                                'status'   => $this->FormStatus()
                            ], JSON_THROW_ON_ERROR);


        $this->_debug('Form', $form);
        // return current form
        return $form;
    }

    /**
     * return form configurations on configuration step.
     *
     * @return array
     */
    private function FormElements(): array
    {
        $model = $this->ReadAttributeString(self::ATTRIBUTE_MODEL);
        if ($model !== '') {
            $model = $this->device->GetName(get_class($this->device));
        }

        return [
            [
                'type'  => 'RowLayout',
                'items' => [
                    [
                        'name'    => self::PROPERTY_IP,
                        'type'    => 'ValidationTextBox',
                        'caption' => 'IP address Roborock'
                    ],
                    [
                        'name'    => self::PROPERTY_MODEL,
                        'type'    => 'Label',
                        'caption' => $model,
                        'visible' => ($model !== '')
                    ]
                ]
            ],
            [
                'type'    => 'Label',
                'caption' => 'Enter the credentials of your Xiaomi Home Account below.'
            ],
            [
                'name'    => self::PROPERTY_XIAOMI_USER,
                'type'    => 'ValidationTextBox',
                'caption' => 'User'
            ],
            [
                'name'    => self::PROPERTY_XIAOMI_PASSWORD,
                'type'    => 'PasswordTextBox',
                'caption' => 'Password'
            ],
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'Optional Status Variables',
                'items'   => [
                    [
                        'name'    => self::PROPERTY_FAN_POWER,
                        'type'    => 'CheckBox',
                        'caption' => 'Fan Power'
                    ],
                    [
                        'name'    => self::PROPERTY_WATER_QUANTITY,
                        'type'    => 'CheckBox',
                        'caption' => 'Water Quantity'
                    ],
                    [
                        'name'    => self::PROPERTY_MAP_STATUS,
                        'type'    => 'CheckBox',
                        'caption' => 'Active Map',
                        'visible' => in_array(Features::MULTI_FLOOR_SUPPORT, $this->device::FEATURES, true)
                    ],
                    [
                        'type'  => 'RowLayout',
                        'items' => [
                            [
                                'name'    => self::PROPERTY_MAP_PICTURE,
                                'type'    => 'CheckBox',
                                'caption' => 'Map Picture'
                            ],
                            [
                                'name'    => self::PROPERTY_MAP_PICTURE_SCALE,
                                'type'    => 'NumberSpinner',
                                'visible' => $this->ReadPropertyBoolean(self::PROPERTY_MAP_PICTURE),
                                'caption' => 'Map Picture Scale',
                                'minumum' => 10,
                                'maximum' => 300,
                                'suffix'  => '%'
                            ]
                        ]
                    ],
                    [
                        'name'    => self::PROPERTY_CLEANING_ORDER,
                        'type'    => 'CheckBox',
                        'caption' => 'Cleaning Order (Active Map, Room Selection, selected Rooms, Cleaning Cycles, Start Cleaning)'
                    ],
                    [
                        'name'    => 'error_code',
                        'type'    => 'CheckBox',
                        'caption' => 'Error Code'
                    ],
                    [
                        'name'    => self::PROPERTY_CONSUMABLES,
                        'type'    => 'CheckBox',
                        'caption' => 'Consumables',
                        'visible' => in_array(Features::GET_CONSUMABLES, $this->device::FEATURES, true)
                    ],
                    [
                        'name'    => self::PROPERTY_CONSUMABLES_SEPARATE,
                        'type'    => 'CheckBox',
                        'caption' => 'Consumables (Separate Variables)',
                        'visible' => in_array(Features::GET_CONSUMABLES, $this->device::FEATURES, true)
                    ],
                    [
                        'name'    => 'dnd_mode',
                        'type'    => 'CheckBox',
                        'caption' => 'DND Mode (Do not disturb)'
                    ],
                    [
                        'name'    => 'clean_area',
                        'type'    => 'CheckBox',
                        'caption' => 'Clean Area'
                    ],
                    [
                        'name'    => self::PROPERTY_CLEAN_TIME,
                        'type'    => 'CheckBox',
                        'caption' => 'Clean Time'
                    ],
                    [
                        'name'    => 'total_cleans',
                        'type'    => 'CheckBox',
                        'caption' => 'Total Cleans'
                    ],
                    [
                        'name'    => 'serial_number',
                        'type'    => 'CheckBox',
                        'caption' => 'Serial Number'
                    ],
                    [
                        'name'    => 'timer_details',
                        'type'    => 'CheckBox',
                        'caption' => 'Timer Details'
                    ],
                    [
                        'name'    => 'extended_info',
                        'type'    => 'CheckBox',
                        'caption' => 'Extended Information (WLAN SSID, RSSI, firmware version, ip, model, mac)'
                    ],
                    [
                        'name'    => 'volume',
                        'type'    => 'CheckBox',
                        'caption' => 'Volume'
                    ],
                    [
                        'name'    => 'timezone',
                        'type'    => 'CheckBox',
                        'caption' => 'Timezone'
                    ],
                    [
                        'name'    => self::PROPERTY_REMOTE,
                        'type'    => 'CheckBox',
                        'caption' => 'Remote Control',
                        'visible' => false
                    ]
                ]
            ],
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'Push Notifications',
                'items'   => [
                    [
                        'name'    => 'notification_instance',
                        'type'    => 'SelectInstance',
                        'caption' => 'Webfront Configurator'
                    ],
                    [
                        'type'     => 'List',
                        'name'     => 'notifications',
                        'caption'  => 'Push Notifications',
                        'rowCount' => count(self::PUSH_NOTIFICATIONS),
                        'add'      => false,
                        'delete'   => false,
                        'sort'     => [
                            'column'    => 'name',
                            'direction' => 'ascending'
                        ],
                        'columns'  => [
                            [
                                'name'    => 'enabled',
                                'caption' => 'Enabled',
                                'width'   => '100px',
                                'edit'    => [
                                    'type'    => 'CheckBox',
                                    'caption' => 'Enable Push Notification'
                                ]
                            ],
                            [
                                'name'    => 'name',
                                'caption' => 'Notification',
                                'width'   => 'auto',
                                'save'    => true
                            ],
                            [
                                'name'    => 'sound',
                                'caption' => 'Notification Sound',
                                'width'   => '170px',
                                'edit'    => [
                                    'type'    => 'Select',
                                    'options' => [
                                        [
                                            'caption' => 'default',
                                            'value'   => ''
                                        ],
                                        [
                                            'caption' => 'alarm',
                                            'value'   => 'alarm'
                                        ],
                                        [
                                            'caption' => 'bell',
                                            'value'   => 'bell'
                                        ],
                                        [
                                            'caption' => 'boom',
                                            'value'   => 'boom'
                                        ],
                                        [
                                            'caption' => 'buzzer',
                                            'value'   => 'buzzer'
                                        ],
                                        [
                                            'caption' => 'connected',
                                            'value'   => 'connected'
                                        ],
                                        [
                                            'caption' => 'dark',
                                            'value'   => 'dark'
                                        ],
                                        [
                                            'caption' => 'digital',
                                            'value'   => 'digital'
                                        ],
                                        [
                                            'caption' => 'drums',
                                            'value'   => 'drums'
                                        ],
                                        [
                                            'caption' => 'duck',
                                            'value'   => 'duck'
                                        ],
                                        [
                                            'caption' => 'full',
                                            'value'   => 'full'
                                        ],
                                        [
                                            'caption' => 'happy',
                                            'value'   => 'happy'
                                        ],
                                        [
                                            'caption' => 'horn',
                                            'value'   => 'horn'
                                        ],
                                        [
                                            'caption' => 'inception',
                                            'value'   => 'inception'
                                        ],
                                        [
                                            'caption' => 'kazoo',
                                            'value'   => 'kazoo'
                                        ],
                                        [
                                            'caption' => 'roll',
                                            'value'   => 'roll'
                                        ],
                                        [
                                            'caption' => 'siren',
                                            'value'   => 'siren'
                                        ],
                                        [
                                            'caption' => 'space',
                                            'value'   => 'space'
                                        ],
                                        [
                                            'caption' => 'trickling',
                                            'value'   => 'trickling'
                                        ],
                                        [
                                            'caption' => 'turn',
                                            'value'   => 'turn'
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'name'    => 'state_id',
                                'caption' => 'State ID',
                                'width'   => 'auto',
                                'save'    => true,
                                'visible' => false
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'Zones',
                'visible' => false,   //zurzeit deaktiviert. Nutzen ist fraglich
                'items'   => [
                    [
                        'type'     => 'List',
                        'name'     => 'zonecoordinates',
                        'caption'  => 'zone coordinates',
                        'rowCount' => $this->GetNumberZones() + 2,
                        'add'      => true,
                        'delete'   => true,
                        'sort'     => [
                            'column'    => 'zone',
                            'direction' => 'ascending'
                        ],
                        'columns'  => [
                            [
                                'name'    => 'zone',
                                'caption' => 'zone',
                                'width'   => '100px',
                                'add'     => $this->GetZoneID(),
                                'save'    => true
                            ],
                            [
                                'name'    => 'roomname',
                                'caption' => 'room name',
                                'width'   => 'auto',
                                'add'     => 'room name',
                                'save'    => true,
                                'edit'    => [
                                    'type' => 'ValidationTextBox'
                                ]
                            ],
                            [
                                'name'    => 'lx',
                                'caption' => 'lower left corner x',
                                'width'   => '150px',
                                'add'     => 25000,
                                'save'    => true,
                                'edit'    => [
                                    'type' => 'NumberSpinner'
                                ]
                            ],
                            [
                                'name'    => 'ly',
                                'caption' => 'lower left corner y',
                                'width'   => '150px',
                                'add'     => 25000,
                                'save'    => true,
                                'edit'    => [
                                    'type' => 'NumberSpinner'
                                ]
                            ],
                            [
                                'name'    => 'ux',
                                'caption' => 'upper right corner x',
                                'width'   => '150px',
                                'add'     => 25000,
                                'save'    => true,
                                'edit'    => [
                                    'type' => 'NumberSpinner'
                                ]
                            ],
                            [
                                'name'    => 'uy',
                                'caption' => 'upper right corner y',
                                'width'   => '150px',
                                'add'     => 25000,
                                'save'    => true,
                                'edit'    => [
                                    'type' => 'NumberSpinner'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'Expert Parameters',
                'items'   => [
                    [
                        'name'    => self::PROPERTY_UPDATE_INTERVAL,
                        'type'    => 'NumberSpinner',
                        'caption' => 'Update Interval Roborock',
                        'suffix'  => 'Seconds',
                        'minimum' => 0
                    ],
                    [
                        'name'    => self::PROPERTY_SERVER,
                        'type'    => 'ValidationTextBox',
                        'caption' => 'Server'
                    ]
                ]
            ],
        ];
    }

    protected function GetNumberZones(): int
    {
        return count($this->GetZones());
    }

    protected function GetZoneID(): int
    {
        return $this->GetNumberZones() + 1;
    }

    public function GetZones(): array
    {
        $zones_json = $this->ReadPropertyString('zonecoordinates');
        $this->_debug('Zones', $zones_json);
        if ($zones_json === '') {
            $zones = [];
        } else {
            $zones = json_decode($zones_json, true, 512, JSON_THROW_ON_ERROR);
        }
        return $zones;
    }

    public function GetZoneCoordinatesByNumber(int $roomnumber): false|array
    {
        $zones      = $this->GetZones();
        $zoneid     = $roomnumber - 1;
        $zonenumber = $this->GetNumberZones() - 1;
        if ($zonenumber < $roomnumber) {
            $zone = $zones[$zoneid];
            $this->_debug('Zone Coordinates', 'room: ' . $zone['roomname']);
            $lower_left_corner_x  = $zone['lx'];
            $lower_left_corner_y  = $zone['ly'];
            $upper_right_corner_x = $zone['ux'];
            $upper_right_corner_y = $zone['uy'];
            $this->_debug(
                'Zone Coordinates',
                'left x: ' . $lower_left_corner_x . ', left y: ' . $lower_left_corner_y . ', right x: ' . $upper_right_corner_x . ', right y: '
                . $upper_right_corner_y
            );
            $result = [$lower_left_corner_x, $lower_left_corner_y, $upper_right_corner_x, $upper_right_corner_y];
        } else {
            $this->_debug('Zone Coordinates', 'could not find roomnumber');
            $result = false;
        }
        return $result;
    }

    public function GetZoneCoordinatesByName(string $roomname): false|array
    {
        $zones  = $this->GetZones();
        $zoneid = -1;
        foreach ($zones as $key => $zone) {
            if ($zone['roomname'] === $roomname) {
                $zoneid = $key;
            }
        }
        if ($zoneid > -1) {
            $zone = $zones[$zoneid];
            $this->_debug('Zone Coordinates', 'room: ' . $zone['roomname']);
            $lower_left_corner_x  = $zone['lx'];
            $lower_left_corner_y  = $zone['ly'];
            $upper_right_corner_x = $zone['ux'];
            $upper_right_corner_y = $zone['uy'];
            $this->_debug(
                'Zone Coordinates',
                'left x: ' . $lower_left_corner_x . ', left y: ' . $lower_left_corner_y . ', right x: ' . $upper_right_corner_x . ', right y: '
                . $upper_right_corner_y
            );
            $result = [$lower_left_corner_x, $lower_left_corner_y, $upper_right_corner_x, $upper_right_corner_y];
        } else {
            $this->_debug('Zone Coordinates', 'could not find roomname');
            $result = false;
        }
        return $result;
    }


    /**
     * return form actions by token.
     *
     * @return array
     */
    protected function FormActions(): array
    {
        return [
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'TestCenter',
                'visible' => $this->GetStatus() === IS_ACTIVE,
                'items'   => [
                    [
                        'type' => 'TestCenter'
                    ],
                ]
            ],
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'Change Room Names',
                'visible' => $this->ReadPropertyBoolean(self::PROPERTY_CLEANING_ORDER) && ($this->GetStatus() === IS_ACTIVE),
                'items'   => [
                    [
                        'name'     => self::FF_MAPANDROOMLIST,
                        'type'     => 'Tree',
                        'rowCount' => 12,
                        'sort'     => [
                            'column' => self::FF_COL_MAPFLAG
                        ],
                        'enabled'  => true,
                        'columns'  => [
                            [
                                'caption' => 'Map ID',
                                'name'    => self::FF_COL_MAPFLAG,
                                'width'   => '150px'
                            ],
                            [
                                'caption' => 'Map Name',
                                'name'    => self::FF_COL_MAPNAME,
                                'width'   => '200px'
                            ],
                            [
                                'caption' => 'Parent Map ID',
                                'name'    => self::FF_COL_PARENT_MAP_ID,
                                'width'   => '50px',
                                'visible' => false
                            ],
                            [
                                'caption' => 'Room ID',
                                'name'    => self::FF_COL_ROOMID,
                                'width'   => '50px'
                            ],
                            [
                                'caption' => 'Room Text Reference',
                                'name'    => self::FF_COL_ROOMTEXTREFERENCE,
                                'visible' => false,
                                'width'   => '50px'
                            ],
                            [
                                'caption' => 'Room Name',
                                'name'    => self::FF_COL_ROOMNAME,
                                'edit'    => [
                                    'type' => 'ValidationTextBox'
                                ],
                                'width'   => 'auto'
                            ],
                            [
                                'caption' => 'ignore Room',
                                'name'    => self::FF_COL_IGNORE_ROOM,
                                'edit'    => [
                                    'type' => 'CheckBox'
                                ],
                                'width'   => '150'
                            ]
                        ],
                        'onEdit'   => '
                        IPS_RequestAction($id, \'' . self::IDENT_UPDATEROOMNAME . '\', json_encode($MapAndRoomList));
                        ',
                        'values'   => $this->GetMapAndRoomListFormValues()
                    ],
                    [
                        'type'    => 'Button',
                        'caption' => 'Update Maps and Rooms',
                        'onClick' => '
                            IPS_RequestAction($id, \'UpdateMapsAndRooms\', \'\');
                        '
                    ]

                ]
            ],

            [
                'type'    => 'Button',
                'caption' => 'Xiaomi Login Test',
                'onClick' => [
                    '$module = new IPSModuleStrict($id);',
                    '$Result = Roborock_GetTokenFromXiaomi($id);',
                    'if ($Result === true){',
                    '  echo $module->Translate(\'OK\');',
                    '} elseif ($Result === false) {',
                    '  echo $module->Translate(\'Error\');',
                    '};'
                ],
                'visible' => ($this->ReadPropertyString(self::PROPERTY_XIAOMI_USER) !== '')
                             && ($this->ReadPropertyString(
                            self::PROPERTY_XIAOMI_PASSWORD
                        ) !== ''),
            ],
            [
                'type'    => 'RowLayout',
                'name'    => 'Row_HandleMap',
                'visible' => $this->ReadPropertyBoolean(self::PROPERTY_MAP_PICTURE) && ($this->GetStatus() === IS_ACTIVE),
                'items'   => [
                    [
                        'type'    => 'Button',
                        'caption' => 'Get Map',
                        'onClick' => '
                            $module = new IPSModule($id);
                            if (Roborock_GetMap($id)){
                                echo $module->Translate(\'OK\');
                                IPS_RequestAction($id, "ReloadForm", true);
                            } else {
                                echo $module->Translate(\'Error\');
                            };
                        '
                    ],
                    [
                        'type'    => 'PopupButton',
                        'caption' => 'Show Map',
                        'popup'   => [
                            'caption' => 'Map',
                            'items'   => [
                                [
                                    'type'    => 'Image',
                                    'width'   => '30%',
                                    'mediaID' => @IPS_GetObjectIDByIdent(self::IDENT_MAP_PICTURE, $this->InstanceID)
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type'    => 'Button',
                'caption' => 'Update',
                'visible' => $this->GetStatus() === IS_ACTIVE,
                'onClick' => 'Roborock_Update($id);'
            ],
            [
                'type'    => 'Button',
                'caption' => 'Show Room Mapping',
                'visible' => false, //todo: entscheiden, ob überflüssig
                'onClick' => '
                    print_r(Roborock_Get_Room_Mapping($id));
                    '
            ],
            [
                'type'    => 'Button',
                'caption' => 'Push Notification Test',
                'visible' => $this->ReadPropertyInteger('notification_instance') > 0,
                'onClick' => 'IPS_RequestAction($id, "SendPushNotificationTest", 0);'
            ],
            [
                'type'    => 'PopupAlert',
                'name'    => 'VerifyPopup',
                'popup'   => [
                    'closeCaption' => 'Abort',
                    'items'        => [
                        [
                            'type'    => 'Label',
                            'bold'    => true,
                            'name'    => 'VerifyTitle',
                            'caption' => 'Verify Login'
                        ],
                        [
                            'type'    => 'Label',
                            'name'    => 'VerifyMessage',
                            'caption' => ''
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Send',
                            'name'    => 'SendVerificationCodeButton',
                            'onClick' => 'echo Roborock_SendVerificationCode($id);'
                        ],
                        [
                            'type'    => 'Label',
                            'bold'    => true,
                            'caption' => 'Enter the verification code below and click Submit to continue.'
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'VerifyCode',
                            'caption' => 'Verification Code'
                        ]
                    ],
                    'buttons'      => [
                        [
                            'type'    => 'Button',
                            'caption' => 'Submit',
                            'name'    => 'SubmitVerificationCodeButton',
                            'onClick' => 'echo Roborock_SubmitVerificationCode($id, $VerifyCode);'
                        ]
                    ]
                ],
                'visible' => $this->GetBuffer(self::BUFFER_VERIFICATION_URL) !== ''
            ]
        ];
    }

    /**
     * return from status.
     *
     * @return array
     */
    private function FormStatus(): array
    {
        return [
            [
                'code'    => self::STATUS_INST_REGISTRATION_INCOMPLETE,
                'icon'    => 'inactive',
                'caption' => 'Registration is not complete. Please check user and password.'
            ],
            [
                'code'    => self::STATUS_INST_IP_ADDRESS_IS_INVALID,
                'icon'    => 'error',
                'caption' => 'No valid IP address.'
            ],
            [
                'code'    => self::STATUS_INST_TOKEN_IS_INVALID,
                'icon'    => 'error',
                'caption' => 'Token is not valid.'
            ],
            [
                'code'    => self::STATUS_INST_NO_ROBOROCK_FOUND,
                'icon'    => 'inactive',
                'caption' => 'No roborock was found on that ip and token.'
            ]
        ];
    }

    private function GetMapAndRoomListFormValues(): array
    {
        $maps_list = json_decode($this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST), true, 512, JSON_THROW_ON_ERROR);
        $RoomNames = json_decode($this->ReadAttributeString(self::ATTRIBUTE_ROOM_NAMES), true, 512, JSON_THROW_ON_ERROR);
        $this->_debug(__FUNCTION__, 'maps_list: ' . json_encode($maps_list, JSON_THROW_ON_ERROR));
        $this->_debug(__FUNCTION__, 'RoomNames: ' . json_encode($RoomNames, JSON_THROW_ON_ERROR));

        $id                   = 1;
        $MapAndRoomListValues = [];
        foreach ($maps_list as $map) {
            $parentID               = $id;
            $MapAndRoomListValues[] = [
                'id'                 => $id++,
                self::FF_COL_MAPFLAG => $map['mapFlag'],
                self::FF_COL_MAPNAME => $map['MapName'],
                'editable'           => false
            ];

            foreach ($map['rooms'] as $room) {
                $MapAndRoomListValues[] = [
                    'id'                           => $id++,
                    'parent'                       => $parentID,
                    self::FF_COL_PARENT_MAP_ID     => $map['mapFlag'],
                    self::FF_COL_ROOMID            => $room['roomID'],
                    self::FF_COL_ROOMTEXTREFERENCE => $room['referenceID'],
                    self::FF_COL_ROOMNAME          => $RoomNames[$room['referenceID']] ?? $this->Translate('Room') . ' ' . $room['roomID'],
                    self::FF_COL_IGNORE_ROOM       => $room['IgnoreRoom'] ?? false,
                    'editable'                     => true
                ];
            }
        }
        $this->_debug(__FUNCTION__, 'Values: ' . json_encode($MapAndRoomListValues, JSON_THROW_ON_ERROR));

        return $MapAndRoomListValues;
    }

    /***********************************************************
     * Helper methods
     ***********************************************************/

    /**
     * updates remote variable with joystick html.
     */
    public function SetJoystickHtml(): void
    {
        $joystick = file_get_contents(dirname(__FILE__, 2) . '/libs/joystick.html');
        $joystick = str_replace('[instance_id]', (string)$this->InstanceID, $joystick);
        $this->SetValue(self::IDENT_REMOTE_CONTROL, $joystick);
    }

    /**
     * check for variable and set value.
     *
     * @param $ident
     * @param $value
     */
    private function _SetValue($ident, $value): void
    {
        if (@$this->GetIDForIdent($ident)) {
            $this->SetValue($ident, $value);
        }
    }

    /**
     * convert seconds to human-readable time.
     *
     * @param int $inputSeconds
     *
     * @return string
     */
    private function _convertSecondsToTime(int $inputSeconds = 0): string
    {
        $secondsInAMinute = 60;
        $secondsInAnHour  = 60 * $secondsInAMinute;
        $secondsInADay    = 24 * $secondsInAnHour;

        // extract days
        $days = (int)floor($inputSeconds / $secondsInADay);

        // extract hours
        $hourSeconds = $inputSeconds % $secondsInADay;
        $hours       = (int)floor($hourSeconds / $secondsInAnHour);

        // extract minutes
        $minuteSeconds = $hourSeconds % $secondsInAnHour;
        $minutes       = (int)floor($minuteSeconds / $secondsInAMinute);

        // extract the remaining seconds
        $remainingSeconds = $minuteSeconds % $secondsInAMinute;
        $seconds          = (int)ceil($remainingSeconds);

        // build time
        $time = '';
        if ($days) {
            $time .= ', ' . $days . ' ' . $this->Translate('Day' . ($days === 1 ? '' : 's'));
        }

        if ($hours) {
            $time .= ', ' . $hours . ' ' . $this->Translate('Hour' . ($hours === 1 ? '' : 's'));
        }

        if ($minutes) {
            $time .= ', ' . $minutes . ' ' . $this->Translate('Minute' . ($minutes === 1 ? '' : 's'));
        }

        if (empty($time)) {
            $time .= ', ' . $seconds . ' ' . $this->Translate('Second' . ($seconds === 1 ? '' : 's'));
        }

        return substr($time, 2);
    }

    /**
     * add leading zeros to number.
     *
     * @param int|string $number
     * @param int        $padding
     *
     * @return string
     */
    private function _zeroPadding(int|string $number, int $padding = 2): string
    {
        return str_pad((string)$number, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * send debug log.
     *
     * @param string|null $notification
     * @param string|null $message
     */
    private function _debug(?string $notification = null, ?string $message = null): void
    {
        $this->SendDebug($notification, $message, 0);
    }


    /**
     * return incremented position.
     *
     * @return int
     */
    private function _getPosition(): int
    {
        $this->position++;
        return $this->position;
    }

    /**
     * convert data array to html table.
     *
     * @param array $data
     *
     * @return string
     */
    private function _convertDataToTable(array $data = []): string
    {
        // build table
        $html = <<<EOF
                <style>
                    .robotable th,
                    .robotable td {
                        padding: .5em .8em;
                    }
                    .robotable .th { text-align:left; white-space: nowrap; }
                    .robotable tr.th:nth-child(odd) {background: rgba(0,0,0,0.4)}
                    .robotable tr:nth-child(odd) {background: rgba(0,0,0,0.2)}
                    .unicode, {border:0;background:transparent;padding:0;color:#FFF;text-decoration:none}
                    .unicode.red {color:red}
                    .unicode.green {color:green}
                    .separator { background: rgba(0,0,0,0.3);font-weight:bold;font-size:1.2em }
                </style>
			<table class="robotable">
EOF;

        // build table head
        if (isset($data['table']['head'])) {
            $html .= '<tr class="th">';
            foreach ($data['table']['head'] as $th) {
                $options = '';
                if (is_array($th)) {
                    $options = ' ' . $th[1];
                    $th      = $th[0];
                }

                $html .= '<th class="th" ' . $options . '>' . $this->Translate($th) . '</th>';
            }

            $html .= '</tr>';
        }

        // build table body
        if (isset($data['table']['head'])) {
            foreach ($data['table']['body'] as $tr) {
                $html .= '<tr>';
                foreach ($tr as $td) {
                    $options = '';
                    if (is_array($td)) {
                        $options = ' ' . $td[1];
                        $td      = $td[0];
                    }

                    $html .= '<td' . $options . '>' . $this->Translate($td) . '</td>';
                }

                $html .= '</tr>';
            }
        }

        $html .= '</table>';

        return $html;
    }

    /**
     * Get description timer day.
     *
     * @param $day_of_week
     *
     * @return string
     */
    private function _getTimerDay($day_of_week): string
    {
        if ($day_of_week === '*') {
            $timer_day = 'once';
        } elseif ($day_of_week === '1,2,3,4,5') {
            $timer_day = 'weekdays';
        } elseif ($day_of_week === '0,6') {
            $timer_day = 'weekends';
        } elseif ($day_of_week === '0,1,2,3,4,5,6') {
            $timer_day = 'every day';
        } else {
            $timer_day = 'custom';
        }
        return $this->Translate($timer_day);
    }

    /**
     * Get repetition for timer.
     *
     * @param $repetitionstring
     *
     * @return string
     */
    private function _getTimerRepetition($repetitionstring): string
    {
        if ($repetitionstring === 'once') {
            $repetition = '*';
        } elseif ($repetitionstring === 'weekdays') {
            $repetition = '1,2,3,4,5';
        } elseif ($repetitionstring === 'weekends') {
            $repetition = '0,6';
        } elseif ($repetitionstring === 'every day') {
            $repetition = '0,1,2,3,4,5,6';
        } else {
            $repetition = '*';
        }
        return $repetition;
    }


    // Xiaomi App Login Test
    public function GetTokenFromXiaomi(): bool|int
    {
        // read properties
        $user     = $this->ReadPropertyString(self::PROPERTY_XIAOMI_USER);
        $password = $this->ReadPropertyString(self::PROPERTY_XIAOMI_PASSWORD);

        $clientId = $this->ReadAttributeString(self::ATTRIBUTE_CLIENTID);
        $agentId  = $this->ReadAttributeString(self::ATTRIBUTE_AGENTID);
        $this->SendDebug(
            __FUNCTION__,
            'user/password/agentId/clientId: ' . json_encode([$user, $password, $agentId, $clientId], JSON_THROW_ON_ERROR),
            0
        );

        // -- login --
        $loginData = $this->login($user, $agentId, $clientId);

        if (!$loginData || !isset($loginData['qs'], $loginData['callback'], $loginData['_sign'])) {
            $this->SendDebug(__FUNCTION__ . ': ERROR', 'Login failed, please check account at https://account.xiaomi.com', 0);
            return false;
        }

        $this->SendDebug(
            __FUNCTION__,
            'loginData: ' . json_encode(['qs' => $loginData['qs'], 'callback' => $loginData['callback'], '_sign' => $loginData['_sign']],
                                        JSON_THROW_ON_ERROR),
            0
        );

        // -- login_account --
        $loginAccountData =
            $this->login_account($user, $password, $agentId, $clientId, $loginData['qs'], $loginData['callback'], $loginData['_sign']);
        if ($loginAccountData['securityStatus'] === 16) { // 2FA
            $this->SendDebug(__FUNCTION__ . ': WARNING', 'Additional verification required', 0);
            $this->SetBuffer(self::BUFFER_VERIFICATION_URL, $loginAccountData['notificationUrl']);
            $this->SetBuffer(self::BUFFER_IDENTITY_SESSION, '');
            $this->SetBuffer(self::BUFFER_VERIFICATION_FLAG, '');

            [$VerifyMessage, $ErrorMessage] = $this->StartVerifyDevice();

            $this->UpdateFormField('LogoutButton', 'visible', true);
            $this->UpdateFormField('LoginButton', 'visible', false);
            $this->UpdateFormField('LoginPopup', 'visible', false);

            $this->UpdateFormField('VerifyMessage', 'caption', $VerifyMessage);
            if ($VerifyMessage !== '') {
                $this->UpdateFormField('VerifyMessage', 'caption', $VerifyMessage);
            } else {
                $this->UpdateFormField('VerifyMessage', 'caption', $this->Translate($ErrorMessage));
                $this->UpdateFormField('VerifyMessage', 'color', 0xff0000);
                $this->UpdateFormField('SubmitVerificationCodeButton', 'visible', false);
            }
            $this->UpdateFormField('VerifyPopup', 'visible', true);
            return 16;
        }
        if (!$loginAccountData || !isset($loginAccountData['ssecurity'], $loginAccountData['userId'], $loginAccountData['location'])) {
            $this->SendDebug(__FUNCTION__ . ': ERROR', 'Login failed, please check user/password at https://account.xiaomi.com', 0);
            return false;
        }

        $this->WriteAttributeString(self::ATTRIBUTE_LOGIN_ACCOUNT_DATA, json_encode($loginAccountData, JSON_THROW_ON_ERROR));

        $this->SendDebug(
            __FUNCTION__,
            'loginAccountData: ' . json_encode(
                [
                    'ssecurity' => $loginAccountData['ssecurity'],
                    'userId'    => $loginAccountData['userId'],
                    'location'  => $loginAccountData['location']
                ],
                JSON_THROW_ON_ERROR
            ),
            0
        );

        // -- login_location --
        $loginLocationData = $this->login_location($agentId, $clientId, $loginAccountData['location']);
        if (!$loginLocationData || !isset($loginLocationData['userId'], $loginLocationData['serviceToken'])) {
            $this->SendDebug(__FUNCTION__ . ': ERROR', 'Login Location failed', 0);
            return false;
        }
        $this->WriteAttributeString(self::ATTRIBUTE_LOGIN_LOCATION_DATA, json_encode($loginLocationData, JSON_THROW_ON_ERROR));

        $this->SendDebug(
            __FUNCTION__,
            'loginLocationData: ' . json_encode(
                ['userId' => $loginLocationData['userId'], 'serviceToken' => $loginLocationData['serviceToken']],
                JSON_THROW_ON_ERROR
            ),
            0
        );

        // -- token of device by getDeviceStatus --
        $deviceData = $this->getDeviceStatus();
        if ($deviceData === []) {
            return false;
        }
        if (!isset($deviceData['result']['list'][0]['did'], $deviceData['result']['list'][0]['token'])) {
            return false;
        }

        $host = gethostbyname($this->ReadPropertyString(self::PROPERTY_IP));
        foreach ($deviceData['result']['list'] as $key => $device) {
            $this->SendDebug(
                __FUNCTION__,
                "deviceData[$key]: " . json_encode(['did' => $device['did'], 'name' => $device['name'], 'token' => $device['token']],
                                                   JSON_THROW_ON_ERROR),
                0
            );
            if ($device['localip'] === $host) {
                $this->WriteAttributeString(self::ATTRIBUTE_TOKEN, $device['token']);
                $this->SendDebug(__FUNCTION__, sprintf('Token \'%s\' found', $device['token']), 0);
                return true;
            }
        }

        $this->SendDebug(__FUNCTION__, sprintf('No Token found for \'%s\'', $host), 0);
        return false;
    }

    /**
     * StartVerifyDevice
     *
     * @return array
     */
    private function StartVerifyDevice(): array
    {
        $this->SendDebug('Cloud Login', 'Device verification process initiated', 0);
        $IdentityUrl = str_replace('fe/service/identity/authStart', 'identity/list', $this->GetBuffer(self::BUFFER_VERIFICATION_URL));
        $headers     = [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Android-7.1.1-1.0.0-ONEPLUS A3010-136-' . $this->ReadAttributeString(self::ATTRIBUTE_AGENTID)
            . ' APP/xiaomi.smarthome APPV/62830',
            'Cookie: sdkVersion=accountsdk-18.8.15; deviceId=' . $this->ReadAttributeString(self::ATTRIBUTE_CLIENTID)
        ];

        $ch = curl_init($IdentityUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result       = curl_exec($ch);
        $header_size  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responsecode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        if (($responsecode !== 200)) {
            trigger_error(
                sprintf('%s: responsecode: %s , URL: %s, effective URL: %s', __FUNCTION__, (int)$responsecode, $IdentityUrl, $effectiveURL)
            );
            return ['', 'Error on fetching verification list'];
        }
        $header           = substr($result, 0, $header_size);
        $result           = substr($result, $header_size);
        $identity_session = explode('identity_session=', $header)[1];
        $identity_session = explode(';', $identity_session)[0];
        if ($identity_session === '') {
            return ['', 'Error on parsing identity session'];
        }
        $this->SetBuffer(self::BUFFER_IDENTITY_SESSION, $identity_session);
        $Json = $this->parseJson($result);
        if ($Json === null) {
            return ['', 'Error on parsing verification list'];
        }
        $this->SetBuffer(self::BUFFER_VERIFICATION_FLAG, $Json['flag']);
        $VerifyUrl = RoborockApiVerifyIdentity::getUrl((int)$Json['flag']) . http_build_query(
                [
                    '_flag' => (int)$Json['flag'],
                    '_json' => 'true'
                ]
            );
        $headers   = [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Android-7.1.1-1.0.0-ONEPLUS A3010-136-' . $this->ReadAttributeString(self::ATTRIBUTE_AGENTID)
            . ' APP/xiaomi.smarthome APPV/62830',
            'Cookie: identity_session=' . $identity_session . ';sdkVersion=accountsdk-18.8.15;deviceId=' . $this->ReadAttributeString(
                self::ATTRIBUTE_CLIENTID
            )
        ];
        $ch        = curl_init($VerifyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result       = curl_exec($ch);
        $responsecode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        if (($responsecode !== 200)) {
            trigger_error(
                sprintf('%s: responsecode: %s , URL: %s, effective URL: %s', __FUNCTION__, (int)$responsecode, $IdentityUrl, $effectiveURL)
            );
            return ['', 'Error on fetching verification message'];
        }
        $Json = $this->parseJson($result);
        if ($Json === null) {
            return ['', 'Error on parsing verification message'];
        }
        if ($Json['code'] !== 0) {
            return $Json['tips'] ?? ['', 'Error on fetching verification message'];
        }
        [$Message, $Index] = RoborockApiVerifyIdentity::getMessageTextAndIndex((int)$this->GetBuffer(self::BUFFER_VERIFICATION_FLAG));
        $Message = sprintf($this->Translate($Message), $Json[$Index]);
        return [$Message, ''];
    }

    /**
     * SendVerificationCode
     *
     * @return string
     */
    public function SendVerificationCode(): string
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        $VerifyUrl = RoborockApiCheckIdentity::getUrl((int)$this->GetBuffer(self::BUFFER_VERIFICATION_FLAG)) . http_build_query([
                                                                                                                                    '_dc' => (time(
                                                                                                                                                   )
                                                                                                                                                   * 1000)
                                                                                                                                ]);
        $headers   = [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Android-7.1.1-1.0.0-ONEPLUS A3010-136-' . $this->ReadAttributeString(self::ATTRIBUTE_AGENTID)
            . ' APP/xiaomi.smarthome APPV/62830',
            'Cookie: identity_session=' . $this->GetBuffer(self::BUFFER_IDENTITY_SESSION) . ';sdkVersion=accountsdk-18.8.15;deviceId='
            . $this->ReadAttributeString(self::ATTRIBUTE_CLIENTID)
        ];
        $form      = [
            'retry' => 0,
            'icode' => '',
            '_json' => 'true'
        ];
        $ch        = curl_init($VerifyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result       = curl_exec($ch);
        $responsecode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        if (($responsecode !== 200)) {
            trigger_error(sprintf('%s: responsecode: %s, URL: %s, effective URL: %s', __FUNCTION__, (int)$responsecode, $VerifyUrl, $effectiveURL));
            return 'Error in request to send verification code';
        }
        $Json = $this->parseJson($result);
        if ($Json === null) {
            return 'Error in parsing result from send verification request';
        }
        if ($Json['code'] !== 0) {
            return $Json['tips'] ?? 'Error in request to send verification code';
        }
        $this->UpdateFormField('SendVerificationCodeButton', 'enabled', false);
        $this->UpdateFormField('SendVerificationCodeButton', 'caption', $this->Translate('Code sent'));
        return '';
    }

    /**
     * SubmitVerificationCode
     *
     * @param string $Code
     *
     * @return string
     */
    public function SubmitVerificationCode(string $Code): string
    {
        $this->SendDebug(__FUNCTION__, $Code, 0);
        $headers   = [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Android-7.1.1-1.0.0-ONEPLUS A3010-136-' . $this->ReadAttributeString(self::ATTRIBUTE_AGENTID)
            . ' APP/xiaomi.smarthome APPV/62830',
            'Cookie: identity_session=' . $this->GetBuffer(self::BUFFER_IDENTITY_SESSION) . ';sdkVersion=accountsdk-18.8.15;deviceId='
            . $this->ReadAttributeString(self::ATTRIBUTE_CLIENTID)
        ];
        $VerifyUrl = RoborockApiVerifyIdentity::getUrl((int)$this->GetBuffer(self::BUFFER_VERIFICATION_FLAG)) . http_build_query([
                                                                                                                                     '_dc' => (time(
                                                                                                                                                    )
                                                                                                                                                    * 1000)
                                                                                                                                 ]);
        $form      = [
            '_flag'  => (int)$this->GetBuffer(self::BUFFER_VERIFICATION_FLAG),
            '_json'  => 'true',
            'ticket' => $Code,
            'trust'  => 'true'
        ];
        $ch        = curl_init($VerifyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result       = curl_exec($ch);
        $responsecode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $this->SendDebug(__FUNCTION__, $result, 0);
        if (($responsecode !== 200)) {
            trigger_error(sprintf('%s: responsecode: %s, URL: %s, effective URL: %s', __FUNCTION__, (int)$responsecode, $VerifyUrl, $effectiveURL));
            return 'Error on submit verification code';
        }
        $Json = $this->parseJson($result);
        if ($Json === null) {
            return 'Error on parsing verification result';
        }
        if ($Json['code'] !== 0) {
            return $Json['tips'] ?? 'Error on submit verification code';
        }
        $LoginUrl = $Json['location'];
        $ch       = curl_init($LoginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result       = curl_exec($ch);
        $responsecode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        if (($responsecode !== 200)) {
            trigger_error(sprintf('%s: responsecode: %s, URL: %s, effective URL: %s', __FUNCTION__, (int)$responsecode, $LoginUrl, $effectiveURL));
            return 'Error on finalizing verification';
        }
        $userId = explode('userId=', $result)[1];
        $userId = explode(';', $userId)[0];

        $serviceToken = explode('serviceToken=', $result)[1];
        $serviceToken = explode(';', $serviceToken)[0];

        $this->WriteAttributeString(
            self::ATTRIBUTE_LOGIN_LOCATION_DATA,
            json_encode([
                            'userId'       => $userId,
                            'serviceToken' => $serviceToken
                        ],
                        JSON_THROW_ON_ERROR)
        );

        $Lines    = explode("\r\n", $result);
        $location = '';
        foreach ($Lines as $Line) {
            $line_array = explode(':', $Line);
            $Field      = strtolower(trim(array_shift($line_array)));
            if ($Field === 'location') {
                $location = trim(implode(':', $line_array));
                continue;
            }
            if ($Field === 'extension-pragma') {
                $Data = json_decode(trim(implode(':', $line_array)), true, 512, JSON_THROW_ON_ERROR);
                $this->SetBuffer(self::BUFFER_VERIFICATION_URL, '');
                $this->WriteAttributeString(
                    self::ATTRIBUTE_LOGIN_ACCOUNT_DATA,
                    json_encode(
                        [
                            'ssecurity' => $Data['ssecurity'],
                            'userId'    => $userId,
                            'location'  => $location
                        ],
                        JSON_THROW_ON_ERROR
                    )
                );
                $this->SendDebug('Cloud Login', 'Device verification successful', 0);
                return $this->Translate('MESSAGE:Verification successful!');
            }
        }
        return 'Error on finalizing verification';
    }

    private function randomClientId(): string
    {
        $clientId = '';
        for ($i = 0; $i < 7; $i++) {
            $clientId .= chr(random_int(97, 122)); // buchstaben a bis z
        }
        return $clientId;
    }

    private function randomAgentId(): string
    {
        $agentId = '';
        for ($i = 0; $i < 7; $i++) {
            $agentId .= chr(random_int(97, 122)); // buchstaben a bis z
        }
        return $agentId;
    }

    private function login(string $user, string $agentId, string $clientId)
    {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Android-7.1.1-1.0.0-ONEPLUS A3010-136-' . $agentId . ' APP/xiaomi.smarthome APPV/62830',
            'Cookie: sdkVersion=3.8.6; userId=' . trim($user) . '; deviceId=' . $clientId
        ];
        $url     = 'https://account.xiaomi.com/pass/serviceLogin?sid=xiaomiio&_json=true';
        $ch      = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result       = curl_exec($ch);
        $responsecode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        curl_close($ch);
        if (($responsecode !== 200)) {
            trigger_error(sprintf('%s: responsecode: %s , URL: %s, effective URL: %s', __FUNCTION__, (int)$responsecode, $url, $effectiveURL));
            return false;
        }
        return $this->parseJson($result);
    }

    private function login_account(string $user, string $password, string $agentId, string $clientId, string $qs, string $callback, string $sign)
    {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Android-7.1.1-1.0.0-ONEPLUS A3010-136-' . $agentId . ' APP/xiaomi.smarthome APPV/62830',
            'Cookie: sdkVersion=accountsdk-18.8.15; deviceId=' . $clientId
        ];
        $form    = [
            'sid'      => 'xiaomiio',
            'hash'     => $this->encodePassword($password),
            'callback' => $callback,
            'qs'       => $qs,
            'user'     => trim($user),
            '_sign'    => $sign,
            '_json'    => 'true',

        ];

        $form = http_build_query($form);
        $url  = 'https://account.xiaomi.com/pass/serviceLoginAuth2';
        $ch   = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result       = curl_exec($ch);
        $responsecode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        curl_close($ch);
        if (($responsecode !== 200)) {
            trigger_error(sprintf('%s: responsecode: %s, URL: %s, effective URL: %s', __FUNCTION__, (int)$responsecode, $url, $effectiveURL));
            return false;
        }
        return $this->parseJson($result);
    }

    private function login_location(string $agentId, string $clientId, string $url): false|array
    {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Android-7.1.1-1.0.0-ONEPLUS A3010-136-' . $agentId . ' APP/xiaomi.smarthome APPV/62830',
            'Cookie: sdkVersion=accountsdk-18.8.15; deviceId=' . $clientId
        ];
        $ch      = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result       = curl_exec($ch);
        $header_size  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responsecode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        curl_close($ch);
        if (($responsecode !== 200)) {
            trigger_error(sprintf('%s: responsecode: %s, URL: %s, effective URL: %s', __FUNCTION__, (int)$responsecode, $url, $effectiveURL));
            return false;
        }

        $header = substr($result, 0, $header_size);
        $result = substr($result, $header_size);


        if ($result === 'ok') {
            $userId = explode('userId=', $header)[1];
            $userId = explode(';', $userId)[0];

            $cUserId = explode('cUserId=', $header)[1];
            $cUserId = explode(';', $cUserId)[0];

            $serviceToken = explode('serviceToken=', $header)[1];
            $serviceToken = explode(';', $serviceToken)[0];

            return [
                'userId'       => $userId,
                'cUserId'      => $cUserId,
                'serviceToken' => $serviceToken
            ];
        }

        return false;
    }

    private function getApiIO(string $path, array $values): array
    {
        $loginLocationData = json_decode($this->ReadAttributeString(self::ATTRIBUTE_LOGIN_LOCATION_DATA), true, 512, JSON_THROW_ON_ERROR);
        if ($loginLocationData === []) {
            $this->_debug(__FUNCTION__, 'no loginLocationData');
            return [];
        }

        $server = strtolower($this->ReadPropertyString(self::PROPERTY_SERVER));
        if ($server === 'cn') {
            $server = '';
        }
        if ($server !== '') {
            $server .= '.';
        }

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'x-xiaomi-protocal-flag-cli: PROTOCAL-HTTP2',
            'User-Agent: Android-7.1.1-1.0.0-ONEPLUS A3010-136-9D28921C354D7 APP/xiaomi.smarthome APPV/62830',
            'Cookie: userId=' . $loginLocationData['userId'] . '; yetAnotherServiceToken=' . $loginLocationData['serviceToken'] . '; serviceToken='
            . $loginLocationData['serviceToken'] . '; locale=de_DE; timezone=GMT%2B01%3A00; is_daylight=1; dst_offset=3600000; channel=MI_APP_STORE'
        ];

        $params = [
            'key'   => 'data',
            'value' => json_encode($values, JSON_THROW_ON_ERROR)
        ];

        $loginAccountData = json_decode($this->ReadAttributeString(self::ATTRIBUTE_LOGIN_ACCOUNT_DATA), true, 512, JSON_THROW_ON_ERROR);
        if ($loginAccountData === []) {
            $this->_debug(__FUNCTION__, 'no loginAccountData');
            return [];
        }

        $body = $this->generateSignature($loginAccountData['ssecurity'], $params, $path);
        $body = http_build_query($body);

        $url = 'https://' . $server . 'api.io.mi.com/app' . $path;
        $this->_debug(__FUNCTION__, sprintf('url: %s, params: %s', $url, json_encode($params, JSON_THROW_ON_ERROR)));
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result       = curl_exec($ch);
        $responsecode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error        = curl_error($ch);

        curl_close($ch);
        if (($responsecode !== 200)) {
            trigger_error(
                sprintf(
                    '%s: http responsecode: %s, URL: %s, effective URL: %s, result: %s, error: %s',
                    __FUNCTION__,
                    (int)$responsecode,
                    $url,
                    $effectiveURL,
                    $result,
                    $error
                )
            );
            return [];
        }
        return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    }

    private function getDeviceStatus(): array
    {
        $device_list = $this->getApiIO('/home/device_list', ['getVirtualModel' => false, 'getHuamiDevices' => 0]);
        $this->_debug(__FUNCTION__, sprintf('device_list: %s', json_encode($device_list, JSON_THROW_ON_ERROR)));

        return $device_list;
    }

    private function encodePassword(string $password): string
    {
        return strtoupper(md5($password));
    }

    private function parseJson(string $jsonString)
    {
        $jsonString = str_replace('&&&START&&&', '', $jsonString);
        $jsonData   = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        return $jsonData ?? false;
    }

    private function generateSignature(string $ssecurity, array $params, string $path): array
    {
        $nonce = random_bytes(8);
        $bytes = pack('N', (int)round(microtime(true) / 60));
        $nonce .= $bytes;
        $nonce = base64_encode($nonce);

        $ctx = hash_init('sha256');
        hash_update($ctx, base64_decode($ssecurity) . base64_decode($nonce));
        $signature = base64_encode(hash_final($ctx, true));


        $paramsArray   = [];
        $paramsArray[] = $path;
        $paramsArray[] = $signature;
        $paramsArray[] = $nonce;

        $data = "";
        foreach ($params as $key => $value) {
            if ($key === "key") {
                $data = $value . "=";
            }
            if ($key === "value") {
                $data .= $value;
            }
        }
        $paramsArray[] = $data;

        $postdata = '';
        foreach ($paramsArray as $value) {
            $postdata .= $value . '&';
        }
        $postdata = substr($postdata, 0, -1);

        return [
            'signature' => $this->HashHmacSHA256($postdata, $signature),
            '_nonce'    => $nonce,
            'data'      => $params['value']
        ];
    }

    private function HashHmacSHA256($data, $secret): string
    {
        return base64_encode(hash_hmac('sha256', $data, base64_decode($secret), true));
    }

    /***********************************************************
     * Callbacks
     ***********************************************************/

    /**
     * Callback: Serial Number.
     *
     * @param array $data
     *
     * @return string
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function get_serial_number_callback(array $data): string
    {
        $serial = $data['result'][0]['serial_number'] ?? '';
        $this->_SetValue('serial_number', $serial);

        return $serial;
    }

    /**
     * Callback: get_room_mapping
     *
     * @param array $data
     *
     * @return array
     * @throws \JsonException
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function get_room_mapping_callback(array $data): array
    {
        $this->_debug(__FUNCTION__, json_encode($data, JSON_THROW_ON_ERROR));
        //        $serial = $data['result'][0]['serial_number'] ?? '';
        //        $this->SetRoborockValue('serial_number', $serial);

        if (!isset($data['result'])) {
            return [];
        }

        $rooms = [];
        foreach ($data['result'] as $room) {
            if ($room[1] !== 'NaN') { //Räume ohne Namen werden nicht übernommen
                $rooms[$room[0]] = [
                    'roomID'      => $room[0],
                    'referenceID' => $room[1]
                ];
            }
        }

        if ($this->ReadPropertyBoolean(self::PROPERTY_CLEANING_ORDER)) {
            $this->UpdateAttributeMapsListWithRooms($rooms);
        }

        return $data;
    }

    private function UpdateAttributeMapsListWithRooms(array $rooms): void
    {
        if (!$this->ReadPropertyBoolean(self::PROPERTY_CLEANING_ORDER)) {
            return;
        }

        $mapFlag  = $this->GetValue(self::IDENT_MAP_STATUS);
        $MapsList = json_decode($this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST), true, 512, JSON_THROW_ON_ERROR);
        $this->_debug(__FUNCTION__, sprintf('MapsList (old): %s', $this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST)));
        $this->_debug(__FUNCTION__, sprintf('rooms: %s', json_encode($rooms, JSON_THROW_ON_ERROR)));

        foreach ($rooms as $id => $room) {
            if (!isset($MapsList[$mapFlag]['rooms'][$id])) {
                $MapsList[$mapFlag]['rooms'][$id]               = $room;
                $MapsList[$mapFlag]['rooms'][$id]['IgnoreRoom'] = false;
            } else {
                $MapsList[$mapFlag]['rooms'][$id]['referenceID'] = $room['referenceID'];
            }
        }

        //no longer existing rooms are deleted
        foreach (array_keys(array_diff_key($MapsList[$mapFlag]['rooms'], $rooms)) as $roomID) {
            unset ($MapsList[$mapFlag]['rooms'][$roomID]);
        }

        $this->_debug(__FUNCTION__, sprintf('MapsList (new): %s', json_encode($MapsList, JSON_THROW_ON_ERROR)));
        $this->WriteAttributeString(self::ATTRIBUTE_MAPS_LIST, json_encode($MapsList, JSON_THROW_ON_ERROR));

        $this->WriteRoomSelectionProfile();
        $this->UpdateRoomsSelected();
    }

    /**
     * Callback: Serial Number.
     *
     * @param array $data
     *
     * @return false|string
     * @throws \JsonException
     */
    private function load_multi_map_callback(array $data): false|int
    {
        $this->SendDebug(__FUNCTION__, json_encode($data), 0);
        if (isset($data['result'][0]) && $data['result'][0] === 'ok') {
            $map_status = (int) $data['params'][0];
            $this->_SetValue(self::IDENT_MAP_STATUS, $map_status);

            if ($this->ReadPropertyBoolean(self::PROPERTY_CLEANING_ORDER)) {
                $this->RequestData('get_room_mapping', ['immediate' => true]);
                $this->WriteRoomSelectionProfile();
                $this->UpdateRoomsSelected();
            }

            //fetch the current map
            $this->WriteAttributeString(self::ATTRIBUTE_MAPFILE_URL, '');
            $this->GetMap();

            return $map_status;
        }

        return false;
    }

    /**
     * Callback: Timezone.
     *
     * @param array $data
     *
     * @return string
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function get_timezone_callback(array $data): string
    {
        $timezone = $data['result'][0] ?? '';
        $this->_SetValue(self::IDENT_TIMEZONE, $timezone);

        return $timezone;
    }

    /**
     * Callback: Device Info.
     *
     * @param array $data
     *
     * @return array
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function miio_info_callback(array $data): array
    {
        if (isset($data['result'])) {
            $info = $data['result'];

            $hardware_version = $info['hw_ver'];
            $this->_SetValue('hw_ver', $hardware_version);

            $firmware_version = $info['fw_ver'];
            $this->_SetValue('fw_ver', $firmware_version);

            $ssid = $info['ap']['ssid'];
            $this->_SetValue('ssid', $ssid);

            $rssi = $info['ap']['rssi'];
            $this->_SetValue('rssi', $rssi);

            $ip = $info['netif']['localIp'];
            $this->_SetValue('local_ip', $ip);

            $model = $info['model'];
            $this->_SetValue(self::IDENT_MODEL, $model);
            if ($model !== $this->ReadAttributeString(self::ATTRIBUTE_MODEL)) {
                $this->WriteAttributeString(self::ATTRIBUTE_MODEL, $model);
                $this->SendDebug(__FUNCTION__, 'new attribute Model: ' . $model, 0);
                if (($modelClassName = str_replace('.', '_', $model)) && class_exists($modelClassName)) {
                    $this->device = new $modelClassName();
                } else {
                    $this->device = new roborock_vacuum();
                }
            }

            $mac = $info['mac'];
            $this->_SetValue('mac', $mac);

            // return values
            return [
                'hardware_version' => $hardware_version,
                'firmware_version' => $firmware_version,
                'ssid'             => $ssid,
                'rssi'             => $rssi,
                'ip'               => $ip,
                'model'            => $model,
                'mac'              => $mac
            ];
        }

        // fallback
        return [
            'hardware_version' => null,
            'firmware_version' => null,
            'ssid'             => null,
            'rssi'             => null,
            'ip'               => null,
            'model'            => null,
            'mac'              => null
        ];
    }

    /**
     * Callback: Status.
     *
     * @param array $data
     *
     * @return array
     */
    protected function get_status_callback(array $data): array
    {
        if (!isset($data['result'][0])) {
            return [];
        }

        $result = $data['result'][0];

        // update values
        $ret     = [];
        $battery = (int)$result['battery'];
        $this->_SetValue('battery', $battery);
        $ret['battery'] = $battery;

        $state = (int)$result['state'];
        if ($state === StateCode::CHARGING->value && $battery === 100) {
            $this->_SetValue(self::IDENT_STATE, 100);
        } else {
            $this->_SetValue(self::IDENT_STATE, $state);
        }
        $ret['state'] = $state;

        $clean_area = (float)($result['clean_area'] / 1000000); // cm2 -> m2
        $this->_SetValue('clean_area', $clean_area);
        $ret['clean_area'] = $clean_area;

        $clean_time = $result['clean_time']; // sec
        $this->_SetValue('clean_time', $clean_time);
        $ret['clean_time'] = $clean_time;

        $error_code = (int)$result['error_code'];
        $this->_SetValue('error_code', $error_code);
        $ret['error_code'] = $error_code;

        $fan_power = (int)$result['fan_power'];
        $this->_SetValue(self::IDENT_FAN_POWER, $fan_power);
        $ret['fan_power'] = $fan_power;

        if (isset($result['water_box_mode'])) {
            $water_box_mode = (int)$result['water_box_mode'];
            $this->_SetValue(self::IDENT_WATER_QUANTITY, $water_box_mode);
            $ret['water_box_mode'] = $water_box_mode;
        }

        if (isset($result['water_box_status'])) {
            $water_box_status = (bool)$result['water_box_status'];
            $this->_SetValue(self::IDENT_WATER_BOX_STATUS, $water_box_status);
            $ret['water_box_status'] = $water_box_status;
        }

        if (isset($result['water_box_carriage_status'])) {
            $water_box_carriage_status = (bool)$result['water_box_carriage_status'];
            $this->_SetValue(self::IDENT_WATER_BOX_CARRIAGE_STATUS, $water_box_carriage_status);
            $ret['water_box_carriage_status'] = $water_box_carriage_status;
        }

        if (isset($result['map_status'])) {
            $prev_map_status = $this->GetValue(self::IDENT_MAP_STATUS);
            $map_status = (int)$result['map_status'] >> 2;
            $this->_SetValue(self::IDENT_MAP_STATUS, $map_status);
            $ret['map_status'] = $map_status;
            if ($map_status !== $prev_map_status && $this->ReadPropertyBoolean(self::PROPERTY_CLEANING_ORDER)) {
                $this->RequestData('get_room_mapping', ['immediate' => true]);
            }
        }


        // send push notifications
        $this->SendPushNotification('state', $state);
        $this->SendPushNotification('error', $error_code);

        // return values
        return $ret;
    }

    /**
     * Callback: Consumables.
     *
     * @param array $data
     *
     * @return array
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function get_consumable_callback(array $data): array
    {
        if (isset($data['result'][0])) {
            $consumables = [];
            $ret         = [];

            foreach ($this->device::CONSUMABLES as $ident => $name) {
                $max_work_time = consumable::GetMaxWorkTime($ident);
                if (!isset($data['result'][0][$name])) {
                    $this->_debug(__FUNCTION__, sprintf('Consumable \'%s\' not found.', $name));
                    continue;
                }
                $work_time = $data['result'][0][$name];
                if ($max_work_time['unit'] === 'hours') {
                    $work_time_percent = round(100 - (100 / ($max_work_time['value'] * 3600) * $work_time));
                } else {
                    $work_time_percent = round(100 - (100 / ($max_work_time['value']) * $work_time));
                }
                $consumables[] = [
                    $this->Translate(consumable::GetName($ident)),
                    $work_time_percent . '%'
                ];

                $ret[$ident] = $work_time_percent;

                // consumables separate
                if ($this->ReadPropertyBoolean(self::PROPERTY_CONSUMABLES_SEPARATE)) {
                    $this->_SetValue($ident, $work_time_percent);
                }
            }

            // consumables
            if ($this->ReadPropertyBoolean(self::PROPERTY_CONSUMABLES)) {
                $html = $this->_convertDataToTable([
                                                       'table' => [
                                                           'head' => [
                                                               $this->Translate('Consumable'),
                                                               $this->Translate('Residual (%)')
                                                           ],
                                                           'body' => $consumables
                                                       ]
                                                   ]);

                $this->_SetValue(self::IDENT_CONSUMABLES, $html);
            }

            return $ret;
        }

        // fallback
        return [];
    }

    /**
     * Callback: Clean Summary.
     *
     * @param array $data
     *
     * @return array
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function get_clean_summary_callback(array $data): array
    {
        $total_cleaning_time = null;
        $area_cleaned        = null;
        $cleanups            = null;
        $clean_records       = null;

        //clean_time
        if (isset($data['result'][0])) {
            $total_cleaning_time = $data['result'][0];
            $this->_SetValue('total_clean_time', $total_cleaning_time); // sec
        }

        if (isset($data['result']['clean_time'])) {
            $total_cleaning_time = $data['result']['clean_time'];
            $this->_SetValue('total_clean_time', $total_cleaning_time); // sec
        }

        //clean_area
        if (isset($data['result'][1])) {
            $area_cleaned = (float)$data['result'][1] / 1000000; // cm2 -> m2
            $this->_SetValue('total_clean_area', $area_cleaned);
        }

        if (isset($data['result']['clean_area'])) {
            $area_cleaned = (float)$data['result']['clean_area'] / 1000000; // cm2 -> m2
            $this->_SetValue('total_clean_area', $area_cleaned);
        }

        //clean_count
        if (isset($data['result'][2])) {
            $cleanups = (int)$data['result'][2];
            $this->_SetValue('total_cleans', $cleanups);
        }

        if (isset($data['result']['clean_count'])) {
            $cleanups = (int)$data['result']['clean_count'];
            $this->_SetValue('total_cleans', $cleanups);
        }

        //records
        if (isset($data['result'][3])) {
            $clean_records = $data['result'][3];
        }

        if (isset($data['result']['records'])) {
            $clean_records = $data['result']['records'];
        }

        // update clean record details
        foreach ($clean_records as $key => $record_id) {
            if ($key >= self::MAX_NUMBER_OF_CLEAN_RECORDS) {
                break;
            }
            $this->GetCleanRecord($record_id);
        }

        // return values
        return [
            'total_cleaning_time' => $total_cleaning_time,
            'area_cleaned'        => $area_cleaned,
            'cleanups'            => $cleanups,
            'clean_records'       => $clean_records
        ];
    }


    /**
     * Callback: Get Multi Maps List
     *
     * @param array $data
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function get_multi_maps_list_callback(array $data): void
    {
        if (!isset($data['result'][0]['multi_map_count'])) {
            return;
        }

        $ass       = [];
        $maps_list = [];

        $result = $data['result'][0];
        foreach ($result['map_info'] as $mapInfo) {
            $index = $mapInfo['mapFlag'];
            if ($mapInfo['name']) {
                $maps_list[$index] = [
                    'mapFlag' => $index,
                    'MapName' => $mapInfo['name']
                ];
                $ass[]             = [$index, $mapInfo['name'], '', -1];
            } else {
                $maps_list[$index] = [
                    'mapFlag' => $index,
                    'MapName' => $this->Translate('Map') . ($index + 1)
                ];
                $ass[]             = [$index, $this->Translate('Map') . ($index + 1), '', -1];
            }
        }

        if (count($ass)) {
            $this->RegisterProfileAssociation(
                self::PROFILE_MAPS,
                '',
                '',
                '',
                0,
                count($ass),
                0,
                0,
                VARIABLETYPE_INTEGER,
                $ass
            );
        }

        $this->UpdateAttributeMapsListWithMaps($maps_list);
    }

    private function UpdateAttributeMapsListWithMaps(array $maps): void
    {
        $savedList = json_decode($this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST), true, 512, JSON_THROW_ON_ERROR);

        foreach ($maps as $mapFlag => $map) {
            if (isset($savedList[$mapFlag])) {
                $savedList[$mapFlag]['MapName'] = $map['MapName'];
            } else {
                $savedList[$mapFlag]          = $map;
                $savedList[$mapFlag]['rooms'] = [];
            }
        }

        foreach (array_keys(array_diff_key($savedList, $maps)) as $mapFlag) {
            unset ($savedList[$mapFlag]);
        }

        $this->WriteAttributeString(self::ATTRIBUTE_MAPS_LIST, json_encode($savedList, JSON_THROW_ON_ERROR));
    }

    /**
     * Callback: Clean Record Details.
     *
     * @param array $data
     *
     * @return array
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function get_clean_record_callback(array $data): array
    {
        if (isset($data['result'][0])) {
            $record = $data['result'][0];

            $start_time = $record['begin'] ?? $record[0] ?? 0;

            $end_time = $record['end'] ?? $record[1] ?? 0;

            $cleaning_duration = $record['duration'] ?? $record[2] ?? 0;

            if ($cleaning_duration === 0) {
                $cleaning_duration = $end_time - $start_time;
            }

            $area = 0;
            if (isset($record[3])) {
                $area = (float)$record[3] / 1000000; //cm2 -> m2
            }
            if (isset($record['area'])) {
                $area = (float)$record['area'] / 1000000; //cm2 -> m2
            }

            $errors = $record['error'] ?? $record[4] ?? 0;

            $completed = $record['complete'] ?? $record[5] ?? 0;


            $data = [
                'starttime'        => $start_time,
                'endtime'          => $end_time,
                'cleaningduration' => $cleaning_duration,
                'area'             => $area,
                'errors'           => $errors,
                'completed'        => $completed
            ];

            // return when duration was 0s
            if ($cleaning_duration === 0) {
                return $data;
            }

            // update HTML, when enabled
            if ($this->ReadPropertyBoolean(self::PROPERTY_CLEAN_TIME)) {
                $html_data = [
                    $data['starttime'] => $data
                ];

                if ($cleaning_records = $this->ReadAttributeString(self::ATTRIBUTE_CLEANING_RECORDS)) {
                    //to be compatible with older module versions
                    if ($cleaning_records === '') {
                        $cleaning_records = '[]';
                    } else {
                        $cleaning_records = json_decode($cleaning_records, true, 512, JSON_THROW_ON_ERROR);
                    }
                    $this->_debug(__FUNCTION__, sprintf('cleaning_records: %s', $this->ReadAttributeString(self::ATTRIBUTE_CLEANING_RECORDS)));
                    $this->_debug(__FUNCTION__, sprintf('html_data: %s', json_encode($html_data, JSON_THROW_ON_ERROR)));

                    // merge cleaning records with HTML data
                    $cleaning_records[key($html_data)] = $html_data[key($html_data)];

                    // sort by key (time)
                    krsort($cleaning_records);

                    // show last 5 records, only
                    if (count($cleaning_records) > self::MAX_NUMBER_OF_CLEAN_RECORDS) {
                        $cleaning_records = array_slice($cleaning_records, 0, self::MAX_NUMBER_OF_CLEAN_RECORDS, true);
                    }
                }

                $this->WriteAttributeString(self::ATTRIBUTE_CLEANING_RECORDS, json_encode($cleaning_records, JSON_THROW_ON_ERROR));

                // build HTML
                $body_data = [];
                foreach ($cleaning_records as $clean_record) {
                    $start_time        = $clean_record['starttime'];
                    $start_hour        = date('H', $start_time);
                    $clean_day         = date('l', $start_time);
                    $clean_date        = date('d.m.', $start_time);
                    $start_minutes     = date('i', $start_time);
                    $end_time          = $clean_record['endtime'];
                    $end_hour          = date('H', $end_time);
                    $end_minutes       = date('i', $end_time);
                    $cleaning_duration = $this->_convertSecondsToTime($clean_record['cleaningduration']);
                    $area              = number_format($clean_record['area'], 1, ',', '.');
                    $errors            = $clean_record['errors'];
                    $completed         = $clean_record['completed'];

                    $body_data[] = [
                        $this->Translate($clean_day),
                        $clean_date . ' ' . $start_hour . ':' . $start_minutes . ' - ' . $end_hour . ':' . $end_minutes,
                        $cleaning_duration,
                        $area . ' m<sup>2</sup>',
                        ($errors ? '<span class="unicode red">✖</span>' : '-'),
                        ($completed ? '<span class="unicode green">✔</span>' : '<span class="unicode red">✖</span>')
                    ];
                }

                // build HTML table
                $head = [
                    $this->Translate('Day'),
                    $this->Translate('Date'),
                    $this->Translate('Cleaning Duration'),
                    $this->Translate('Area'),
                    $this->Translate('Errors'),
                    $this->Translate('Completed'),
                ];

                $html = $this->_convertDataToTable([
                                                       'table' => [
                                                           'head' => $head,
                                                           'body' => $body_data
                                                       ]
                                                   ]);

                // save HTML table
                $this->_SetValue('cleaning_records', $html);
            }

            return $data;
        }

        return [];
    }

    /**
     * Callback: DND Timer.
     *
     * @param array $data
     *
     * @return array
     */
    private function get_dnd_timer_callback(array $data): array
    {
        if (isset($data['result'][0]) && is_array($data['result'][0])) {
            $timer        = $data['result'][0];
            $dnd_state    = (bool)$timer['enabled'];
            $end_hour     = $this->_zeroPadding($timer['end_hour']);
            $end_minute   = $this->_zeroPadding($timer['end_minute']);
            $start_hour   = $this->_zeroPadding($timer['start_hour']);
            $start_minute = $this->_zeroPadding($timer['start_minute']);

            $start_time     = $start_hour . ':' . $start_minute;
            $start_unixtime = strtotime($start_time);
            $this->_SetValue('dnd_starttime', $start_unixtime);

            $end_time     = $end_hour . ':' . $end_minute;
            $end_unixtime = strtotime($end_time);

            $this->_SetValue('dnd_endtime', $end_unixtime);
            $this->_SetValue('dnd_mode', $dnd_state);

            // return values
            return [
                'start'          => $start_time,
                'start_unixtime' => $start_unixtime,
                'end'            => $end_time,
                'end_unixtime'   => $end_unixtime
            ];
        }

        // fallback
        return [
            'start'          => null,
            'start_unixtime' => null,
            'end'            => null,
            'end_unixtime'   => null
        ];
    }


    /**
     * Callback: Fan Power.
     *
     * @param array $data
     *
     * @return int
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function get_custom_mode_callback(array $data): int
    {
        if (isset($data['result'][0])) {
            $fan_power = $data['result'][0];
            $this->_SetValue(self::IDENT_FAN_POWER, $fan_power);

            return $fan_power;
        }

        // fallback
        return 0;
    }

    /**
     * Callback: Water Box Custom Mode.
     *
     * @param array $data
     *
     * @return int
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function get_water_box_custom_mode_callback(array $data): int
    {
        if (isset($data['result'][0])) {
            $water_flow_mode = $data['result'][0];
            $this->_SetValue(self::IDENT_WATER_QUANTITY, $water_flow_mode);

            return $water_flow_mode;
        }

        // fallback
        return 0;
    }

    /**
     * Callback: Get Sound Volume.
     *
     * @param array $data
     *
     * @return int
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function get_sound_volume_callback(array $data): int
    {
        if (isset($data['result'][0])) {
            $volume = $data['result'][0];
            $type   = gettype($volume);
            if ($type === 'integer') {
                $this->_SetValue(self::IDENT_VOLUME, $volume);
            }

            return $volume;
        }

        // fallback
        return 0;
    }

    /**
     * Callback: Start Remote Control.
     *
     * @param array $data
     *
     * @return bool
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function app_rc_start_callback(array $data): bool
    {
        // update state to 'Remote Control'
        $this->_SetValue('state', StateCode::REMOTE_CONTROL);
        return true;
    }

    /**
     * Callback: Stop Remote Control.
     *
     * @param array $data
     *
     * @return bool
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function app_rc_end_callback(array $data): bool
    {
        // update state to 'Waiting'
        $this->_SetValue('state', StateCode::WAITING);
        return true;
    }

    /**
     * Callback: Map v1
     *
     * @param array $data
     *
     * @return string
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function get_map_v1_callback(array $data): string
    {
        return $data['result'][0] ?? '';
    }

    private function UnregisterProfile(string $Name): void
    {
        if (!IPS_VariableProfileExists($Name)) {
            return;
        }

        foreach (IPS_GetVariableList() as $VarID) {
            if (IPS_GetParent($VarID) === $this->InstanceID) {
                continue;
            }
            if (IPS_GetVariable($VarID)['VariableCustomProfile'] === $Name) {
                return;
            }
            if (IPS_GetVariable($VarID)['VariableProfile'] === $Name) {
                return;
            }
        }

        foreach (IPS_GetMediaListByType(MEDIATYPE_CHART) as $mediaID) {
            $mediaContent = @IPS_GetMediaContent($mediaID);
            if (!is_string($mediaContent)) {
                continue;
            }
            $content = json_decode(base64_decode($mediaContent), true, 512, JSON_THROW_ON_ERROR);
            if (isset($content['axes'])) {
                foreach ($content['axes'] as $axis) {
                    if ($axis['profile'] === $Name) {
                        return;
                    }
                }
            }
        }

        IPS_DeleteVariableProfile($Name);
    }

    private function GetParent()
    {
        $instance = IPS_GetInstance($this->InstanceID); //array
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : 0; //ConnectionID
    }

}

/**
 * ApiVerifyIdentity
 */
class RoborockApiVerifyIdentity
{
    public const Phone = 4;
    public const Email = 8;

    public static array $TypeToPath = [
        self::Phone => 'https://account.xiaomi.com/identity/auth/verifyPhone?',
        self::Email => 'https://account.xiaomi.com/identity/auth/verifyEmail?',
    ];

    /**
     * getUrl
     *
     * @param int $Type
     *
     * @return string
     */
    public static function getUrl(int $Type): string
    {
        if (!array_key_exists($Type, self::$TypeToPath)) {
            throw new RuntimeException('Unknown verification type: ' . $Type);
        }
        return self::$TypeToPath[$Type];
    }

    /**
     * getMessageTextAndIndex
     *
     * @param int $Flag
     *
     * @return array
     */
    public static function getMessageTextAndIndex(int $Flag): array
    {
        return match ($Flag) {
            self::Email => [
                'Send the confirmation code to the email address (%s).',
                'maskedEmail'
            ],
            self::Phone => [
                'Send the confirmation code to the phone number (%s).',
                'maskedPhone'
            ],
            default => [],
        };
    }
}

/**
 * ApiCheckIdentity
 */
class RoborockApiCheckIdentity
{
    public const Phone = 4;
    public const Email = 8;

    public static $TypeToPath = [
        self::Phone => 'https://account.xiaomi.com/identity/auth/sendPhoneTicket?',
        self::Email => 'https://account.xiaomi.com/identity/auth/sendEmailTicket?',
    ];

    /**
     * getUrl
     *
     * @param int $Type
     *
     * @return string
     */
    public static function getUrl(int $Type): string
    {
        if (!array_key_exists($Type, self::$TypeToPath)) {
            throw new RuntimeException('Unknown verification type: ' . $Type);
        }
        return self::$TypeToPath[$Type];
    }
}
