<?php

declare(strict_types=1);

require_once __DIR__ . '/roborock_vacuum.php';
require_once __DIR__ . '/RRMapFileParser.php';
require_once __DIR__ . '/RRMapDraw.php';
require_once __DIR__ . '/../libs/VariablePresentations.php';
require_once __DIR__ . '/../libs/DebugMaskTrait.php';

use libs\VariablePresentations;

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
    use DebugMaskTrait;

    private const STATUS_INST_REGISTRATION_INCOMPLETE = 201;
    private const STATUS_INST_IP_ADDRESS_IS_INVALID   = 203;
    private const STATUS_INST_TOKEN_IS_INVALID        = 205;
    private const STATUS_INST_NO_ROBOROCK_FOUND       = 206;

    private const HTTP_OK               = 200;
    private const HTTP_UPGRADE_REQUIRED = 426; // Xiaomi-Cloud: abgelaufener ServiceToken

    private const MI_ERROR_TOKEN_EXPIRED = 'SERVICETOKEN_EXPIRED';

    private const ATTRIBUTE_TOKEN                   = 'token';
    private const ATTRIBUTE_LOGIN_LOCATION_DATA     = 'loginLocationData';
    private const ATTRIBUTE_LOGIN_ACCOUNT_DATA      = 'loginAccountData';
    private const ATTRIBUTE_LAST_NOTIFICATION_STATE = 'last_notification_state';
    private const ATTRIBUTE_LAST_NOTIFICATION_ERROR = 'last_notification_error';
    private const ATTRIBUTE_CLEANING_RECORDS        = 'cleaning_records';
    private const ATTRIBUTE_PENDING_CLEAN_SUMMARY   = 'pending_clean_summary';
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
    private const BUFFER_LAST_MAP_RAW      = 'last_map_raw'; // zuletzt geladene Karte (gz, base64) für den Download
    private const BUFFER_LAST_STATUS_MESSAGE = 'last_status_message'; // zuletzt geloggte Ursache eines Fehlerstatus
    private const BUFFER_REPORTED_MAP_BLOCK_TYPES ='reported_map_block_types'; // bereits gemeldete unbekannte Blocktypen (JSON-Liste)

    // Instanz-Buffer werden beim Speichern auf 512 KB gekürzt; größere Karten nicht ablegen
    private const MAX_MAP_RAW_BUFFER_LENGTH = 400 * 1024;

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

    //Legacy-Profile aus Vorgängerversionen; werden seit der Umstellung auf Presentations nur noch aufgeräumt
    private const LEGACY_PROFILES = [
        self::PROFILE_CONSUMABLE,
        self::PROFILE_COMMAND,
        self::PROFILE_BATTERY,
        self::PROFILE_CLEANAREA,
        self::PROFILE_START_CLEANING,
        self::PROFILE_CLEANING_CYCLES,
        self::PROFILE_DURATION,
        self::PROFILE_ERRORCODE,
        self::PROFILE_FANPOWER,
        self::PROFILE_FINDME,
        self::PROFILE_MAPS,
        self::PROFILE_STATE,
        self::PROFILE_TOTALCLEANS,
        self::PROFILE_VOLUME,
        self::PROFILE_WATERQUANTITY
    ];

    //Ident => Übersetzungsschlüssel der 'extended_info'-Variablen
    private const EXTENDED_INFO_VARIABLES = [
        'hw_ver'          => 'hardware version',
        'fw_ver'          => 'firmware version',
        'ssid'            => 'ssid',
        'rssi'            => 'rssi',
        'local_ip'        => 'local ip',
        self::IDENT_MODEL => 'model',
        'mac'             => 'mac'
    ];

    private const IDENT_SERIAL_NUMBER             = 'serial_number';
    private const IDENT_TIMEZONE                  = 'timezone';
    private const IDENT_VOLUME                    = 'volume';
    private const IDENT_COMMAND                   = 'command';
    private const IDENT_STATE                     = 'state';
    private const IDENT_FAN_POWER                 = 'fan_power';
    private const IDENT_WATER_QUANTITY            = 'water_quantity';
    private const IDENT_CONSUMABLES               = 'consumables';
    private const IDENT_CONSUMABLES_TEXT          = 'consumables_text';
    private const IDENT_CLEANING_RECORDS_TEXT     = 'cleaning_records_text';
    private const IDENT_WATER_BOX_STATUS          = 'water_box_status';
    private const IDENT_WATER_BOX_CARRIAGE_STATUS = 'water_box_carriage_status'; //Anmerkung: Der Unterschied zwischen 'water_box_status' und 'water_box_carriage_status' ist unklar
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
    private const MIN_VALUE_UPDATE_INTERVAL     = 10; // 0 = deaktiviert; kleinere positive Werte werden angehoben
    private const MAX_NUMBER_OF_CLEAN_RECORDS   = 5;
    private const MAX_LENGTH_FOREIGN_NAME       = 40; // Kartennamen aus der App (MCP-Regel 17)

    // helper properties
    private int             $position = 0;

    // RequestAction: Gerätebefehle sofort senden und ihr Ergebnis festhalten (MCP-Regel 8)
    private bool  $sendImmediately = false;
    // RunSelfTest: Antwort roh zurückgeben, ohne Callback (der schriebe Variablen und Attribute)
    private bool  $rawResponse = false;
    /** @var list<array{method: string, error: ?string}> error: null = bestätigt, '' = keine Antwort, sonst Ablehnung */
    private array $deviceRequests = [];

    // Statusvariablen mit Aktion; die ersten senden einen Befehl an den Sauger
    private const ACTION_IDENTS_DEVICE   = [
        self::IDENT_COMMAND, self::IDENT_VOLUME, self::IDENT_FAN_POWER, self::IDENT_WATER_QUANTITY, self::IDENT_MAP_STATUS,
        self::IDENT_START_CLEANING, 'dnd_mode', 'dnd_starttime', 'dnd_endtime'
    ];
    private const ACTION_IDENTS_VARIABLE = [self::IDENT_ROOMSELECTION, self::IDENT_CLEANING_CYCLES];

    private roborock_vacuum $device;

    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID);

        // Bei einer neu angelegten Instanz läuft der Konstruktor vor Create(): Das Attribut gibt es dann noch nicht.
        try {
            $model = @$this->ReadAttributeString(self::ATTRIBUTE_MODEL);
        } catch (Throwable) {
            $model = '';
        }
        if ($model && ($modelClassName = str_replace('.', '_', $model))
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

        // register attributes
        $this->RegisterAttributeString(self::ATTRIBUTE_TOKEN, '');
        $this->RegisterAttributeString(self::ATTRIBUTE_LOGIN_LOCATION_DATA, json_encode([], JSON_THROW_ON_ERROR));
        $this->RegisterAttributeString(self::ATTRIBUTE_LOGIN_ACCOUNT_DATA, json_encode([], JSON_THROW_ON_ERROR));
        $this->RegisterAttributeString(self::ATTRIBUTE_LAST_NOTIFICATION_STATE, '');
        $this->RegisterAttributeString(self::ATTRIBUTE_LAST_NOTIFICATION_ERROR, '');
        $this->RegisterAttributeString(self::ATTRIBUTE_CLEANING_RECORDS, '[]');
        $this->RegisterAttributeBoolean(self::ATTRIBUTE_PENDING_CLEAN_SUMMARY, false);
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
        $this->UnregisterLegacyProfiles();

        parent::Destroy();
    }

    /**
     * apply changes from the configuration form.
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        // remove old profiles from previous versions once we switched to presentations
        $this->UnregisterLegacyProfiles();

        $this->RegisterControlVariables();
        $this->RegisterMapVariables();
        $this->RegisterStatusVariables();
        $this->RegisterCleaningOrderVariables();

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
     * Text aus fremder Quelle (App des Herstellers) für Namen und Optionen: Steuerzeichen und
     * mehrfache Leerzeichen entfernen, auf $maxLength Zeichen kürzen. Er landet sonst unverändert
     * im Kontext jeder KI, die die Variable findet (MCP-Regel 17).
     */
    private static function CleanForeignText(string $text, int $maxLength): string
    {
        $text = trim((string)preg_replace(['/\p{C}+/u', '/\s+/u'], [' ', ' '], $text));
        return mb_strlen($text) > $maxLength ? rtrim(mb_substr($text, 0, $maxLength - 1)) . '…' : $text;
    }

    /**
     * Variable, die das Modul nicht mehr versorgt, als veraltet kennzeichnen (MCP-Regel 14) — sie
     * bleibt erhalten, löschen muss der Anwender. Eine KI erkennt sonst nicht, dass der Wert tot ist.
     */
    private function MarkObsoleteVariable(string $ident): void
    {
        $id = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if (!$id) {
            return;
        }
        $suffix = $this->Translate('(obsolete)');
        $name   = IPS_GetName($id);
        if (!str_ends_with($name, $suffix)) {
            IPS_SetName($id, $name . ' ' . $suffix);
        }
    }

    private function UnregisterLegacyProfiles(): void
    {
        $this->UnregisterProfile(sprintf('%s.%s', self::PROFILE_ROOMSELECTION, $this->InstanceID));
        foreach (self::LEGACY_PROFILES as $profile) {
            $this->UnregisterProfile($profile);
        }
    }

    /**
     * Baut eine Enumeration-Presentation aus einer Geräte-Konstante (Name => Wert),
     * z. B. FANPOWER oder WATERQUANTITY.
     */
    private function GetDeviceEnumerationPresentation(array $valuesByName): array
    {
        $options = [];
        foreach ($valuesByName as $name => $value) {
            $options[] = ['Value' => $value, 'Caption' => $this->Translate($name)];
        }

        return VariablePresentations::enumeration($options);
    }

    private function RegisterControlVariables(): void
    {
        // Remote Control
        if ($this->ReadPropertyBoolean(self::PROPERTY_REMOTE)) {
            if ($this->RegisterVariableString(self::IDENT_REMOTE_CONTROL, $this->Translate('Remote Control'), VariablePresentations::webContent(), $this->_getPosition())) {
                IPS_SetIcon($this->GetIDForIdent(self::IDENT_REMOTE_CONTROL), 'Move');
                $this->SetJoystickHtml();
            }
        } else {
            $this->UnregisterVariable(self::IDENT_REMOTE_CONTROL);
        }

        // command
        $commandPresentation = VariablePresentations::enumeration([
            ['Value' => 0, 'Caption' => $this->Translate('Start'), 'IconValue' => 'HollowLargeArrowRight'],
            ['Value' => 1, 'Caption' => $this->Translate('Pause'), 'IconValue' => 'Close'],
            ['Value' => 2, 'Caption' => $this->Translate('Stop'), 'IconValue' => 'Close'],
            ['Value' => 3, 'Caption' => $this->Translate('Spot'), 'IconValue' => 'Climate'],
            ['Value' => 4, 'Caption' => $this->Translate('Charge'), 'IconValue' => 'Battery'],
            ['Value' => 5, 'Caption' => $this->Translate('Locate'), 'IconValue' => 'Motion']
        ]);
        $this->RegisterVariableInteger(self::IDENT_COMMAND, $this->Translate('Command'), $commandPresentation, $this->_getPosition());
        $this->EnableAction(self::IDENT_COMMAND);

        // current state
        $stateOptions = [];
        foreach (StateCode::cases() as $state) {
            $stateOptions[] = ['Value' => $state->value, 'Caption' => $this->Translate($state->getDescription())];
        }
        $this->RegisterVariableInteger(self::IDENT_STATE, $this->Translate('State'), VariablePresentations::valueEnumeration($stateOptions), $this->_getPosition());

        // current battery level
        $this->RegisterVariableInteger('battery', $this->Translate('Battery'), VariablePresentations::value(0, 100, ' %', 0), $this->_getPosition());

        // fan power
        if ($this->ReadPropertyBoolean(self::PROPERTY_FAN_POWER)) {
            $this->RegisterVariableInteger(
                self::IDENT_FAN_POWER,
                $this->Translate('Fan Power'),
                $this->GetDeviceEnumerationPresentation($this->device::FANPOWER),
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
                $this->GetDeviceEnumerationPresentation($this->device::WATERQUANTITY),
                $this->_getPosition()
            );
            $this->RegisterVariableBoolean(self::IDENT_WATER_BOX_STATUS, $this->Translate('Water Box installed'), VariablePresentations::switch(), $this->_getPosition());
            $this->RegisterVariableBoolean(
                self::IDENT_WATER_BOX_CARRIAGE_STATUS,
                $this->Translate('Water Box Carriage Status'),
                VariablePresentations::switch(),
                $this->_getPosition()
            );
            $this->EnableAction(self::IDENT_WATER_QUANTITY);
        } else {
            $this->UnregisterVariable(self::IDENT_WATER_QUANTITY);
            $this->UnregisterVariable(self::IDENT_WATER_BOX_STATUS);
            $this->UnregisterVariable(self::IDENT_WATER_BOX_CARRIAGE_STATUS);
        }
    }

    private function RegisterMapVariables(): void
    {
        // map_status
        if ($this->ReadPropertyBoolean(self::PROPERTY_MAP_STATUS) || $this->ReadPropertyBoolean(self::PROPERTY_CLEANING_ORDER)) {
            $this->RegisterVariableInteger(self::IDENT_MAP_STATUS, $this->Translate('Active Map'), $this->GetMapStatusPresentation(), $this->_getPosition());
            $this->EnableAction(self::IDENT_MAP_STATUS);
            // when MAP_STATUS is created, we can update the RoomSelectionPresentation
            $this->WriteRoomSelectionPresentation();
        } else {
            $this->UnregisterVariable(self::IDENT_MAP_STATUS);
        }

        // map_picture
        if ($this->ReadPropertyBoolean(self::PROPERTY_MAP_PICTURE)) {
            $this->CreateMapPictureVariable(self::IDENT_MAP_PICTURE, 'Map', sprintf('Map_%s.png', $this->InstanceID));
        }
    }

    private function RegisterStatusVariables(): void
    {
        // volume
        if ($this->ReadPropertyBoolean(self::PROPERTY_VOLUME)) {
            $this->RegisterVariableInteger(self::IDENT_VOLUME, $this->Translate('Volume'), VariablePresentations::slider(0, 100, 1, ' %', 0), $this->_getPosition());
            $this->EnableAction(self::IDENT_VOLUME);
        } else {
            $this->UnregisterVariable(self::IDENT_VOLUME);
        }

        // error code
        if ($this->ReadPropertyBoolean('error_code')) {
            $errorOptions = [];
            foreach (ErrorCode::cases() as $error) {
                $errorOptions[] = ['Value' => $error->value, 'Caption' => $this->Translate($error->getDescription())];
            }
            $this->RegisterVariableInteger('error_code', $this->Translate('Error Code'), VariablePresentations::valueEnumeration($errorOptions), $this->_getPosition());
        } else {
            $this->UnregisterVariable('error_code');
        }

        // consumables
        if ($this->ReadPropertyBoolean(self::PROPERTY_CONSUMABLES)) {
            // Klartext neben der HTML-Tabelle (MCP-Regel 9), gleiche Position — die übrigen rücken nicht nach
            $position = $this->_getPosition();
            $this->RegisterVariableString(self::IDENT_CONSUMABLES, $this->Translate('Consumables'), VariablePresentations::webContent(), $position);
            $this->RegisterVariableString(self::IDENT_CONSUMABLES_TEXT, $this->Translate('Consumables (Text)'), '', $position);
        } else {
            $this->UnregisterVariable(self::IDENT_CONSUMABLES);
            $this->UnregisterVariable(self::IDENT_CONSUMABLES_TEXT);
        }

        // consumables separate
        if ($this->ReadPropertyBoolean(self::PROPERTY_CONSUMABLES_SEPARATE)) {
            foreach ($this->device::CONSUMABLES as $ident => $consumable) {
                $this->RegisterVariableInteger(
                    $ident,
                    $this->Translate(consumable::GetName($ident)),
                    VariablePresentations::value(0, 100, ' %', 0),
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
            $this->RegisterVariableBoolean('dnd_mode', $this->Translate('DND Mode'), VariablePresentations::switch(), $this->_getPosition());
            $this->EnableAction('dnd_mode');
            $this->RegisterVariableInteger('dnd_starttime', $this->Translate('DND Starttime'), VariablePresentations::timeOnly(), $this->_getPosition());
            $this->EnableAction('dnd_starttime');
            $this->RegisterVariableInteger('dnd_endtime', $this->Translate('DND Endtime'), VariablePresentations::timeOnly(), $this->_getPosition());
            $this->EnableAction('dnd_endtime');
        } else {
            $this->UnregisterVariable('dnd_mode');
            $this->UnregisterVariable('dnd_starttime');
            $this->UnregisterVariable('dnd_endtime');
        }

        // clean area
        if ($this->ReadPropertyBoolean('clean_area')) {
            $this->RegisterVariableFloat('clean_area', $this->Translate('Clean Area'), VariablePresentations::value(0, 0, ' m²', 1), $this->_getPosition());
            $this->RegisterVariableFloat('total_clean_area', $this->Translate('Total Clean Area'), VariablePresentations::value(0, 0, ' m²', 1), $this->_getPosition());
        } else {
            $this->UnregisterVariable('clean_area');
            $this->UnregisterVariable('total_clean_area');
        }

        // clean_time
        if ($this->ReadPropertyBoolean(self::PROPERTY_CLEAN_TIME)) {
            $this->RegisterVariableInteger('clean_time', $this->Translate('Clean Time'), VariablePresentations::value(0, 0, ' s', 0), $this->_getPosition());
            $this->RegisterVariableInteger('total_clean_time', $this->Translate('Total Clean Time'), VariablePresentations::value(0, 0, ' s', 0), $this->_getPosition());
            $position = $this->_getPosition();
            $this->RegisterVariableString('cleaning_records', $this->Translate('Cleaning Records'), VariablePresentations::webContent(), $position);
            $this->RegisterVariableString(self::IDENT_CLEANING_RECORDS_TEXT, $this->Translate('Cleaning Records (Text)'), '', $position);
            // aus den gespeicherten Reinigungen füllen — sonst bliebe der mit 2.4 neue Klartext bis zur nächsten Reinigung leer
            $records = $this->SafeJsonDecode($this->ReadAttributeString(self::ATTRIBUTE_CLEANING_RECORDS), __FUNCTION__ . ' cleaning_records');
            if (is_array($records) && $records !== []) {
                $this->WriteCleaningRecordVariables($records);
            }
        } else {
            $this->UnregisterVariable('clean_time');
            $this->UnregisterVariable('total_clean_time');
            $this->UnregisterVariable('cleaning_records');
            $this->UnregisterVariable(self::IDENT_CLEANING_RECORDS_TEXT);
        }

        // Altlast: „Aktuelle Koordinaten“ füllte nur der alte Karten-Upload für gerootete Geräte
        $this->MarkObsoleteVariable('coordinates');

        // total cleans
        if ($this->ReadPropertyBoolean('total_cleans')) {
            $this->RegisterVariableInteger('total_cleans', $this->Translate('Total Cleans'), VariablePresentations::value(), $this->_getPosition());
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

        // Option „Timer Details“ ist in 2.4 build 100 entfallen (Variable seit 2.2 #65 nicht mehr befüllt)
        $this->UnregisterVariable('timer_details');

        // extended info
        if ($this->ReadPropertyBoolean('extended_info')) {
            foreach (self::EXTENDED_INFO_VARIABLES as $ident => $caption) {
                $this->RegisterVariableString($ident, $this->Translate($caption), '', $this->_getPosition());
            }
        } else {
            foreach (array_keys(self::EXTENDED_INFO_VARIABLES) as $ident) {
                $this->UnregisterVariable($ident);
            }
        }

        // Timezone
        if ($this->ReadPropertyBoolean('timezone')) {
            $this->RegisterVariableString(self::IDENT_TIMEZONE, $this->Translate('Timezone'), '', $this->_getPosition());
        } else {
            $this->UnregisterVariable(self::IDENT_TIMEZONE);
        }
    }

    private function RegisterCleaningOrderVariables(): void
    {
        if ($this->ReadPropertyBoolean(self::PROPERTY_CLEANING_ORDER)) {
            $this->RegisterVariableInteger(
                self::IDENT_ROOMSELECTION,
                $this->Translate('Roomselection'),
                $this->GetRoomSelectionPresentation(),
                $this->_getPosition()
            );
            $this->EnableAction(self::IDENT_ROOMSELECTION);
            $this->_SetValue(self::IDENT_ROOMSELECTION, -1);

            $this->RegisterVariableString(self::IDENT_ROOMS_SELECTED, $this->Translate('Selected Rooms'), '', $this->_getPosition());

            $cleaningCycleOptions = [];
            for ($i = 1; $i <= 3; $i++) {
                $cleaningCycleOptions[] = ['Value' => $i, 'Caption' => sprintf('%sx', $i)];
            }

            $this->RegisterVariableInteger(
                self::IDENT_CLEANING_CYCLES,
                $this->Translate('Cleaning Cycles'),
                VariablePresentations::enumeration($cleaningCycleOptions),
                $this->_getPosition()
            );
            if ((int)$this->GetValue(self::IDENT_CLEANING_CYCLES) === 0) {
                $this->_SetValue(self::IDENT_CLEANING_CYCLES, 1);
            }
            $this->EnableAction(self::IDENT_CLEANING_CYCLES);

            $this->RegisterVariableInteger(
                self::IDENT_START_CLEANING,
                $this->Translate('Start Cleaning'),
                VariablePresentations::enumeration([['Value' => 1, 'Caption' => $this->Translate('Start')]]),
                $this->_getPosition()
            );
            $this->EnableAction(self::IDENT_START_CLEANING);
        }
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
                // Parent status changes can occur while interfaces are not yet available.
                // Reconfigure only when parent is active and kernel is ready.
                if (IPS_GetKernelRunlevel() !== KR_READY) {
                    break;
                }
                if ($SenderID !== $this->GetParent()) {
                    break;
                }
                if (($Data[0] ?? 0) !== IS_ACTIVE) {
                    break;
                }
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
            // ohne IP ist die Instanz noch nicht eingerichtet (frisch angelegt) — dann keine Warnung
            $this->SetStatusAndLog(
                self::STATUS_INST_IP_ADDRESS_IS_INVALID,
                $ip === '' ? '' : $this->InvalidIpText($ip)
            );
            $this->SendDebug(__FUNCTION__, (string)$this->GetStatus(), 0);
            return false;
        }

        // check if configuration is complete
        if (!$this->CheckUserAndPassword()) {
            $this->SetStatusAndLog(
                self::STATUS_INST_REGISTRATION_INCOMPLETE,
                $this->Translate('There is no device token and it could not be fetched: Xiaomi user or password are missing, or the login to the Xiaomi cloud failed. Please check the Xiaomi account data in the configuration.')
            );
            $this->SendDebug(__FUNCTION__, (string)$this->GetStatus(), 0);
            return false;
        }

        // check token
        if (!$this->ValidateToken()) {
            $this->SetStatusAndLog(
                self::STATUS_INST_TOKEN_IS_INVALID,
                $this->InvalidTokenText(strlen($this->ReadAttributeString(self::ATTRIBUTE_TOKEN)))
            );
            $this->SendDebug(__FUNCTION__, (string)$this->GetStatus(), 0);
            return false;
        }

        // get device info
        $info = $this->RequestData('miIO.info', [
            'immediate' => true
        ]);

        if (!$info) {
            $interval = $this->UpdateIntervalSeconds();
            $this->SetStatusAndLog(
                self::STATUS_INST_NO_ROBOROCK_FOUND,
                $interval > 0
                    ? sprintf(
                        $this->Translate('The vacuum cleaner at %s does not respond. The module tries again at every update (every %d s) and reports when it responds again; if it stays unreachable, check its IP address and WiFi.'),
                        $ip,
                        $interval
                    )
                    : sprintf(
                        $this->Translate('The vacuum cleaner at %s does not respond. Automatic updates are disabled, so it is not checked again; apply the configuration to retry.'),
                        $ip
                    )
            );
            $this->SendDebug(__FUNCTION__, (string)$this->GetStatus(), 0);
            return false;
        }

        $this->_debug('info', json_encode($info, JSON_THROW_ON_ERROR));

        // yay, the configuration is valid!
        $this->SetStatusAndLog(IS_ACTIVE);

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

    /** Störungstexte, die Statusprüfung und Selbsttest gleich formulieren */
    private function InvalidIpText(string $ip): string
    {
        return sprintf($this->Translate("The IP address '%s' is not valid. Please enter the IP address of the vacuum cleaner in the configuration."), $ip);
    }

    private function InvalidTokenText(int $length): string
    {
        return sprintf(
            $this->Translate('The device token is invalid (32 characters expected, %d found). Please set a valid token with Roborock_SetDeviceToken.'),
            $length
        );
    }

    /**
     * Status setzen; beim Wechsel in einen Fehlerstatus die Ursache samt nächstem Schritt als Warnung
     * ins Log, bei der Rückkehr auf „aktiv" eine Meldung. Bleibt der Status gleich, kein Eintrag —
     * 206 wird bei jeder Aktualisierung neu geprüft.
     */
    private function SetStatusAndLog(int $status, string $message = ''): void
    {
        $previous = $this->GetStatus();
        if ($message !== '') {
            // neue Ursache (anderer Status oder andere Meldung, z. B. eine andere falsche IP) → Warnung
            if ($status !== $previous || $message !== $this->GetBuffer(self::BUFFER_LAST_STATUS_MESSAGE)) {
                $this->LogMessage($message, KL_WARNING);
            }
        } elseif ($status === IS_ACTIVE && $previous >= IS_EBASE) {
            // „antwortet wieder" nur nach 206 — nach einem Konfigurationsfehler war er nie unerreichbar
            $this->LogMessage(
                $previous === self::STATUS_INST_NO_ROBOROCK_FOUND
                    ? $this->Translate('The vacuum cleaner responds again, the instance is active.')
                    : $this->Translate('The configuration is complete and the vacuum cleaner responds, the instance is active.'),
                KL_MESSAGE
            );
        }
        $this->SetBuffer(self::BUFFER_LAST_STATUS_MESSAGE, $message);
        $this->SetStatus($status);
    }

    /**
     * wirksames Aktualisierungsintervall in Sekunden (0 = deaktiviert, sonst mindestens MIN_VALUE_UPDATE_INTERVAL).
     * Zu kurze Werte stauen bei langsamer (Cloud-)Verbindung die Warteschlange, da ein kompletter
     * Update-Zyklus deutlich länger dauern kann als das Intervall.
     */
    private function UpdateIntervalSeconds(): int
    {
        $interval = $this->ReadPropertyInteger(self::PROPERTY_UPDATE_INTERVAL);
        return ($interval > 0) ? max($interval, self::MIN_VALUE_UPDATE_INTERVAL) : 0;
    }

    /**
     * set / unset update interval.
     *
     */
    private function SetUpdateInterval(): void
    {
        // 206 (Roboter antwortet nicht) ist vorübergehend: Der Update-Zyklus prüft bei jedem Lauf neu
        // und holt die Instanz zurück. Ohne Timer bliebe sie nach einem einzigen Aussetzer beim
        // ApplyChanges (Kernel-Neustart, Modul-Update) dauerhaft auf 206.
        if (in_array($this->GetStatus(), [IS_ACTIVE, self::STATUS_INST_NO_ROBOROCK_FOUND], true)) {
            $interval = $this->UpdateIntervalSeconds();
            if ($interval !== $this->ReadPropertyInteger(self::PROPERTY_UPDATE_INTERVAL)) {
                $this->_debug(
                    __FUNCTION__,
                    sprintf('Update-Intervall %ds zu kurz - auf %ds angehoben.', $this->ReadPropertyInteger(self::PROPERTY_UPDATE_INTERVAL), $interval)
                );
            }
            $interval *= 1000;
        } else {
            $interval = 0;
        }
        $this->SetTimerInterval(self::TIMER_UPDATE, $interval);

        if ($interval === 0) {
            $this->SetTimerInterval(self::TIMER_UPDATE_MAP, 0);
        }
    }

    public function SetDeviceToken(string $deviceToken): void
    {
        $this->WriteAttributeString(self::ATTRIBUTE_TOKEN, $deviceToken);

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

        // Re-Entrancy-Schutz: verhindert, dass sich Update-Zyklen überlappen (z. B. bei
        // langsamer Cloud-Verbindung, wo ein Zyklus länger als das Intervall dauert) und
        // dadurch die serielle Nachrichten-Warteschlange stauen.
        $semaphore = 'Roborock_Update_' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($semaphore, 0)) {
            $this->_debug(__FUNCTION__, 'vorheriger Update-Zyklus läuft noch - Tick übersprungen');
            return;
        }

        try {
            $this->UpdateInternal();
        } finally {
            IPS_SemaphoreLeave($semaphore);
        }

        $this->_debug(__FUNCTION__ . ': finish');
    }

    private function UpdateInternal(): void
    {
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

            if (in_array($this->GetValue(self::IDENT_STATE), [
                StateCode::REMOTE_CONTROL->value,
                StateCode::CLEANING->value,
                StateCode::RETURNING_TO_BASE->value,
                StateCode::MANUAL_MODE->value,
                StateCode::SPOT_CLEANING->value,
                StateCode::DOCKING->value,
                StateCode::GO_TO->value,
                StateCode::ZONE_CLEAN->value,
                StateCode::ROOM_CLEAN->value,
                StateCode::RETURNING_TO_BASE_FOR_MOP_WASHING->value
            ], true)) {
                $this->SetTimerInterval(self::TIMER_UPDATE_MAP, 10000);
            } elseif ($this->GetTimerInterval(self::TIMER_UPDATE_MAP) !== 0) {
                // Reinigung gerade beendet: finale Karte holen, dann Map-Timer stoppen
                $this->GetMap();
                $this->SetTimerInterval(self::TIMER_UPDATE_MAP, 0);
                // update clean summary
                if ($this->ReadPropertyBoolean(self::PROPERTY_CLEAN_TIME)) {
                    $this->GetCleanSummary();
                    // Datensatz wird geräteseitig oft erst kurz nach Reinigungsende finalisiert
                    // -> im nächsten Zyklus erneut nachladen.
                    $this->WriteAttributeBoolean(self::ATTRIBUTE_PENDING_CLEAN_SUMMARY, true);
                }
            } elseif ($this->ReadPropertyBoolean(self::PROPERTY_CLEAN_TIME)
                      && $this->ReadAttributeBoolean(self::ATTRIBUTE_PENDING_CLEAN_SUMMARY)) {
                // ein Zyklus nach Reinigungsende: spät finalisierten Datensatz nachladen
                $this->GetCleanSummary();
                $this->WriteAttributeBoolean(self::ATTRIBUTE_PENDING_CLEAN_SUMMARY, false);
            }
        }
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

        // send it to an i/o device
        $this->_debug('send', json_encode($buffer, JSON_THROW_ON_ERROR));

        $data = json_encode(['DataID' => '{F7DC50D6-DCE6-27CE-49B2-A363593EBB3B}', 'Buffer' => $buffer], JSON_THROW_ON_ERROR);
        if ($io = @$this->SendDataToParent($data)) {
            // return data
            try {
                return json_decode($io, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $ex) {
                $this->_debug(__FUNCTION__, 'Invalid JSON from parent: ' . $ex->getMessage());
                return false;
            }
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
        // Ist der Befehl einer Aktion gescheitert, keine Folgeabfragen hinterher — jede hätte ihren eigenen
        // Timeout samt Wiederholung, und die Aktion stünde ohne Nutzen eine halbe Minute.
        if ($this->ActionCommandFailed()) {
            $this->_debug(__FUNCTION__, sprintf('%s skipped, the command of this action failed', $method));
            return false;
        }

        $request_id = (int) $this->GetBuffer('request_id');
        $request_id++;
        if ($request_id >= 9999) {
            $request_id = 1;
        }
        $this->SetBuffer('request_id', (string) $request_id);

        // build payload
        $payload = [
            'InstanceID' => $this->InstanceID,
            'token'      => $this->ReadAttributeString(self::ATTRIBUTE_TOKEN),
            'ip'         => $this->ReadPropertyString(self::PROPERTY_IP),
            'request_id' => $this->InstanceID . '-' . $request_id,
            'immediate'  => false,
            'method'     => $method,
            'params'     => []
        ];

        // force immediate option on ips sender

        //wenn ein Aufruf direkt erfolgt und nicht aus der Instanz heraus, dann soll er sofort ausgeführt werden /** @noinspection PhpUndefinedVariableInspection */
        //$this->SendDebug('IPS', json_encode($_IPS, JSON_THROW_ON_ERROR), 0); /** @noinspection PhpUndefinedVariableInspection */
        /** @global array $_IPS */
        $self   = $_IPS['SELF'] ?? 0;   // $_IPS gibt es nur in Symcon, nicht im CLI-PHP der Tests
        $sender = $_IPS['SENDER'] ?? '';
        if (($self > 0 && $self !== $this->InstanceID)
            || in_array($sender, ['Execute', 'Variable', 'RunScript', 'PHPModule'])
            || $this->sendImmediately) {
            $payload['immediate'] = true;
        }

        // merge payload & options
        $buffer = array_merge($payload, $options);

        // send it to an i/o device
        $this->_debug('send', json_encode($buffer, JSON_THROW_ON_ERROR));

        $data = json_encode(['DataID' => '{F7DC50D6-DCE6-27CE-49B2-A363593EBB3B}', 'Buffer' => $buffer], JSON_THROW_ON_ERROR);
        if ($io_json = @$this->SendDataToParent($data)) {
            // receive data on immediate requests
            $this->_debug('send (return)', $io_json);

            if ($buffer['immediate']) {
                $io = json_decode($io_json, true, 512, JSON_THROW_ON_ERROR);
                if ($io) {
                    $this->deviceRequests[] = $this->DeviceRequestResult($method, $io);

                    // merge buffer
                    $data = array_merge($buffer, $io);

                    // return data
                    return $this->rawResponse ? $data : $this->ExecuteCallback($data);
                }
                $this->deviceRequests[] = ['method' => $method, 'result' => 'silent', 'error' => ''];
                return false;
            }

            return true;
        }

        if ($buffer['immediate']) {
            $this->deviceRequests[] = ['method' => $method, 'result' => 'silent', 'error' => ''];
        }
        return false;
    }

    /**
     * Läuft gerade eine Aktion aus RequestAction, deren Befehl (die erste Anfrage) gescheitert ist?
     */
    private function ActionCommandFailed(): bool
    {
        return $this->sendImmediately && ($this->deviceRequests[0]['result'] ?? 'ok') !== 'ok';
    }

    /**
     * Ordnet die Antwort der IO auf eine sofortige Anfrage ein: ok, vom Sauger abgelehnt oder unklar.
     * Eine Ablehnung trägt das Fehlerobjekt des Saugers (code, message); jede andere Antwort mit "error"
     * hat die IO selbst verpackt (_validateResponse, z. B. bei falscher Message-ID) — ob der Befehl
     * ausgeführt wurde, ist dann offen.
     */
    private function DeviceRequestResult(string $method, array $io): array
    {
        if (!isset($io['error'])) {
            return ['method' => $method, 'result' => 'ok', 'error' => null];
        }
        $rejected = is_array($io['error']) && isset($io['error']['error']);
        $detail   = $rejected ? $io['error']['error'] : $io['error'];
        return [
            'method' => $method,
            'result' => $rejected ? 'rejected' : 'unclear',
            'error'  => mb_substr(json_encode($detail, JSON_UNESCAPED_UNICODE) ?: '?', 0, 200)
        ];
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
        // convert JSON payload to array
        $payload = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);

        // extract buffer
        $buffer = $payload['Buffer'];

        // check token and save, if diffs from the current one
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
     * Check if a callback exists and execute the method.
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

        // return the original buffer when no callback was found
        $this->_debug('receive', json_encode($buffer, JSON_THROW_ON_ERROR));
        return $buffer;
    }

    /**
     * validate token.
     *
     * @return bool
     */
    /** Token in der verschlüsselten Form (96 Zeichen) entschlüsseln; '' bei Fehler */
    private static function DecryptToken(string $token): string
    {
        return (string)openssl_decrypt((string)hex2bin($token), 'aes-128-ecb', str_repeat("\0", 16), OPENSSL_RAW_DATA);
    }

    private function ValidateToken(): bool
    {
        $token = $this->ReadAttributeString(self::ATTRIBUTE_TOKEN);

        // convert token on 96 byte length
        if (strlen($token) === 96) {
            $token = self::DecryptToken($token);

            // save attribute
            $this->WriteAttributeString(self::ATTRIBUTE_TOKEN, $token);
        }

        // return true, when token length is 32 bytes
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
        $this->Get_State(); // Status sofort aktualisieren (statt erst beim nächsten Update-Timer)
        $this->StartMapUpdates(); // Karte sofort holen und Map-Timer starten
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
        $this->Get_State(); // Status sofort aktualisieren
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
        $this->Get_State(); // Status sofort aktualisieren
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
        $this->Get_State(); // Status sofort aktualisieren
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
        $this->Get_State(); // Status sofort aktualisieren
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
        $roomSelection  = $this->SafeJsonDecode(
            $this->ReadAttributeString(self::ATTRIBUTE_ROOM_SELECTION),
            __FUNCTION__ . ' room_selection'
        ) ?? [];
        $segments       = array_keys($roomSelection);
        $cleaningCycles = (int)$this->GetValue(self::IDENT_CLEANING_CYCLES);
        if ($cleaningCycles === 1) {
            $this->Start_Segment_Clean_Ex(json_encode($segments, JSON_THROW_ON_ERROR));
        } else {
            $this->Start_Segment_Clean_Ex(json_encode([['segments' => $segments, 'repeat' => $cleaningCycles]], JSON_THROW_ON_ERROR));
        }
        $this->Get_State(); // Status sofort aktualisieren
        $this->StartMapUpdates(); // Karte sofort holen und Map-Timer starten
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
     * @param string $consumableKey filter|mainbrush|sidebrush|sensors
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function Reset_Consumable(string $consumableKey): array|bool
    {
        return $this->RequestData('reset_consumable', [
            'params' => [$consumableKey]
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
     * get a clean summary.
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
        $pic = new RRMapFileParser(
            $data,
            function (string $message, string $data): void {
                $this->_debug($message, $data);
            },
            function (string $message, string $data): void {
                $this->LogMessage($message . ': ' . $data, KL_WARNING);
            }
        );
        $this->ReportUnknownMapBlockTypes($pic->getUnknownBlockTypes());
        if (!$pic->isValid()) {
            $this->_debug(__FUNCTION__, sprintf('pic is invalid: %s', $filename));
            return false;
        }

        $draw    = new RRMapDraw($pic, function (string $message, string $data): void {
            $this->LogMessage($message . ': ' . $data, KL_WARNING);
        });
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
        if ($responsecode !== self::HTTP_OK) {
            $this->_debug(
                __FUNCTION__,
                sprintf('%s: responsecode: %s, curl_getinfo: %s', __FUNCTION__,
                    json_encode($responsecode, JSON_THROW_ON_ERROR),
                    json_encode(curl_getinfo($ch), JSON_THROW_ON_ERROR)
                )
            );
            return '';
        }

        $rawBase64 = base64_encode($result);
        if (strlen($rawBase64) <= self::MAX_MAP_RAW_BUFFER_LENGTH) {
            $this->SetBuffer(self::BUFFER_LAST_MAP_RAW, $rawBase64);
        } else {
            $this->SetBuffer(self::BUFFER_LAST_MAP_RAW, '');
            $this->_debug(__FUNCTION__, sprintf('map too large for download buffer: %s bytes', strlen($result)));
        }

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
                $mapName = $this->RequestData('get_map_v1', ['immediate' => true]);
                $this->_debug(__FUNCTION__, sprintf('mapName: %s', json_encode($mapName, JSON_THROW_ON_ERROR)));
                $count++;
            } while ((!$mapName || ((string)$mapName === 'retry')) && $count < 3);

            if (!$mapName || $mapName === 'retry') {
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
        $pic = new RRMapFileParser(
            $data,
            function (string $message, string $data): void {
                $this->_debug($message, $data);
            },
            function (string $message, string $data): void {
                $this->LogMessage($message . ': ' . $data, KL_WARNING);
            }
        );
        $this->ReportUnknownMapBlockTypes($pic->getUnknownBlockTypes());
        if (!$pic->isValid()) {
            return false;
        }

        $draw    = new RRMapDraw($pic, function (string $message, string $data): void {
            $this->LogMessage($message . ': ' . $data, KL_WARNING);
        });
        $picture = $draw->getImage($this->ReadPropertyInteger(self::PROPERTY_MAP_PICTURE_SCALE) / 100);

        if ($picture === '') {
            return false;
        }

        $this->CreateMapPictureVariable(self::IDENT_MAP_PICTURE, 'Map', sprintf('Map_%s.png', $this->InstanceID));

        IPS_SetMediaContent(IPS_GetObjectIDByIdent(self::IDENT_MAP_PICTURE, $this->InstanceID), base64_encode($picture));

        return true;
    }

    /**
     * Unbekannte Blocktypen der Karte melden: je Typ und Instanz einmal als Warnung, danach nur im
     * Debug — während einer Reinigung wird die Karte alle 10 s geholt (Forum t/46511/853, Block 34).
     *
     * @param array<int, array{headerLength: int, dataLength: int}> $unknownBlockTypes
     */
    private function ReportUnknownMapBlockTypes(array $unknownBlockTypes): void
    {
        if ($unknownBlockTypes === []) {
            return;
        }
        $reported = json_decode($this->GetBuffer(self::BUFFER_REPORTED_MAP_BLOCK_TYPES) ?: '[]', true) ?: [];
        foreach ($unknownBlockTypes as $type => $lengths) {
            $text = sprintf(
                'The map blocktype %s is not yet supported. (header length: %s, data length: %s)',
                $type,
                $lengths['headerLength'],
                $lengths['dataLength']
            );
            if (in_array($type, $reported, true)) {
                $this->_debug(__FUNCTION__, $text);
                continue;
            }
            $this->LogMessage($text, KL_WARNING);
            $reported[] = $type;
        }
        $this->SetBuffer(self::BUFFER_REPORTED_MAP_BLOCK_TYPES, json_encode($reported, JSON_THROW_ON_ERROR));
    }

    /** Geräte-Token: im Debug auch außerhalb von JSON maskieren (DebugMaskTrait) */
    protected function DebugSecrets(): array
    {
        try {
            return [$this->ReadAttributeString(self::ATTRIBUTE_TOKEN)];
        } catch (Throwable) {
            return []; // vor Create() gibt es das Attribut noch nicht
        }
    }

    /**
     * Probelauf ohne Wirkung: prüft Konfiguration, Token, Xiaomi-Konto, I/O-Instanz, Erreichbarkeit
     * und Modell, Aktualisierung und Karte und liefert das Ergebnis als Text — jede Störung mit dem
     * nächsten Schritt. Setzt keinen Status, keine Variable, kein Attribut und keinen Timer; an den
     * Sauger geht höchstens eine lesende Anfrage (miIO.info).
     */
    public function RunSelfTest(): string
    {
        $ip       = $this->ReadPropertyString(self::PROPERTY_IP);
        $problems = 0;
        $lines    = [
            sprintf($this->Translate('Self-test of the vacuum cleaner at %s (without effect on the instance)'), $ip),
            sprintf($this->Translate('Instance status: %d (%s)'), $this->GetStatus(), $this->StatusText($this->GetStatus()))
        ];
        $good = static function (string $text) use (&$lines): void {
            $lines[] = '✔ ' . $text;
        };
        $bad  = static function (string $text) use (&$lines, &$problems): void {
            $lines[] = '✘ ' . $text;
            $problems++;
        };
        $hint = static function (string $text) use (&$lines): void {
            $lines[] = '– ' . $text;
        };

        $ipValid = $ip !== '' && filter_var(gethostbyname($ip), FILTER_VALIDATE_IP);
        if (!$ipValid) {
            $bad($this->InvalidIpText($ip));
        }

        $user        = $this->ReadPropertyString(self::PROPERTY_XIAOMI_USER);
        $hasAccount  = $user !== '' && $this->ReadPropertyString(self::PROPERTY_XIAOMI_PASSWORD) !== '';
        $token       = $this->ReadAttributeString(self::ATTRIBUTE_TOKEN);
        $tokenLength = strlen($token);
        // 96 = verschlüsselt, wird beim nächsten Übernehmen umgewandelt; geprüft wird mit der entschlüsselten Form
        $probeToken  = $tokenLength === 96 ? self::DecryptToken($token) : $token;
        $tokenValid  = strlen($probeToken) === 32;
        if ($tokenLength === 0) {
            if ($hasAccount) {
                $hint($this->Translate('There is no device token yet; it is fetched from the Xiaomi cloud when the configuration is applied.'));
            } else {
                $bad($this->Translate('There is no device token and no Xiaomi account data. Please enter the Xiaomi account data in the configuration (the token is fetched from the Xiaomi cloud) or set the token with Roborock_SetDeviceToken.'));
            }
        } elseif (!$tokenValid) {
            $bad($this->InvalidTokenText($tokenLength));
        } else {
            $good($this->Translate('A device token is set.'));
            if ($tokenLength === 96) {
                $hint($this->Translate('The device token is stored encrypted; it is converted when the configuration is applied.'));
            }
        }

        if ($hasAccount) {
            $good($this->Translate('Xiaomi account data are set.'));
        } else {
            $hint($this->Translate('No Xiaomi account data: the vacuum cleaner can be controlled with the token, but the map picture needs the Xiaomi account (cloud).'));
        }

        $parentActive = $this->HasActiveParent();
        if (!$parentActive) {
            $bad($this->Translate('The Roborock IO (parent instance) is not active. Please check the I/O instance.'));
        }

        if ($ipValid && $tokenValid && $parentActive) {
            $this->rawResponse = true;
            try {
                $info = $this->RequestData('miIO.info', ['immediate' => true, 'token' => $probeToken]);
            } finally {
                $this->rawResponse = false;
            }
            if (is_array($info) && isset($info['result']['model'])) {
                $good(
                    sprintf(
                        $this->Translate('The vacuum cleaner answers: model %s, firmware %s, WiFi signal %s dBm.'),
                        $info['result']['model'],
                        $info['result']['fw_ver'] ?? '?',
                        $info['result']['ap']['rssi'] ?? '?'
                    )
                );
                if ($this->GetStatus() === self::STATUS_INST_NO_ROBOROCK_FOUND) {
                    $interval = $this->UpdateIntervalSeconds();
                    $hint(
                        $interval > 0
                            ? sprintf($this->Translate('The instance status changes to active at the next update (within %d s).'), $interval)
                            : $this->Translate('The instance status changes to active when the configuration is applied.')
                    );
                }
            } else {
                $bad(sprintf($this->Translate('The vacuum cleaner at %s does not respond. If this persists, check its IP address and WiFi.'), $ip));
            }
        }

        $model = $this->ReadAttributeString(self::ATTRIBUTE_MODEL);
        if ($model !== '' && get_class($this->device) === 'roborock_vacuum') {
            $hint(sprintf($this->Translate('The model %s is not yet well supported (generic device definition); model-specific options may be missing.'), $model));
        }

        $interval = $this->UpdateIntervalSeconds();
        if ($interval > 0) {
            $good(sprintf($this->Translate('The status is updated every %d s.'), $interval));
        } else {
            $hint($this->Translate('Automatic updates are disabled (update interval 0).'));
        }

        if ($this->ReadPropertyBoolean(self::PROPERTY_MAP_PICTURE)) {
            $mediaId = @IPS_GetObjectIDByIdent(self::IDENT_MAP_PICTURE, $this->InstanceID);
            $updated = $mediaId ? (IPS_GetMedia($mediaId)['MediaUpdated'] ?? 0) : 0;
            if ($updated > 0) {
                $good(sprintf($this->Translate('Map picture last fetched %s.'), date('d.m.Y H:i:s', $updated)));
            } else {
                $hint($this->Translate('No map picture fetched yet (Roborock_GetMap).'));
            }
            $unknown = json_decode($this->GetBuffer(self::BUFFER_REPORTED_MAP_BLOCK_TYPES) ?: '[]', true) ?: [];
            if ($unknown !== []) {
                $hint(sprintf($this->Translate('The map contains block types the module does not know yet: %s. The map is drawn without them.'), implode(', ', $unknown)));
            }
        }

        $variableCount = 0;
        $lastUpdate    = 0;
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childId) {
            if (IPS_VariableExists($childId)) {
                $variableCount++;
                $lastUpdate = max($lastUpdate, IPS_GetVariable($childId)['VariableUpdated']);
            }
        }
        $hint(sprintf($this->Translate('Status variables: %d, last update %s.'), $variableCount, $lastUpdate > 0 ? date('d.m.Y H:i:s', $lastUpdate) : '-'));

        $lines[] = $problems === 0 ? $this->Translate('Result: OK') : sprintf($this->Translate('Result: %d problem(s)'), $problems);
        return implode("\n", $lines);
    }

    /** Text zu einem Instanzstatus, wie er im Formular steht */
    private function StatusText(int $status): string
    {
        foreach ($this->FormStatus() as $entry) {
            if ($entry['code'] === $status) {
                return $this->Translate($entry['caption']);
            }
        }
        return match ($status) {
            IS_ACTIVE   => $this->Translate('active'),
            IS_INACTIVE => $this->Translate('inactive'),
            default     => '?'
        };
    }

    /**
     * Rohdaten der zuletzt geladenen Karte (gz, base64-kodiert) — für Fehleranalysen, z. B. bei
     * unbekannten Blocktypen. Leer, solange seit dem letzten Laden des Moduls keine Karte geholt wurde.
     */
    public function GetMapRawData(): string
    {
        return $this->GetBuffer(self::BUFFER_LAST_MAP_RAW);
    }

    /**
     * Karte sofort holen und den 10s-Map-Timer starten (z. B. direkt nach Reinigungsstart),
     * damit die Karte nicht erst beim nächsten regulären Update-Zyklus aktualisiert wird.
     */
    private function StartMapUpdates(): void
    {
        // nach einem gescheiterten Start keine Kartenabfrage alle 10 s bis zum nächsten Update
        if ($this->ActionCommandFailed()) {
            return;
        }
        $this->SetTimerInterval(self::TIMER_UPDATE_MAP, 10000);
        $this->GetMap();
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
     * Zeitzone abfragen; füllt über get_timezone_callback die Variable „Zeitzone“ (Update-Zyklus).
     * Intern: als Skriptfunktion seit 2.2 #59 defekt, entfallen in 2.4 build 100.
     */
    private function GetTimezone(): string|bool
    {
        return $this->RequestData('get_timezone');
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
     * set fan power (Quiet=38, Balanced=60, Turbo=77, Full Speed=90).
     *
     * @param int $fanPowerValue
     *
     * @return void
     * @throws \JsonException
     */
    public function Set_Fan_Power(int $fanPowerValue): void
    {
        $this->_SetValue(self::IDENT_FAN_POWER, $fanPowerValue);
        $this->RequestData('set_custom_mode', [
            'params' => [$fanPowerValue]
        ]);
    }

    /**
     * set the water quantity control during the cleaning process. (Quiet=38, Balanced=60, Turbo=77, Full Speed=90).
     *
     * @param int $waterQuantityValue
     *
     * @return array|bool
     */
    public function Set_Water_Quantity_Control(int $waterQuantityValue): array|bool
    {
        $this->_SetValue(self::IDENT_WATER_QUANTITY, $waterQuantityValue);
        return $this->RequestData('set_water_box_custom_mode', [
            'params' => [$waterQuantityValue]
        ]);
    }

    /**
     * move robot to a direction.
     *
     * @param int $rotation -100..100
     * @param int $velocity  0..100
     * @param int $durationMs      in ms
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function Move_Direction(int $rotation, int $velocity, int $durationMs = 1000): array|bool
    {
        $this->StartRemoteControl();
        $result = $this->RequestData('app_rc_move', [
            'params' => [
                'omega'    => $rotation,
                'velocity' => $velocity,
                'seqnum'   => 'sequence',
                'duration' => $durationMs
            ]
        ]);
        $this->StopRemoteControl();

        return $result;
    }

    /**
     * load map
     *
     * @param int $mapStatusValue
     *
     * @return bool
     */
    public function LoadMap(int $mapStatusValue): bool
    {
        if ($this->RequestData('load_multi_map', ['params' => [$mapStatusValue]])) {
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
     * Roborock Vacuum 2 clean zone with coordinates for the area, use a rectangle with values for the lower left corner and the upper right corner.
     *
     * @param int $lower_left_corner_x
     * @param int $lower_left_corner_y
     * @param int $upper_right_corner_x
     * @param int $upper_right_corner_y
     * @param int $passes
     *
     * @return array|bool
     * @throws \JsonException
     */
    public function ZoneClean(
        int $lower_left_corner_x,
        int $lower_left_corner_y,
        int $upper_right_corner_x,
        int $upper_right_corner_y,
        int $passes
    ): array|bool {
        return $this->RequestData('app_zoned_clean', [
            'params' => [
                [
                    $lower_left_corner_x,
                    $lower_left_corner_y,
                    $upper_right_corner_x,
                    $upper_right_corner_y,
                    $passes
                ]
            ]
        ]);
    }

    public function ZoneCleanRoomname(string $zoneName, int $passes): array|bool
    {
        $zones  = $this->GetZones();
        $zoneid = -1;
        foreach ($zones as $key => $zone) {
            if ($zone['roomname'] === $zoneName) {
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
            $result = $this->ZoneClean($lower_left_corner_x, $lower_left_corner_y, $upper_right_corner_x, $upper_right_corner_y, $passes);
        } else {
            $this->_debug('ZoneClean', 'could not find roomname');
            $result = false;
        }
        return $result;
    }

    public function ZoneCleanRoomnumber(int $zoneNumber, int $passes): array|bool
    {
        $zones      = $this->GetZones();
        $zoneid     = $zoneNumber - 1;
        $zonenumber = $this->GetNumberZones() - 1;
        if ($zoneid <= $zonenumber) {
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
            $result = $this->ZoneClean($lower_left_corner_x, $lower_left_corner_y, $upper_right_corner_x, $upper_right_corner_y, $passes);
        } else {
            $this->_debug('ZoneClean', 'could not find roomnumber');
            $result = false;
        }
        return $result;
    }

    /** Roborock Vacuum 2 clean multiple zone with coordinates for area, use a rectangle with values for the lower left corner and the upper right corner
     * $zonesJson = '[['.$lower_left_corner_x.','. $lower_left_corner_y.','. $upper_right_corner_x.','. $upper_right_corner_y.','. $number.'],['.
     * $lower_left_corner_x1.','. $lower_left_corner_y1.','.    $upper_right_corner_x1.','. $upper_right_corner_y1.','. $number.']]';.
     *
     * @param string $zonesJson
     *
     * @return array|bool
     */
    public function ZoneCleanMulti(string $zonesJson): array|bool
    {
        $zonesJson = json_decode($zonesJson, true, 512, JSON_THROW_ON_ERROR);
        return $this->RequestData('app_zoned_clean', [
            'params' => $zonesJson
        ]);
    }

    /** Roborock Vacuum 2 clean multiple zone with coordinates for area, use a rectangle with values for the lower left corner and the upper right corner
     *
     * @param string $zonesJson
     *
     * @return array|bool
     */
    public function ZoneCleanMultiName(string $zonesJson): array|bool
    {
        $zonesJson     = json_decode($zonesJson, true, 512, JSON_THROW_ON_ERROR);
        $command_zones = [];
        foreach ($zonesJson as $zone) {
            $command_zones[] = [$zone[0][0], $zone[0][1], $zone[0][2], $zone[0][3], $zone[1]];
        }
        return $this->RequestData('app_zoned_clean', [
            'params' => $command_zones
        ]);
    }

    /**
     * Roborock Vacuum 2 go-to coordinates.
     *
     * @param int $xMillimeter
     * @param int $yMillimeter
     *
     * @return void
     * @throws \JsonException
     */
    public function GotoTarget(int $xMillimeter, int $yMillimeter): void
    {
        $this->RequestData('app_goto_target', [
            'params' => [
                $xMillimeter,
                $yMillimeter
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
     * @param bool $startCleaning
     */
    public function Toggle_State(bool $startCleaning): void
    {
        if ($startCleaning) {
            $this->Start();
        } else {
            $this->Stop();
        }
    }

    /**
     * enable / disable dnd mode.
     *
     * @param bool $enable
     */
    public function Set_DND(bool $enable): void
    {
        $this->_SetValue('dnd_mode', $enable);

        if ($enable) {
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
     * @param string $startTimeHHMM
     */
    public function Set_DND_Start(string $startTimeHHMM): void
    {
        $unixtime = strtotime($startTimeHHMM);
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
     * @param string $endTimeHHMM
     */
    public function Set_DND_End(string $endTimeHHMM): void
    {
        $unixtime = strtotime($endTimeHHMM);
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
     * @param int $segmentId
     *
     * @return void
     * @throws \JsonException
     */
    public function Start_Segment_Clean(int $segmentId): void
    {
        $this->RequestData('app_segment_clean', [
            'params' => [
                $segmentId
            ]
        ]);
    }

    /**
     * segment clean Ex
     *
     * @param string $segmentIdsJson json encoded array of segmentids
     *
     * @return void
     * @throws \JsonException
     */
    public function Start_Segment_Clean_Ex(string $segmentIdsJson): void
    {
        $this->RequestData('app_segment_clean', [
            'params' => json_decode($segmentIdsJson, true, 512, JSON_THROW_ON_ERROR)
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
     * Prüft Ident und Wert, sendet Gerätebefehle sofort und meldet jeden Fehlschlag per trigger_error —
     * das Einzige, was beim Aufrufer (Skript, Visualisierung, KI über MCP) ankommt (MCP-Regel 8).
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        $isDeviceAction = in_array($Ident, self::ACTION_IDENTS_DEVICE, true);
        if ($isDeviceAction || in_array($Ident, self::ACTION_IDENTS_VARIABLE, true)) {
            $Value = $this->ValidateActionValue($Ident, $Value);
            if ($Value === null) {
                return; // Grund wurde per trigger_error gemeldet
            }
        }

        if (!$isDeviceAction) {
            $this->ExecuteAction($Ident, $Value);
            return;
        }

        // die Aktionen setzen ihre Variable vor dem Befehl; scheitert er, zählt wieder der bestätigte Wert
        $confirmedValue = $this->GetValue($Ident);

        $this->sendImmediately = true;
        $this->deviceRequests  = [];
        try {
            $this->ExecuteAction($Ident, $Value);
        } finally {
            $this->sendImmediately = false;
        }

        // maßgeblich ist der erste Befehl der Aktion; danach folgen nur Statusabfragen
        $first = $this->deviceRequests[0] ?? null;
        if ($first === null || $first['result'] === 'ok') {
            return;
        }
        $this->SetValue($Ident, $confirmedValue);

        if ($first['result'] === 'unclear') {
            trigger_error(
                sprintf(
                    $this->Translate('"%s": the vacuum cleaner at %s gave no matching answer (%s), so it is unknown whether the command was executed. Check its state before repeating.'),
                    $Ident,
                    $this->ReadPropertyString(self::PROPERTY_IP),
                    $first['error']
                ),
                E_USER_WARNING
            );
            return;
        }
        if ($first['result'] === 'silent') {
            trigger_error(
                sprintf(
                    $this->Translate('"%s" was not executed: the vacuum cleaner at %s does not respond. Try again later.'),
                    $Ident,
                    $this->ReadPropertyString(self::PROPERTY_IP)
                ),
                E_USER_WARNING
            );
            return;
        }
        trigger_error(
            sprintf(
                $this->Translate('"%s" was rejected by the vacuum cleaner (%s). Check the value and the state of the vacuum cleaner.'),
                $Ident,
                $first['error']
            ),
            E_USER_WARNING
        );
    }

    /**
     * Prüft einen Wert für eine Statusvariable gegen deren Darstellung (Optionen bzw. Minimum/Maximum).
     * Liefert den Wert im Typ der Variable oder null, nachdem der Grund per trigger_error gemeldet wurde.
     */
    private function ValidateActionValue(string $ident, mixed $value): int|bool|null
    {
        $variableId = @$this->GetIDForIdent($ident);
        if (!$variableId) {
            trigger_error(
                sprintf($this->Translate('"%s" is not available: the status variable is not enabled in the configuration of this instance.'), $ident),
                E_USER_WARNING
            );
            return null;
        }
        $variable = IPS_GetVariable($variableId);
        $shown    = is_scalar($value) ? var_export($value, true) : (json_encode($value) ?: '?');

        if ($variable['VariableType'] === VARIABLETYPE_BOOLEAN) {
            if (is_bool($value) || $value === 0 || $value === 1) {
                return (bool)$value;
            }
            trigger_error(
                sprintf($this->Translate('Value %s for "%s" is not allowed (allowed: true, false). Do not repeat with this value.'), $shown, $ident),
                E_USER_WARNING
            );
            return null;
        }

        if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', $value)) && !(is_float($value) && floor($value) === $value)) {
            trigger_error(
                sprintf($this->Translate('Value %s for "%s" is not a whole number. Do not repeat with this value.'), $shown, $ident),
                E_USER_WARNING
            );
            return null;
        }
        $value        = (int)$value;
        $presentation = $variable['VariablePresentation'] ?? [];

        if (isset($presentation['OPTIONS'])) {
            $options = json_decode((string)$presentation['OPTIONS'], true) ?: [];
            $allowed = [];
            foreach ($options as $option) {
                if ((int)$option['Value'] === $value) {
                    return $value;
                }
                $allowed[] = $option['Value'] . ' = ' . $option['Caption'];
            }
            trigger_error(
                sprintf($this->Translate('Value %s for "%s" is not allowed (allowed: %s). Do not repeat with this value.'), $shown, $ident, implode(', ', $allowed)),
                E_USER_WARNING
            );
            return null;
        }

        if (isset($presentation['MIN'], $presentation['MAX']) && ($value < $presentation['MIN'] || $value > $presentation['MAX'])) {
            trigger_error(
                sprintf(
                    $this->Translate('Value %s for "%s" is out of range (allowed: %s to %s). Do not repeat with this value.'),
                    $shown,
                    $ident,
                    $presentation['MIN'],
                    $presentation['MAX']
                ),
                E_USER_WARNING
            );
            return null;
        }
        return $value;
    }

    private function ExecuteAction(string $Ident, mixed $Value): void
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
                $Texts = $this->SafeJsonDecode(
                    $this->ReadAttributeString(self::ATTRIBUTE_ROOM_NAMES),
                    __FUNCTION__ . ' room_names'
                ) ?? [];

                $Texts[$RoomValues[self::FF_COL_ROOMTEXTREFERENCE]] = $RoomValues[self::FF_COL_ROOMNAME]; //update Name of Room

                $this->WriteAttributeString(self::ATTRIBUTE_ROOM_NAMES, json_encode($Texts, JSON_THROW_ON_ERROR));

                //aktualisieren des Ignore Flags
                $savedMapsList = $this->SafeJsonDecode(
                    $this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST),
                    __FUNCTION__ . ' maps_list'
                ) ?? [];
                //$map = $savedMapsList[$]
                $savedMapsList[$RoomValues[self::FF_COL_PARENT_MAP_ID]]['rooms'][$RoomValues[self::FF_COL_ROOMID]][self::FF_COL_IGNORE_ROOM] =
                    $RoomValues[self::FF_COL_IGNORE_ROOM];
                $this->WriteAttributeString(self::ATTRIBUTE_MAPS_LIST, json_encode($savedMapsList, JSON_THROW_ON_ERROR));

                $this->WriteRoomSelectionPresentation();
                $this->UpdateRoomsSelected();

                break;
            default:
                trigger_error(
                    sprintf(
                        $this->Translate('"%s" is not an action of this instance. Switchable status variables: %s.'),
                        $Ident,
                        implode(', ', array_merge(self::ACTION_IDENTS_DEVICE, self::ACTION_IDENTS_VARIABLE))
                    ),
                    E_USER_WARNING
                );
        }
    }

    private function ProcessSelectedRoom(int $roomId): void
    {
        $roomSelection = $this->SafeJsonDecode(
            $this->ReadAttributeString(self::ATTRIBUTE_ROOM_SELECTION),
            __FUNCTION__ . ' room_selection'
        ) ?? [];

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
        if (@$this->GetIDForIdent(self::IDENT_ROOMS_SELECTED) === false || @$this->GetIDForIdent(self::IDENT_ROOMSELECTION) === false) {
            return;
        }

        $roomSelection = $this->SafeJsonDecode(
            $this->ReadAttributeString(self::ATTRIBUTE_ROOM_SELECTION),
            __FUNCTION__ . ' room_selection'
        ) ?? [];

        $roomOptions = $this->GetRoomSelectionOptions();
        $captionByValue = [];
        foreach ($roomOptions as $option) {
            $captionByValue[(int)$option['Value']] = (string)$option['Caption'];
        }
        $roomNames = [];
        if (count($roomSelection) === 0) { // all
            $roomNames[] = $captionByValue[0] ?? sprintf('- %s -', $this->Translate('None'));
        } else {
            foreach ($roomSelection as $roomId => $room) {
                $roomNames[] = $captionByValue[(int)$roomId] ?? ((string)$roomId);
            }
        }
        $this->SetValue(self::IDENT_ROOMS_SELECTED, implode(', ', $roomNames));
    }

    private function WriteRoomSelectionPresentation(): void
    {
        if (@$this->GetIDForIdent(self::IDENT_ROOMSELECTION) === false) {
            return;
        }
        $this->RegisterVariableInteger(
            self::IDENT_ROOMSELECTION,
            $this->Translate('Roomselection'),
            $this->GetRoomSelectionPresentation(),
            IPS_GetObject($this->GetIDForIdent(self::IDENT_ROOMSELECTION))['ObjectPosition']
        );
    }

    private function GetMapStatusPresentation(): array
    {
        return VariablePresentations::enumeration($this->GetMapStatusOptions());
    }

    private function GetMapStatusOptions(): array
    {
        $mapsList = $this->SafeJsonDecode(
            $this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST),
            __FUNCTION__ . ' maps_list'
        ) ?? [];

        $options = [];
        foreach ($mapsList as $mapFlag => $map) {
            $options[] = [
                'Value'   => (int)$mapFlag,
                // auch beim Lesen reinigen: Namen, die eine ältere Version gespeichert hat, sind ungeprüft
                'Caption' => isset($map['MapName'])
                    ? self::CleanForeignText((string)$map['MapName'], self::MAX_LENGTH_FOREIGN_NAME)
                    : $this->Translate('Map') . ((int)$mapFlag + 1)
            ];
        }

        if (count($options) === 0) {
            $options[] = ['Value' => 0, 'Caption' => sprintf('- %s -', $this->Translate('None'))];
        }

        return $options;
    }

    private function GetRoomSelectionPresentation(): array
    {
        return VariablePresentations::enumeration($this->GetRoomSelectionOptions());
    }

    private function GetRoomSelectionOptions(): array
    {
        $roomNames = $this->SafeJsonDecode(
            $this->ReadAttributeString(self::ATTRIBUTE_ROOM_NAMES),
            __FUNCTION__ . ' room_names'
        ) ?? [];
        $mapsList = $this->SafeJsonDecode(
            $this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST),
            __FUNCTION__ . ' maps_list'
        ) ?? [];

        $options = [['Value' => 0, 'Caption' => sprintf('- %s -', $this->Translate('None'))]];
        if (count($mapsList) === 0) {
            return $options;
        }

        $mapStatus = 0;
        if (@$this->GetIDForIdent(self::IDENT_MAP_STATUS) !== false) {
            $mapStatus = (int)$this->GetValue(self::IDENT_MAP_STATUS);
        }
        if (!isset($mapsList[$mapStatus]['rooms']) || !is_array($mapsList[$mapStatus]['rooms'])) {
            return $options;
        }

        foreach ($mapsList[$mapStatus]['rooms'] as $roomID => $room) {
            if (!isset($room['IgnoreRoom']) || !$room['IgnoreRoom']) {
                $options[] = [
                    'Value'   => (int)$roomID,
                    'Caption' => (string)($roomNames[$room['referenceID']] ?? ($this->Translate('Room') . ' ' . $room['roomID']))
                ];
            }
        }

        return $options;
    }

    /**
     * checks if a token is available.
     *
     * @return bool
     */
    private function CheckUserAndPassword(): bool
    {
        // if the token is valid, everything is ok
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

        // return, when the last notification is the same as the current notification or id is 0
        if ((($last_notification === (string)$id) && !$force_send) || ($id === 0)) {
            return;
        }

        // check notification instance (webfront)
        // get notification settings
        if (($instance_id = $this->ReadPropertyInteger('notification_instance'))
            && ($notifications = $this->SafeJsonDecode(
                $this->ReadPropertyString('notifications'),
                __FUNCTION__ . ' notifications'
            ))) {
            // loop notifications and search for the current state
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

        // merge with current settings (in Create() ist die Property noch nicht registriert)
        try {
            $current_notifications = @$this->ReadPropertyString('notifications');
        } catch (Throwable) {
            $current_notifications = '';
        }
        if ($current_notifications) {
            $current_notifications = $this->SafeJsonDecode($current_notifications, __FUNCTION__ . ' notifications') ?? [];
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

    private function SafeJsonDecode(string $json, string $context): ?array
    {
        if ($json === '') {
            return null;
        }
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $ex) {
            $this->_debug(__FUNCTION__, $context . ': ' . $ex->getMessage());
            return null;
        }
        return is_array($decoded) ? $decoded : null;
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
            'actions'  => array_merge($this->FormActions(), $this->FormHints()),
            'status'   => $this->FormStatus()
        ], JSON_THROW_ON_ERROR);

        $this->_debug('Form', $form);
        // return current form
        return $form;
    }

    /**
     * Unsichtbare Hinweise für Skripte und KI-Assistenten (MCP-Regel 6): je Aufgabe die passenden
     * Skriptfunktionen mit Wirkung, Parametern und Rückgabe. In der Konsole erscheinen sie nicht
     * (Vorgabe Burkhard); eine KI liest sie über IPS_GetConfigurationForm. Inhalte an der Anlage
     * geprüft (nuc, 04.10.2026) — beim Ändern einer Skriptfunktion hier mitziehen
     * (tests/check-form-hints.php prüft, dass jede genannt ist).
     */
    private function FormHints(): array
    {
        return [
            [
                'type'    => 'Label',
                'visible' => false,
                'caption' => 'For scripts and AI assistants: switch the vacuum cleaner via its status variables with RequestAction(VariableID, value) or IPS_RequestAction(InstanceID, ident, value). The value is checked against the options or the range of the variable\'s presentation; an invalid value is rejected with the allowed values and nothing is sent, a vacuum cleaner that does not respond is reported as an error. Idents (command always, the others only if enabled in the configuration): command (0 = Start, 1 = Pause, 2 = Stop, 3 = Spot, 4 = Charge, 5 = Locate), fan_power, water_quantity, volume (0 to 100), map_status (active map), dnd_mode (true/false), dnd_starttime and dnd_endtime (Unix time, only hour and minute count). Cleaning selected rooms: write the rooms one after another to roomselection (0 clears the selection), set cleaning_cycles (1 to 3), then write 1 to start_cleaning. Roborock_Set_Fan_Power(int $InstanceID, int $fanPowerValue): void and Roborock_Set_Water_Quantity_Control(int $InstanceID, int $waterQuantityValue): array|bool set the same values as fan_power and water_quantity, but without any check - prefer RequestAction.'
            ],
            [
                'type'    => 'Label',
                'visible' => false,
                'caption' => 'For scripts and AI assistants - diagnosis and reading: Roborock_RunSelfTest(int $InstanceID): string checks configuration, token, Xiaomi account, I/O instance, reachability and model, updates and map without any effect and returns a text (✔ OK, ✘ problem with the next step, – note); use it first when something does not work. The following read the vacuum cleaner (some seconds each) and also update the matching status variables: Roborock_Get_State(int $InstanceID): array (battery %, clean_area m², clean_time s, error_code, fan_power, map_status, state, water box); Roborock_GetDeviceInfo(int $InstanceID): array (model, firmware_version, hardware_version, ip, mac, rssi dBm, ssid); Roborock_Get_Serial_Number(int $InstanceID): string; Roborock_Get_Consumables(int $InstanceID): array (remaining life in % per part); Roborock_GetCleanSummary(int $InstanceID): array (area_cleaned m², cleanups, total_cleaning_time s, clean_records = start times as Unix time); Roborock_Get_DND_Mode(int $InstanceID): array (start, end as HH:MM). Roborock_Update(int $InstanceID): void reads all enabled values now, as the update timer does.'
            ],
            [
                'type'    => 'Label',
                'visible' => false,
                'caption' => 'For scripts and AI assistants - cleaning: Roborock_Start, Roborock_Stop, Roborock_Pause, Roborock_Charge (back to the dock), Roborock_Locate (plays a sound) and Roborock_CleanSpot (cleans around its position), each (int $InstanceID): void, do the same as the values of the status variable command, but report no error - prefer RequestAction on command. Roborock_Toggle_State(int $InstanceID, bool $startCleaning): void - true = Start, false = Stop. Roborock_StartCleaning(int $InstanceID): void starts cleaning the rooms selected in roomselection with cleaning_cycles. Roborock_Start_Segment_Clean(int $InstanceID, int $segmentId): void cleans one room; Roborock_Start_Segment_Clean_Ex(int $InstanceID, string $segmentIdsJson): void cleans several, JSON like [16,17] or [{"segments":[16,17],"repeat":2}]. Roborock_Get_Room_Mapping(int $InstanceID): array returns the rooms of the active map as pairs [segment ID, room ID of the Xiaomi cloud]; the vacuum cleaner provides no room names - they are assigned in the configuration and appear as options of roomselection. Roborock_LoadMap(int $InstanceID, int $mapStatusValue): bool loads a saved map (floor); the values are the options of map_status.'
            ],
            [
                'type'    => 'Label',
                'visible' => false,
                'caption' => 'For scripts and AI assistants - zones and position (map coordinates, 1 unit is about 1 mm; the charging station is usually near 25500/25500): Roborock_ZoneClean(int $InstanceID, int $lower_left_corner_x, int $lower_left_corner_y, int $upper_right_corner_x, int $upper_right_corner_y, int $passes): array|bool cleans a rectangle. Roborock_ZoneCleanMulti(int $InstanceID, string $zonesJson) takes JSON [[x1,y1,x2,y2,passes], ...]; Roborock_ZoneCleanMultiName(int $InstanceID, string $zonesJson) takes JSON [[[x1,y1,x2,y2],passes], ...] - despite its name it takes coordinates, not names. Roborock_ZoneCleanRoomname(int $InstanceID, string $zoneName, int $passes) and Roborock_ZoneCleanRoomnumber(int $InstanceID, int $zoneNumber, int $passes) clean a zone of the configuration property zonecoordinates (JSON list of {roomname, lx, ly, ux, uy}, numbers count from 1) and return false if it does not exist; Roborock_GetZones(int $InstanceID): array returns that list, Roborock_GetZoneCoordinatesByName(int $InstanceID, string $zoneName) and Roborock_GetZoneCoordinatesByNumber(int $InstanceID, int $zoneNumber): array|false return [lx, ly, ux, uy]. Roborock_GotoTarget(int $InstanceID, int $xMillimeter, int $yMillimeter): void drives to a point. Roborock_Move_Direction(int $InstanceID, int $rotation, int $velocity, int $durationMs): array|bool moves the vacuum cleaner by remote control, the values are passed on unchanged - only with someone watching.'
            ],
            [
                'type'    => 'Label',
                'visible' => false,
                'caption' => 'For scripts and AI assistants - maintenance and do not disturb: after replacing or cleaning a part, reset its counter with Roborock_Reset_Filter, Roborock_Reset_Mainbrush, Roborock_Reset_Sidebrush or Roborock_Reset_Sensors, each (int $InstanceID): array|bool; Roborock_Reset_Consumable(int $InstanceID, string $consumableKey) needs the model-specific key (e.g. filter or filter_work_time) - prefer the four functions above. Roborock_Set_DND(int $InstanceID, bool $enable): void switches do not disturb with the times of dnd_starttime and dnd_endtime; Roborock_SetDNDTimer(int $InstanceID, int $starthour, int $startminutes, int $endhour, int $endminutes): array|bool sets the times and switches it on; Roborock_DisableDND(int $InstanceID): array|bool switches it off; Roborock_Set_DND_Start(int $InstanceID, string $startTimeHHMM) and Roborock_Set_DND_End(int $InstanceID, string $endTimeHHMM): void set one time, e.g. "23:00".'
            ],
            [
                'type'    => 'Label',
                'visible' => false,
                'caption' => 'For scripts and AI assistants - map (needs the Xiaomi account): Roborock_GetMap(int $InstanceID): bool fetches the current map from the Xiaomi cloud (some seconds) and updates the media object Map; Roborock_GetMapRawData(int $InstanceID): string returns the last fetched map as base64-encoded gz file (empty if none was fetched since the module was loaded), for bug reports. Roborock_SetJoystickHtml(int $InstanceID): void rewrites the HTML of the remote control variable.'
            ],
            [
                'type'    => 'Label',
                'visible' => false,
                'caption' => 'For scripts and AI assistants - setup: the device token is fetched from the Xiaomi cloud with the account data of the configuration when the configuration is applied, or with Roborock_GetTokenFromXiaomi(int $InstanceID): bool|int (true = token found, false = failed, 16 = Xiaomi asks for a two-factor verification). Then Roborock_SendVerificationCode(int $InstanceID): string requests the code and Roborock_SubmitVerificationCode(int $InstanceID, string $verificationCode): string submits it - normally done in the popup of the configuration form; both return a message. Roborock_SetDeviceToken(int $InstanceID, string $deviceToken): void sets a token (32 hex characters) directly and checks the connection. Roborock_RequestRawData(int $InstanceID, string $method, array $options): array|bool sends any miIO command unchecked and returns the raw answer - experts only, it can change settings and the answer can contain the device token.'
            ]
        ];
    }

    /**
     * return form configurations on the configuration step.
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
                        'minimum' => self::MIN_VALUE_UPDATE_INTERVAL
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
            $zones = $this->SafeJsonDecode($zones_json, __FUNCTION__ . ' zonecoordinates') ?? [];
        }
        return $zones;
    }

    public function GetZoneCoordinatesByNumber(int $zoneNumber): false|array
    {
        $zones      = $this->GetZones();
        $zoneid     = $zoneNumber - 1;
        $zonenumber = $this->GetNumberZones() - 1;
        if ($zoneid <= $zonenumber) {
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

    public function GetZoneCoordinatesByName(string $zoneName): false|array
    {
        $zones  = $this->GetZones();
        $zoneid = -1;
        foreach ($zones as $key => $zone) {
            if ($zone['roomname'] === $zoneName) {
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
                    ],
                    // 'download': enthält die onClick-Ausgabe eine Data-URL, lädt die Konsole sie als Datei herunter.
                    // Das echo muss im onClick stehen — ein echo im Modul käme dort als "Warning: …" an.
                    [
                        'type'     => 'Button',
                        'caption'  => 'Download Map (Raw Data)',
                        'download' => sprintf('Roborock_Map_%s.gz', $this->InstanceID),
                        'onClick'  => '
                            $raw = Roborock_GetMapRawData($id);
                            echo $raw !== \'\' ? \'data:application/gzip;base64,\' . $raw : (new IPSModule($id))->Translate(\'No map available yet. Please get the map first.\');
                        '
                    ],
                    [
                        'type'     => 'Button',
                        'caption'  => 'Download Map (Picture)',
                        'download' => sprintf('Roborock_Map_%s.png', $this->InstanceID),
                        'onClick'  => '
                            $mediaId = @IPS_GetObjectIDByIdent(\'' . self::IDENT_MAP_PICTURE . '\', $id);
                            $picture = $mediaId ? IPS_GetMediaContent($mediaId) : \'\';
                            echo $picture !== \'\' ? \'data:image/png;base64,\' . $picture : (new IPSModule($id))->Translate(\'No map available yet. Please get the map first.\');
                        '
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
                'caption' => 'Token is not valid. Set a valid token with Roborock_SetDeviceToken.'
            ],
            [
                'code'    => self::STATUS_INST_NO_ROBOROCK_FOUND,
                'icon'    => 'inactive',
                'caption' => 'The vacuum cleaner does not respond. It is checked again at every update.'
            ]
        ];
    }

    private function GetMapAndRoomListFormValues(): array
    {
        $maps_list = $this->SafeJsonDecode(
            $this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST),
            __FUNCTION__ . ' maps_list'
        ) ?? [];
        $RoomNames = $this->SafeJsonDecode(
            $this->ReadAttributeString(self::ATTRIBUTE_ROOM_NAMES),
            __FUNCTION__ . ' room_names'
        ) ?? [];
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
     * updates remote variable with joystick HTML.
     */
    public function SetJoystickHtml(): void
    {
        $joystick = file_get_contents(dirname(__FILE__, 2) . '/libs/joystick.html');
        $joystick = str_replace('[instance_id]', (string)$this->InstanceID, $joystick);
        $this->SetValue(self::IDENT_REMOTE_CONTROL, $joystick);
    }

    /**
     * check for variable and set a value.
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
     * add leading zeros to the number.
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
        $this->SendDebug($notification ?? '', $message ?? '', 0);
    }

    /**
     * return the incremented position.
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
        if (isset($data['table']['body'])) {
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

    // Xiaomi App Login Test
    public function GetTokenFromXiaomi(): bool|int
    {
        // read properties
        $user     = $this->ReadPropertyString(self::PROPERTY_XIAOMI_USER);
        $password = $this->ReadPropertyString(self::PROPERTY_XIAOMI_PASSWORD);

        $clientId = $this->ReadAttributeString(self::ATTRIBUTE_CLIENTID);
        $agentId  = $this->ReadAttributeString(self::ATTRIBUTE_AGENTID);
        $maskedPassword = str_repeat('*', max(8, strlen($password)));
        $this->SendDebug(
            __FUNCTION__,
            'user/password/agentId/clientId: ' . json_encode([$user, $maskedPassword, $agentId, $clientId], JSON_THROW_ON_ERROR),
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
        if (is_array($loginAccountData) && (($loginAccountData['securityStatus'] ?? 0) === 16)) { // 2FA
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
            $loginAccountDebug = is_array($loginAccountData)
                ? [
                    'code'           => $loginAccountData['code'] ?? null,
                    'desc'           => $loginAccountData['desc'] ?? null,
                    'description'    => $loginAccountData['description'] ?? null,
                    'securityStatus' => $loginAccountData['securityStatus'] ?? null,
                    'result'         => $loginAccountData['result'] ?? null,
                    'meta'           => $loginAccountData['__meta'] ?? null
                ]
                : ['type' => gettype($loginAccountData)];
            $this->SendDebug(__FUNCTION__ . ': ERROR', 'Login account response: ' . json_encode($loginAccountDebug, JSON_THROW_ON_ERROR), 0);
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

        // -- token of the device by getDeviceStatus --
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
                $this->SendDebug(__FUNCTION__, sprintf('Token \'%s\' found', self::MaskValue($device['token'])), 0);
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
        if (($responsecode !== self::HTTP_OK)) {
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
        if (($responsecode !== self::HTTP_OK)) {
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
        if (($responsecode !== self::HTTP_OK)) {
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
     * @param string $verificationCode
     *
     * @return string
     */
    public function SubmitVerificationCode(string $verificationCode): string
    {
        $this->SendDebug(__FUNCTION__, $verificationCode, 0);
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
            'ticket' => $verificationCode,
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
        if (($responsecode !== self::HTTP_OK)) {
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
        if (($responsecode !== self::HTTP_OK)) {
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
                // After successful 2FA, refresh cloud session and token via the regular login flow.
                $this->GetTokenFromXiaomi();
                $this->ValidateConfiguration();
                $this->SetUpdateInterval();
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
        if (($responsecode !== self::HTTP_OK)) {
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
        $curlError    = curl_error($ch);
        $responsecode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        curl_close($ch);
        if (($responsecode !== self::HTTP_OK)) {
            trigger_error(sprintf('%s: responsecode: %s, URL: %s, effective URL: %s', __FUNCTION__, (int)$responsecode, $url, $effectiveURL));
            return false;
        }
        $parsed = $this->parseJson($result);
        if (is_array($parsed)) {
            $parsed['__meta'] = [
                'responseCode' => $responsecode,
                'effectiveURL' => $effectiveURL,
                'curlError'    => $curlError
            ];
        }
        return $parsed;
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
        if (($responsecode !== self::HTTP_OK)) {
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

    private function getApiIO(string $path, array $values, bool $allowRelogin = true): array
    {
        $loginLocationData = $this->SafeJsonDecode(
            $this->ReadAttributeString(self::ATTRIBUTE_LOGIN_LOCATION_DATA),
            __FUNCTION__ . ' login_location_data'
        ) ?? [];
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

        $loginAccountData = $this->SafeJsonDecode(
            $this->ReadAttributeString(self::ATTRIBUTE_LOGIN_ACCOUNT_DATA),
            __FUNCTION__ . ' login_account_data'
        ) ?? [];
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
        if (($responsecode !== self::HTTP_OK)) {
            // Abgelaufener Cloud-ServiceToken: Xiaomi antwortet mit HTTP 426 bzw. message "SERVICETOKEN_EXPIRED".
            // In diesem Fall einmalig automatisch neu anmelden und die Anfrage wiederholen, statt nur eine Notice zu erzeugen.
            $tokenExpired = ($responsecode === self::HTTP_UPGRADE_REQUIRED)
                || (is_string($result) && str_contains($result, self::MI_ERROR_TOKEN_EXPIRED));
            if ($tokenExpired && $allowRelogin) {
                $this->_debug(__FUNCTION__, sprintf('ServiceToken expired (responsecode: %s), trying automatic re-login', (int)$responsecode));
                $reloginResult = $this->GetTokenFromXiaomi();
                if ($reloginResult === true) {
                    $this->_debug(__FUNCTION__, 'Re-login successful, retrying request');
                    return $this->getApiIO($path, $values, false);
                }
                if ($reloginResult === 16) {
                    $this->LogMessage(
                        $this->Translate('ServiceToken expired and re-login requires additional verification. Please verify the account again in the module configuration.'),
                        KL_WARNING
                    );
                    return [];
                }
                trigger_error(sprintf('%s: ServiceToken expired and automatic re-login failed', __FUNCTION__));
                return [];
            }
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
        // allowRelogin = false: getDeviceStatus() wird selbst innerhalb von GetTokenFromXiaomi() aufgerufen -> Rekursion vermeiden.
        $device_list = $this->getApiIO('/home/device_list', ['getVirtualModel' => false, 'getHuamiDevices' => 0], false);
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

        $data = '';
        foreach ($params as $key => $value) {
            if ($key === 'key') {
                $data = $value . '=';
            }
            if ($key === 'value') {
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
        $MapsList = $this->SafeJsonDecode(
            $this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST),
            __FUNCTION__ . ' maps_list'
        ) ?? [];
        $this->_debug(__FUNCTION__, sprintf('MapsList (old): %s', $this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST)));
        $this->_debug(__FUNCTION__, sprintf('rooms: %s', json_encode($rooms, JSON_THROW_ON_ERROR)));

        //unbekannte Karte (z. B. mapFlag 63 = keine Karte aktiv) ohne gemeldete Räume — nichts zu aktualisieren
        if ($rooms === [] && !isset($MapsList[$mapFlag])) {
            return;
        }
        if (!is_array($MapsList[$mapFlag]['rooms'] ?? null)) {
            $MapsList[$mapFlag]['rooms'] = [];
        }

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

        $this->WriteRoomSelectionPresentation();
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
        $this->SendDebug(__FUNCTION__, json_encode($data, JSON_THROW_ON_ERROR), 0);
        if (isset($data['result'][0]) && $data['result'][0] === 'ok') {
            $map_status = (int) $data['params'][0];
            $this->_SetValue(self::IDENT_MAP_STATUS, $map_status);

            if ($this->ReadPropertyBoolean(self::PROPERTY_CLEANING_ORDER)) {
                $this->RequestData('get_room_mapping', ['immediate' => true]);
                $this->WriteRoomSelectionPresentation();
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
            $this->_SetValue('rssi', (string)$rssi); // Variable ist String, miIO liefert eine Zahl

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

        if (isset($result['map_status']) && @$this->GetIDForIdent(self::IDENT_MAP_STATUS)) {
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
                $this->_SetValue(
                    self::IDENT_CONSUMABLES_TEXT,
                    implode(', ', array_map(static fn(array $row): string => $row[0] . ' ' . rtrim($row[1], '%') . ' %', $consumables))
                );
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

        $maps_list = [];

        $result = $data['result'][0];
        foreach ($result['map_info'] as $mapInfo) {
            $index = $mapInfo['mapFlag'];
            $mapName = self::CleanForeignText((string)($mapInfo['name'] ?? ''), self::MAX_LENGTH_FOREIGN_NAME);
            if ($mapName !== '') {
                $maps_list[$index] = [
                    'mapFlag' => $index,
                    'MapName' => $mapName
                ];
            } else {
                $maps_list[$index] = [
                    'mapFlag' => $index,
                    'MapName' => $this->Translate('Map') . ($index + 1)
                ];
            }
        }
        $this->UpdateAttributeMapsListWithMaps($maps_list);
        if (@$this->GetIDForIdent(self::IDENT_MAP_STATUS) !== false) {
            $this->RegisterVariableInteger(
                self::IDENT_MAP_STATUS,
                $this->Translate('Active Map'),
                $this->GetMapStatusPresentation(),
                IPS_GetObject($this->GetIDForIdent(self::IDENT_MAP_STATUS))['ObjectPosition']
            );
        }
        $this->WriteRoomSelectionPresentation();
    }

    private function UpdateAttributeMapsListWithMaps(array $maps): void
    {
        $savedList = $this->SafeJsonDecode(
            $this->ReadAttributeString(self::ATTRIBUTE_MAPS_LIST),
            __FUNCTION__ . ' maps_list'
        ) ?? [];

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
                        $cleaning_records = $this->SafeJsonDecode(
                            $cleaning_records,
                            __FUNCTION__ . ' cleaning_records'
                        ) ?? [];
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
                $this->WriteCleaningRecordVariables($cleaning_records);
            }

            return $data;
        }

        return [];
    }

    /** Tabelle (HTML) und Klartext der letzten Reinigungen schreiben */
    private function WriteCleaningRecordVariables(array $cleaning_records): void
    {
        // build HTML
        $body_data  = [];
        $text_lines = [];
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
            $text_lines[] = sprintf(
                '%s %s %s:%s - %s:%s, %s, %s m², %s%s',
                $this->Translate($clean_day),
                $clean_date,
                $start_hour,
                $start_minutes,
                $end_hour,
                $end_minutes,
                $cleaning_duration,
                $area,
                $completed ? $this->Translate('completed') : $this->Translate('not completed'),
                $errors ? ', ' . $this->Translate('with error') : ''
            );
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
        $this->_SetValue(self::IDENT_CLEANING_RECORDS_TEXT, implode("\n", $text_lines));
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
        $this->_SetValue('state', StateCode::REMOTE_CONTROL->value);
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
        $this->_SetValue('state', StateCode::WAITING->value);
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
            $content = $this->SafeJsonDecode(base64_decode($mediaContent) ?: '', __FUNCTION__ . ' media_content');
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

    public static array $TypeToPath = [
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

