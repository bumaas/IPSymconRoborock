<?php

declare(strict_types=1);

require_once __DIR__ . '/roborock_vacuum.php';
require_once __DIR__ . '/RRMapFileParser.php';
require_once __DIR__ . '/RRMapDraw.php';

/**
 * Class Roborock
 * Xiaomi Mi Vacuum Cleaner.
 *
 * a very useful API documentation: https://github.com/marcelrv/XiaomiRobotVacuumProtocol
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
class Roborock extends IPSModule
{

    private const STATUS_INST_CONFIGURATION_INCOMPLETE = 201;
    private const STATUS_INST_IP_ADDRESS_IS_INVALID    = 203;
    private const STATUS_INST_TOKEN_IS_INVALID         = 205;
    private const STATUS_INST_NO_ROBOROCK_FOUND        = 206;

    private const ATTRIBUTE_TOKEN                   = 'token';
    private const ATTRIBUTE_LOGIN_LOCATION_DATA     = 'loginLocationData';
    private const ATTRIBUTE_LOGIN_ACCOUNT_DATA      = 'loginAccountData';
    private const ATTRIBUTE_LAST_NOTIFICATION_STATE = 'last_notification_state';
    private const ATTRIBUTE_LAST_NOTIFICATION_ERROR = 'last_notification_error';
    private const ATTRIBUTE_CLEANING_RECORDS        = 'cleaning_records';
    private const ATTRIBUTE_MODEL                   = 'model';
    private const ATTRIBUTE_MAPFILE_URL             = 'mapfile_url';

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

    private const PROFILE_COMMAND       = 'Roborock.Command';
    private const PROFILE_ERRORCODE     = 'Roborock.Errorcode';
    private const PROFILE_STATE         = 'Roborock.State';
    private const PROFILE_FINDME        = 'Roborock.Findme';
    private const PROFILE_FANPOWER      = 'Roborock.Fanpower';
    private const PROFILE_WATERQUANTITY = 'Roborock.WaterQuantity';
    private const PROFILE_MAPS          = 'Roborock.Maps';
    private const PROFILE_CLEANAREA     = 'Roborock.Cleanarea';
    private const PROFILE_TOTALCLEANS   = 'Roborock.Totalcleans';
    private const PROFILE_VOLUME        = 'Roborock.Volume';
    private const PROFILE_BATTERY       = 'Roborock.Battery';
    private const PROFILE_CONSUMABLE    = 'Roborock.Consumable';
    private const PROFILE_DURATION      = 'Roborock.Duration';

    private const IDENT_VOLUME                    = 'volume';
    private const IDENT_COMMAND                   = 'command';
    private const IDENT_FAN_POWER                 = 'fan_power';
    private const IDENT_WATER_QUANTITY            = 'water_quantity';
    private const IDENT_CONSUMABLES               = 'consumables';
    private const IDENT_WATER_BOX_STATUS          = 'water_box_status';
    private const IDENT_WATER_BOX_CARRIAGE_STATUS = 'water_box_carriage_status'; //Anmerkung: der Unterschied zwischen 'water_box_status' und 'water_box_carriage_status' ist unklar
    private const IDENT_MAP_STATUS                = 'map_status';
    private const IDENT_MAP_PICTURE               = 'map_picture';
    private const IDENT_MAP_PICTURE_FILE          = 'map_picture_file';
    private const IDENT_MODEL                     = 'model';

    private const TIMER_UPDATE = 'RoborockTimerUpdate';

    // state code mapper
    protected array $state_codes = [
        0   => 'Unknown',
        1   => 'Starting up',
        2   => 'Sleeping',
        3   => 'Waiting',
        4   => 'Remote control',
        5   => 'Cleaning',
        6   => 'Returning to base',
        7   => 'Manual mode',
        8   => 'Charging',
        9   => 'Charging problem',
        10  => 'Pause',
        11  => 'Spot cleaning',
        12  => 'Malfunction',
        13  => 'Shutting down',
        14  => 'Software update',
        15  => 'Docking',
        16  => 'Go To',
        17  => 'Zone Clean',
        18  => 'Room Clean',
        22  => 'Dustbin Emptying',
        23  => 'Mop Washing',
        26  => 'Returning to base for mop washing',
        100 => 'Full'
    ];

    // error code mapper
    protected array $error_codes = [
        0  => 'None',
        1  => 'Laser sensor fault',
        2  => 'Collision sensor error',
        3  => 'Wheel floating',
        4  => 'Cliff sensor fault',
        5  => 'Main brush blocked',
        6  => 'Side brush blocked',
        7  => 'Wheel blocked',
        8  => 'Device stuck',
        9  => 'Dust bin missing',
        10 => 'Filter blocked',
        11 => 'Magnetic field detected',
        12 => 'Low battery',
        13 => 'Charging problem',
        14 => 'Battery failure',
        15 => 'Wall sensor fault',
        16 => 'Uneven surface',
        17 => 'Side brush failure',
        18 => 'Suction fan failure',
        19 => 'Unpowered charging station',
        20 => 'Unknown',
        21 => 'Vertical bumper pressed',
        22 => 'Dock locator dirty',
        23 => 'Dock location beacon lost',
        24 => 'No-go zone detected',
        27 => 'VibraRise system jammed',
        28 => 'Robot on carpet',
        34 => 'Ladestation blockiert bei automatischer Entleerung',
        38 => 'Hallsensor für Reinwassertank ausgelöst',
        39 => 'Überprüfen Sie den Schmutzwassertank.',
        46 => 'Staubbehälter nicht installiert'
    ];

    private const PUSH_NOTIFICATIONS = [
        [
            'enabled'  => true,
            'state_id' => 'errors', // enable all error codes
            'name'     => 'Error',
            'sound'    => 'alarm'
        ],
        [
            'enabled'  => false,
            'state_id' => 5,
            'name'     => 'Cleaning',
            'sound'    => '' // empty = default sound
        ],
        [
            'enabled'  => false,
            'state_id' => 8,
            'name'     => 'Charging',
            'sound'    => ''
        ],
        [
            'enabled'  => true,
            'state_id' => 6,
            'name'     => 'Returning to base',
            'sound'    => ''
        ],
        [
            'enabled'  => false,
            'state_id' => 15,
            'name'     => 'Docking',
            'sound'    => ''
        ]
    ];

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
    public function Create()
    {
        parent::Create();

        // connect to parent i/o device
        $this->ConnectParent('{4743ED9C-720B-D5EA-9B0C-0585803284F3}'); // IO Device

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
        $this->RegisterPropertyBoolean('clean_time', false);
        $this->RegisterPropertyBoolean('total_cleans', false);
        $this->RegisterPropertyBoolean('serial_number', false);
        $this->RegisterPropertyBoolean('timer_details', false);
        $this->RegisterPropertyBoolean('extended_info', false);
        $this->RegisterPropertyBoolean(self::PROPERTY_VOLUME, false);
        $this->RegisterPropertyBoolean('timezone', false);
        $this->RegisterPropertyBoolean('remote', false);

        $this->RegisterPropertyInteger('notification_instance', 0);
        $this->RegisterPropertyString('notifications', $this->GetPushNotifications());
        $this->RegisterPropertyString('zonecoordinates', '');

        $this->RegisterPropertyString(self::PROPERTY_XIAOMI_USER, '');
        $this->RegisterPropertyString(self::PROPERTY_XIAOMI_PASSWORD, '');

        // register update timer
        $this->RegisterPropertyInteger('UpdateInterval', 15);
        $this->RegisterTimer(self::TIMER_UPDATE, 0, 'Roborock_Update(' . $this->InstanceID . ');');

        // register kernel messages
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        // register attributes
        $this->RegisterAttributeString(self::ATTRIBUTE_TOKEN, '');
        $this->RegisterAttributeString(self::ATTRIBUTE_LOGIN_LOCATION_DATA, json_encode([], JSON_THROW_ON_ERROR));
        $this->RegisterAttributeString(self::ATTRIBUTE_LOGIN_ACCOUNT_DATA, json_encode([], JSON_THROW_ON_ERROR));
        $this->RegisterAttributeString(self::ATTRIBUTE_LAST_NOTIFICATION_STATE, '');
        $this->RegisterAttributeString(self::ATTRIBUTE_LAST_NOTIFICATION_ERROR, '');
        $this->RegisterAttributeString(self::ATTRIBUTE_CLEANING_RECORDS, '');
        $this->RegisterAttributeString(self::ATTRIBUTE_MODEL, '');
        $this->RegisterAttributeString(self::ATTRIBUTE_MAPFILE_URL, '');
    }

    /**
     * apply changes from configuration form.
     *
     * @return void
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $classname = get_class($this->device);
        if ($classname !== 'roborock_vacuum') {
            $profileSuffix = '.' . $this->device->GetModelType($classname);
        } else {
            $profileSuffix = '';
        }

        //  register profiles
        $this->RegisterProfileAssociation(
            self::PROFILE_COMMAND, 'Execute', '', '', 0, 4, 0, 0, VARIABLETYPE_INTEGER, [
                                     [0, $this->Translate('Start'), 'HollowLargeArrowRight', -1, 1],
                                     [1, $this->Translate('Pause'), 'Close', -1],
                                     [2, $this->Translate('Stop'), 'Close', -1],
                                     [3, $this->Translate('Spot'), 'Climate', -1],
                                     [4, $this->Translate('Charge'), 'Battery', -1],
                                     [5, $this->Translate('Locate'), 'Motion', -1]
                                 ]
        );

        $ass = [];
        foreach ($this->error_codes as $code => $error) {
            $ass[] = [$code, $error, '', -1];
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
        foreach ($this->state_codes as $code => $state) {
            $ass[] = [$code, $state, '', -1];
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
            self::PROFILE_FINDME, 'Robot', '', '', 0, 0, 0, 0, VARIABLETYPE_INTEGER, [
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
        if ($this->ReadPropertyBoolean('remote')) {
            $id = $this->RegisterVariableString('remote', $this->Translate('Remote Control'), '~HTMLBox', $this->_getPosition());
            IPS_SetIcon($id, 'Move');
            $this->SetJoystickHtml();
        } else {
            $this->UnregisterVariable('remote');
        }

        // command
        $this->RegisterVariableInteger(self::IDENT_COMMAND, $this->Translate('Command'), self::PROFILE_COMMAND, $this->_getPosition());
        $this->EnableAction(self::IDENT_COMMAND);

        // current state
        $this->RegisterVariableInteger('state', $this->Translate('State'), self::PROFILE_STATE, $this->_getPosition());

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
        if ($this->ReadPropertyBoolean(self::PROPERTY_MAP_STATUS)) {
            $this->RegisterVariableInteger(self::IDENT_MAP_STATUS, $this->Translate('Active Map'), self::PROFILE_MAPS, $this->_getPosition());
            $this->EnableAction(self::IDENT_MAP_STATUS);
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
        if ($this->ReadPropertyBoolean('clean_time')) {
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
            $id = $this->RegisterVariableString('serial_number', $this->Translate('Serial Number'), '', $this->_getPosition());
            IPS_SetIcon($id, 'Robot');
        } else {
            $this->UnregisterVariable('serial_number');
        }

        // timer details
        if ($this->ReadPropertyBoolean('timer_details')) {
            $id = $this->RegisterVariableString('timer_details', $this->Translate('Timer Details'), '~HTMLBox', $this->_getPosition());
            IPS_SetIcon($id, 'Clock');
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
            $this->RegisterVariableString('timezone', $this->Translate('Timezone'), '', $this->_getPosition());
        } else {
            $this->UnregisterVariable('timezone');
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

        // run only, when kernel is ready
        if (IPS_GetKernelRunlevel() === KR_READY) {
            // validate configuration
            $valid_config = $this->ValidateConfiguration();

            // set interval
            $this->SetUpdateInterval($valid_config);
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
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if (($Message === IPS_KERNELMESSAGE) && ($Data[0] === KR_READY)) {
            // validate configuration & set interval
            $valid_config = $this->ValidateConfiguration();
            $this->SetUpdateInterval($valid_config);
        }
    }

    /**
     * validate configuration.
     *
     * @return bool
     */
    private function ValidateConfiguration(): bool
    {
        // check if configuration is complete
        if (!$this->CheckConfiguration()) {
            $this->SetStatus(self::STATUS_INST_CONFIGURATION_INCOMPLETE);
            $this->SendDebug(__FUNCTION__, (string)$this->GetStatus(), 0);
            return false;
        }

        // read properties

        // check ip address
        if (filter_var(gethostbyname($this->ReadPropertyString(self::PROPERTY_IP)), FILTER_VALIDATE_IP) === false) {
            $this->SetStatus(self::STATUS_INST_IP_ADDRESS_IS_INVALID);
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

        // yay, configuration is valid! =)
        $this->SetStatus(IS_ACTIVE);

        if (get_class($this->device) === 'roborock_vacuum') {
            $this->_debug(
                __FUNCTION__,
                sprintf('The device ist operational (102), but the model \'%s\' is not yet well supported.', $this->ReadAttributeString(self::ATTRIBUTE_MODEL))
            );
        } else {
            $this->_debug(__FUNCTION__, 'The device ist operational (102)');
        }

        return true;
    }

    /**
     * set / unset update interval.
     *
     * @param bool $enable
     */
    private function SetUpdateInterval(bool $enable = true): void
    {
        $interval = $enable ? ($this->ReadPropertyInteger('UpdateInterval') * 1000) : 0;
        $this->SetTimerInterval(self::TIMER_UPDATE, $interval);
    }


    public function SetDeviceToken(string $token)
    {
        $this->WriteAttributeString(self::ATTRIBUTE_TOKEN, $token);

        // validate configuration
        $valid_config = $this->ValidateConfiguration();

        // set interval
        $this->SetUpdateInterval($valid_config);
    }

    /**
     * Update data.
     */
    public function Update(): void
    {
        if ($this->ValidateConfiguration()) {
            // Update state
            $this->Get_State();

            // update serial number, once
            if ($this->ReadPropertyBoolean('serial_number') && !$this->GetValue('serial_number')) {
                $this->Get_Serial_Number();
            }

            // update consumables
            if ($this->ReadPropertyBoolean(self::PROPERTY_CONSUMABLES) || $this->ReadPropertyBoolean(self::PROPERTY_CONSUMABLES_SEPARATE)) {
                $this->Get_Consumables();
            }

            // update clean summary
            if ($this->ReadPropertyBoolean('clean_time')) {
                $this->GetCleanSummary();
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
            if ($this->ReadPropertyBoolean('timezone') && !$this->GetValue('timezone')) {
                $this->GetTimezone();
            }

            // update maps status
            if ($this->ReadPropertyBoolean(self::PROPERTY_MAP_STATUS)) {
                $this->RequestData('get_multi_maps_list', []);
            }

            // update maps picture
            if ($this->ReadPropertyBoolean(self::PROPERTY_MAP_PICTURE)) {
                $this->GetMap();
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
    public function RequestRawData(string $method, array $options = [])
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
        $buffer = $this->_merge($payload, $options);

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
     * @return array|bool
     */
    private function RequestData(string $method, array $options = [])
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
        //$this->SendDebug('IPS', json_encode($_IPS, JSON_THROW_ON_ERROR), 0);
        /** @noinspection PhpUndefinedVariableInspection */
        if (($_IPS['SELF'] > 0 && $_IPS['SELF'] !== $this->InstanceID)
            || in_array($_IPS['SENDER'], ['Execute', 'Variable', 'RunScript', 'PHPModule'])) {
            $payload['immediate'] = true;
        }

        // merge payload & options
        $buffer = $this->_merge($payload, $options);

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
                    $data = $this->_merge($buffer, $io);

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
     */
    public function ReceiveData($JSONString)
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
    }

    /**
     * Check if a callback exist and execute method.
     *
     * @param array $buffer
     *
     * @return mixed
     */
    private function ExecuteCallback(array $buffer)
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
     * check if sound files are installing.
     *
     * @return array
     */
    public function sound_progress()
    {
        return $this->RequestData('get_sound_progress', [
            'immediate' => true
        ]);
    }

    /**
     * start cleaning.
     *
     * @return bool
     */
    public function Start()
    {
        $this->SetRoborockValue(self::IDENT_COMMAND, 0);
        return $this->RequestData('app_start');
    }

    /**
     * stop cleaning.
     *
     * @return bool
     */
    public function Stop()
    {
        $this->SetRoborockValue(self::IDENT_COMMAND, 2);
        return $this->RequestData('app_stop');
    }

    /**
     * start spot cleaning.
     *
     * @return bool
     */
    public function CleanSpot()
    {
        $this->SetRoborockValue(self::IDENT_COMMAND, 3);
        return $this->RequestData('app_spot');
    }

    /**
     * pause cleaning.
     *
     * @return bool
     */
    public function Pause()
    {
        $this->SetRoborockValue(self::IDENT_COMMAND, 1);
        return $this->RequestData('app_pause');
    }

    /**
     * return to dock.
     *
     * @return bool
     */
    public function Charge()
    {
        $this->SetRoborockValue(self::IDENT_COMMAND, 4);
        return $this->RequestData('app_charge');
    }

    /**
     * locate vacuum cleaner by voice message.
     *
     * @return array|bool
     */
    public function Locate()
    {
        $this->SetRoborockValue(self::IDENT_COMMAND, 5);
        return $this->RequestData('find_me');
    }

    // Consumables time remaining in %

    /**
     * get consumables time remaining in %.
     *
     * @return array
     */
    public function Get_Consumables()
    {
        return $this->RequestData('get_consumable');
    }

    /**
     * reset conmsumables.
     *
     * @param string $part filter|mainbrush|sidebrush|sensors
     *
     * @return bool
     */
    public function Reset_Consumable(string $part)
    {
        return $this->RequestData('reset_consumable', [
            'params' => [$part]
        ]);
    }

    /**
     * reset filter.
     *
     * @return bool
     */
    public function Reset_Filter()
    {
        return $this->Reset_Consumable($this->device::CONSUMABLES[Consumable::FILTER]);
    }

    /**
     * reset mainbrush.
     *
     * @return bool
     */
    public function Reset_Mainbrush()
    {
        return $this->Reset_Consumable($this->device::CONSUMABLES[Consumable::MAINBRUSH]);
    }

    /**
     * reset sidebrush.
     *
     * @return bool
     */
    public function Reset_Sidebrush()
    {
        return $this->Reset_Consumable($this->device::CONSUMABLES[Consumable::SIDEBRUSH]);
    }

    /**
     * reset sensor.
     *
     * @return bool
     */
    public function Reset_Sensors()
    {
        return $this->Reset_Consumable($this->device::CONSUMABLES[Consumable::SENSOR]);
    }

    /**
     * get clean summary.
     *
     * @return bool
     */
    public function GetCleanSummary()
    {
        return $this->RequestData('get_clean_summary');
    }

    /**
     * get clean record by record id.
     *
     * @param int|array $record_id
     *
     * @return array
     */
    private function GetCleanRecord($record_id)
    {
        return $this->RequestData('get_clean_record', [
            'params' => is_array($record_id) ? $record_id : [(int)$record_id]
        ]);
    }

    /**
     * get clean record map.
     *
     * @return bool
     */
    public function GetCleanRecordMap()
    {
        return $this->RequestData('get_clean_record_map');
    }

    private function loadMapFileFromFile(string $filename): bool
    {
        if (file_exists($filename)){
            $result = file_get_contents($filename);
        } else {
            return false;
        }
        if ($result){
            $data = gzdecode($result);
        } else {
            return false;
        }

        ini_set('memory_limit', '48M');
        $pic = new RRMapFileParser($data);
        if (!$pic->isValid()) {
            return false;
        }

        $draw = new RRMapDraw($pic);
        $picture = $draw->getImage($this->ReadPropertyInteger(self::PROPERTY_MAP_PICTURE_SCALE) / 100);

        if ($picture === '') {
            return false;
        }

        $this->CreateMapPictureVariable(self::IDENT_MAP_PICTURE_FILE, 'Karte aus Datei', sprintf('Map_File_%s.png', $this->InstanceID));

        return IPS_SetMediaContent(IPS_GetObjectIDByIdent(self::IDENT_MAP_PICTURE_FILE, $this->InstanceID), base64_encode($picture));

    }

    private function getMapdata(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result   = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        curl_close($ch);
        if ($httpcode !== 200) {
            trigger_error(sprintf('%s: httpcode: %s, url: %s', __FUNCTION__, (int)$httpcode, $url));
            return '';
        }

        //$fp = fopen('data1.gz', 'wb');
        //fwrite($fp, $result);
        //fclose($fp);

        return gzdecode($result);
    }

    private function getMapdread(): string
    {
        $filename = 'data1.gz';
        $fp       = fopen($filename, 'rb');
        $result   = fread($fp, filesize($filename));
        fclose($fp);

        return gzdecode($result);
    }

    /**
     * get map.
     *
     * @return bool
     */
    public function GetMap(): bool
    {
        if (!$this->ReadPropertyBoolean(self::PROPERTY_MAP_PICTURE)) {
            return false;
        }
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
            $count = 0;
            do {
                $mapName = $this->RequestData('get_map_v1');
                $count++;
            } while ((!$mapName || ($mapName === 'retry')) && $count < 3);

            $data = $this->getApiIO('/home/getmapfileurl', ['obj_name' => $mapName]);

            $this->_debug(__FUNCTION__, sprintf('getmapfile: %s', json_encode($data)));

            if (!isset($data['result']['url'])) {
                return false;
            }
            $url = ($data['result']['url']);

            $this->_debug(__FUNCTION__ . 'URL', $url);
            $this->WriteAttributeString(self::ATTRIBUTE_MAPFILE_URL, $url);
            $this->ReloadForm();
        }


        $data = $this->getMapdata($url);
        //var_dump($data);
        if (!$data) {
            return false;
        }

        //$data = $this->loadMapFileFromFile(IPS_GetKernelDir() . 'logs\s7karte');

        ini_set('memory_limit', '48M');
        $pic = new RRMapFileParser($data);
        if (!$pic->isValid()) {
            return false;
        }

        $draw = new RRMapDraw($pic);
        //echo '--------------GetImage!!!-----------------' . PHP_EOL;
        $picture = $draw->getImage($this->ReadPropertyInteger(self::PROPERTY_MAP_PICTURE_SCALE) / 100);

        //echo '--------------Get OLD Image!!!-----------------'.PHP_EOL;
        //$picture = $this->createPicture_old($data);

        if ($picture === '') {
            return false;
        }

        $this->CreateMapPictureVariable(self::IDENT_MAP_PICTURE, 'Map', sprintf('Map_%s.png', $this->InstanceID));

        IPS_SetMediaContent(IPS_GetObjectIDByIdent(self::IDENT_MAP_PICTURE, $this->InstanceID), base64_encode($picture));

        return true;
    }

    private function CreateMapPictureVariable(string $ident, string $name, string $FilePath = '')
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
     * @return array
     */
    public function Get_State()
    {
        return $this->RequestData('get_status');
    }

    /**
     * get serial number.
     *
     * @return array|bool
     */
    public function Get_Serial_Number()
    {
        return $this->RequestData('get_serial_number');
    }

    /**
     * get current dnd mode.
     *
     * @return array|bool
     */
    public function Get_DND_Mode()
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
     * @return bool
     */
    public function SetDNDTimer(int $starthour, int $startminutes, int $endhour, int $endminutes)
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
     * @return bool
     */
    public function DisableDND()
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
    public function Set_Timer(int $hour, int $minute, string $repetition)
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
     * @return bool
     */
    public function EnableTimer(string $timerid)
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
     * @return bool
     */
    public function DisableTimer(string $timerid)
    {
        return $this->RequestData('upd_timer', [
            'params' => [$timerid, 'off']
        ]);
    }

    /**
     * get timer details.
     *
     * @return array
     */
    public function Get_Timer_Details()
    {
        return $this->RequestData('get_timer');
    }

    /**
     * delete a timer.
     *
     * @param string $timerid
     *
     * @return bool
     */
    public function DeleteTimer(string $timerid)
    {
        return $this->RequestData('del_timer', [$timerid]);
    }

    /**
     * get timezone.
     *
     * @return bool
     */
    public function GetTimezone()
    {
        return $this->RequestData('get_timezone');
    }

    /**
     * set timezone to europe.
     *
     * @return bool
     */
    public function SetTimezoneEurope()
    {
        return $this->RequestData('set_timezone', ['Europe/Amsterdam']);
    }

    /**
     * install *.pkg sound package by url.
     *
     * @param string $sound_url
     *
     * @return bool
     */
    protected function InstallSound(string $sound_url)
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
     * @return bool
     */
    public function SetSoundLevel(int $level)
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
    public function Get_Fan_Power()
    {
        return $this->RequestData('get_custom_mode');
    }

    /**
     * set fan power (Quiet=38, Balanced=60, Turbo=77, Full Speed=90).
     *
     * @param int $power
     *
     * @return bool
     */
    public function Set_Fan_Power(int $power)
    {
        $this->SetRoborockValue(self::IDENT_FAN_POWER, $power);
        return $this->RequestData('set_custom_mode', [
            'params' => [$power]
        ]);
    }

    /**
     * Get the water quantity control during the cleaning process.
     *
     * @return array|bool
     */
    public function Get_Water_Quantity_Control()
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
    public function Set_Water_Quantity_Control(int $mode)
    {
        $this->SetRoborockValue(self::IDENT_WATER_QUANTITY, $mode);
        return $this->RequestData('set_water_box_custom_mode', [
            'params' => [$mode]
        ]);
    }

    /**
     * move robot to direction.
     *
     * @param int      $direction -100..100
     * @param int      $velocity  0..100
     * @param int|null $time      in ms
     *
     * @return array|bool
     */
    public function Move_Direction(int $direction, int $velocity, int $time = 1000)
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
    public function LoadMap(int $mapIndex)
    {
        return $this->RequestData('load_multi_map', ['params' => [$mapIndex]]);
    }


    /**
     * start remote control.
     *
     * @return bool
     */
    protected function StartRemoteControl()
    {
        return $this->RequestData('app_rc_start');
    }

    /**
     * stop remote control.
     *
     * @return bool
     */
    protected function StopRemoteControl()
    {
        return $this->RequestData('app_rc_end');
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
     * @return bool
     */
    public function ZoneClean(int $lower_left_corner_x, int $lower_left_corner_y, int $upper_right_corner_x, int $upper_right_corner_y, int $number)
    {
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

    public function ZoneCleanRoomname(string $roomname, int $number)
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

    public function ZoneCleanRoomnumber(int $roomnumber, int $number)
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
    public function ZoneCleanMulti(string $multizone)
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
    public function ZoneCleanMultiName(string $multizone)
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
     * @return bool
     */
    public function GotoTarget(int $x, int $y)
    {
        return $this->RequestData('app_goto_target', [
            'params' => [
                $x,
                $y
            ]
        ]);
    }

    /**
     * get device info.
     *
     * @return array
     */
    public function GetDeviceInfo()
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
        $this->SetRoborockValue('dnd_mode', $state);

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
        $this->SetRoborockValue('dnd_starttime', $starttime);
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
        $this->SetRoborockValue('dnd_endtime', $endtime);
        $starttime     = GetValueFormatted($this->GetIDForIdent('dnd_starttime'));
        $time          = explode(':', $starttime);
        $start_hour    = (int)$time[0];
        $start_minutes = (int)$time[1];
        $this->SetDNDTimer($start_hour, $start_minutes, $end_hour, $end_minutes);
    }

    /**
     * get sounds.
     *
     * @return bool
     */
    public function Get_Sound()
    {
        return $this->RequestData('get_current_sound');
    }

    /**
     * get sound volume.
     *
     * @return int
     */
    public function Get_SoundVolume()
    {
        return $this->RequestData('get_sound_volume');
    }

    /**
     * set sound volume.
     *
     * @param int $volume
     *
     * @return bool
     */
    public function Set_SoundVolume(int $volume)
    {
        $this->SetRoborockValue(self::IDENT_VOLUME, $volume);
        return $this->RequestData('change_sound_volume', [
            'params' => [$volume]
        ]);
    }

    /**
     * Roborock Vacuum 1S segment clean.
     *
     * @param int $segmentid
     *
     * @return bool
     */
    public function Start_Segment_Clean(int $segmentid)
    {
        return $this->RequestData('app_segment_clean', [
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
     * @return bool
     */
    public function Start_Segment_Clean_Ex(string $segmentIds)
    {
        $arr = json_decode($segmentIds, true, 512, JSON_THROW_ON_ERROR);

        return $this->RequestData('app_segment_clean', [
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
     * @param        $Value
     *
     * @return bool|void
     */
    public function RequestAction($Ident, $Value)
    {
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
            case 'ReloadForm':
                $this->ReloadForm();
                break;
            case 'LoadMapFile':
                return $this->loadMapFileFromFile($Value);
            default:
                $this->_debug('request action', 'Invalid $Ident <' . $Ident . '>');
        }
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
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $MinValue
     * @param $MaxValue
     * @param $Stepsize
     * @param $Digits
     * @param $Vartype
     * @param $Associations
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
     * checks, if configuration is complete.
     *
     * @return bool
     */
    private function CheckConfiguration()
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

    public function SendPushNotificationTest(int $state_id, int $error_id, bool $force_send): void
    {
        $this->SendPushNotification($state_id, $error_id, $force_send);
    }

    /**
     * Send push notifications.
     *
     * @param string $state_id
     * @param int    $error_id
     * @param bool   $force_send
     *
     * @return bool
     */
    protected function SendPushNotification($state_id = 'errors', $error_id = 0, bool $force_send = false)
    {
        // get codes by state_id
        if ($state_id === 'errors') {
            $codes    = $this->error_codes;
            $state_id = $error_id;
            $prefix   = $this->Translate('Error') . ': ';

            $notification_ident = self::ATTRIBUTE_LAST_NOTIFICATION_ERROR;
        } else {
            $codes  = $this->state_codes;
            $prefix = '';

            $notification_ident = self::ATTRIBUTE_LAST_NOTIFICATION_STATE;
        }

        // check notification
        $last_notification = $this->ReadAttributeString($notification_ident);
        $this->WriteAttributeString($notification_ident, $state_id);

        // return false, when last notification is the same as current notification or id is 0
        if ((($last_notification === $state_id) && !$force_send) || ($state_id === 0)) {
            return false;
        }

        // check notification instance (webfront)
        // get notification settings
        if (($instance_id = $this->ReadPropertyInteger('notification_instance'))
            && $notifications = @json_decode($this->ReadPropertyString('notifications'), true)) {
            // loop notifications and search for current state
            foreach ($notifications as $notification) {
                if ($notification['state_id'] === $state_id) {
                    // check if notification is enabled
                    if ($notification['enabled'] || $force_send) {
                        // send notification
                        if ($state_id > 0 && isset($codes[$state_id])) {
                            // build message
                            $title   = IPS_GetName($this->InstanceID); // instance name
                            $message = $prefix . $this->Translate($codes[$state_id]);

                            // send notification
                            WFC_PushNotification($instance_id, $title, $message, $notification['sound'], 0);
                        }
                    }

                    // break loop
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get push notifications.
     *
     * @return string json encoded settings
     */
    protected function GetPushNotifications()
    {
        // translate default notifications
        $notifications = self::PUSH_NOTIFICATIONS;

        foreach ($notifications as &$notification) {
            $notification['name'] = $this->Translate($notification['name']);
        }
        unset ($notification);

        // merge with current settings
        if ($current_notifications = @$this->ReadPropertyString('notifications')) {
            $current_notifications = json_decode($current_notifications, true);
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

        return json_encode($notifications);
    }

    /***********************************************************
     * Configuration Form
     ***********************************************************/

    /**
     * build configuration form.
     *
     * @return string
     */
    public function GetConfigurationForm()
    {
        // update status, when configuration is not complete
        if (!$this->CheckConfiguration()) {
            $this->SetStatus(self::STATUS_INST_CONFIGURATION_INCOMPLETE);
        }

        $form = json_encode([
                                'elements' => $this->FormElements(),
                                'actions'  => $this->FormActions(),
                                'status'   => $this->FormStatus()
                            ]);


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
                'name'    => 'UpdateInterval',
                'type'    => 'NumberSpinner',
                'caption' => 'Update Interval Roborock',
                'suffix'  => 'Seconds',
                'minimum' => 0
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
                        'caption' => 'Active Map'
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
                                'maximum' => 200,
                                'suffix'  => '%'
                            ]
                        ]
                    ],
                    [
                        'name'    => 'error_code',
                        'type'    => 'CheckBox',
                        'caption' => 'Error Code'
                    ],
                    [
                        'name'    => self::PROPERTY_CONSUMABLES,
                        'type'    => 'CheckBox',
                        'caption' => 'Consumables'
                    ],
                    [
                        'name'    => self::PROPERTY_CONSUMABLES_SEPARATE,
                        'type'    => 'CheckBox',
                        'caption' => 'Consumables (Separate Variables)'
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
                        'name'    => 'clean_time',
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
                        'name'    => 'remote',
                        'type'    => 'CheckBox',
                        'caption' => 'Remote Control'
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
                                'label'   => 'State ID',
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
                'visible' => false,   //zur Zeit deaktiviert. Nutzen ist fraglich
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

        ];

    }

    protected function GetNumberZones()
    {
        return count($this->GetZones());
    }

    protected function GetZoneID(): int
    {
        return $this->GetNumberZones() + 1;
    }

    public function GetZones()
    {
        $zones_json = $this->ReadPropertyString('zonecoordinates');
        $this->_debug('Zones', $zones_json);
        if ($zones_json === '') {
            $zones = [];
        } else {
            $zones = json_decode($zones_json, true);
        }
        return $zones;
    }

    public function GetZoneCoordinatesByNumber(int $roomnumber)
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

    public function GetZoneCoordinatesByName(string $roomname)
    {
        $zones  = $this->GetZones();
        $zoneid = -1;
        foreach ($zones as $key => $zone) {
            if ($zone['roomname'] == $roomname) {
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
                'type' => 'TestCenter'
            ],
            [
                'type'    => 'Button',
                'label'   => 'Xiaomi Login Test',
                'onClick' => '$module = new IPSModule($id); if (Roborock_GetTokenFromXiaomi($id)){echo $module->Translate(\'OK\');} else {echo $module->Translate(\'Error\');};'
            ],
            [
                'type'  => 'RowLayout',
                'name'  => 'Row_HandleMap',
                'visible' => $this->ReadPropertyBoolean(self::PROPERTY_MAP_PICTURE),
                'items' => [
                    [
                        'type'    => 'Button',
                        'caption' => 'Get Map',
                        'onClick' => '$module = new IPSModule($id); if (Roborock_GetMap($id)){echo $module->Translate(\'OK\');IPS_RequestAction($id, "ReloadForm", true);} else {echo $module->Translate(\'Error\');};'
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
                'label'   => 'Update',
                'onClick' => 'Roborock_Update($id);'
            ],
            [
                'type'    => 'Button',
                'label'   => 'Show Room Mapping',
                'onClick' => 'print_r(Roborock_Get_Room_Mapping($id));'
            ],
            [
                'type'  => 'RowLayout',
                'visible' => $this->ReadPropertyBoolean(self::PROPERTY_CONSUMABLES) || $this->ReadPropertyBoolean(self::PROPERTY_CONSUMABLES_SEPARATE),
                'items' => [
                    [
                        'type'    => 'Button',
                        'label'   => 'Reset Filter',
                        'onClick' => 'Roborock_Reset_Filter($id);'
                    ],
                    [
                        'type'    => 'Button',
                        'label'   => 'Reset Mainbrush',
                        'onClick' => 'Roborock_Reset_Mainbrush($id);'
                    ],
                    [
                        'type'    => 'Button',
                        'label'   => 'Reset Sidebrush',
                        'onClick' => 'Roborock_Reset_Sidebrush($id);'
                    ],
                    [
                        'type'    => 'Button',
                        'label'   => 'Reset Sensors',
                        'onClick' => 'Roborock_Reset_Sensors($id);'
                    ]
                ]
            ],

             [
                'type'    => 'Button',
                'label'   => 'Push Notification Test',
                'onClick' => 'Roborock_SendPushNotificationTest($id, 5, 0, true);'
            ],
            /*
                [
                    'type' => 'Button',
                    'caption' => 'Update Joystick - TEST',
                    'onClick' => 'Roborock_SetJoystickHtml($id);'
                ]
            */
        ];

    }

    /**
     * return from status.
     *
     * @return array
     */
    protected function FormStatus()
    {
        $form = [
            [
                'code'    => self::STATUS_INST_CONFIGURATION_INCOMPLETE,
                'icon'    => 'inactive',
                'caption' => 'Please follow the instructions.'
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
                'caption' => 'no roborock was found on that ip and token.'
            ]
        ];

        return $form;
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
        $joystick = str_replace('[instance_id]', $this->InstanceID, $joystick);
        $this->SetValue('remote', $joystick);
    }

    /**
     * check for variable and set value.
     *
     * @param $ident
     * @param $value
     */
    private function SetRoborockValue($ident, $value)
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
    private function _zeroPadding($number, $padding = 2)
    {
        return str_pad((string)$number, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * send debug log.
     *
     * @param string $notification
     * @param string $message
     * @param int    $format 0 = Text, 1 = Hex
     */
    private function _debug(string $notification = null, string $message = null, int $format = 0): void
    {
        $this->SendDebug($notification, $message, $format);
    }

    /**
     * merge arrays with key attention.
     *
     * @param array $data  Array to be merged
     * @param mixed $merge Array to merge with. The argument and all trailing arguments will be array cast when merged
     *
     * @return array Merged array
     */
    private function _merge(array $data, array $merge)
    {
        $args   = array_slice(func_get_args(), 1);
        $return = $data;

        foreach ($args as &$curArg) {
            $stack[] = [(array)$curArg, &$return];
        }
        unset($curArg);

        while (!empty($stack)) {
            foreach ($stack as $curKey => &$curMerge) {
                foreach ($curMerge[0] as $key => &$val) {
                    if (!empty($curMerge[1][$key]) && (array)$curMerge[1][$key] === $curMerge[1][$key] && (array)$val === $val) {
                        $stack[] = [&$val, &$curMerge[1][$key]];
                    } elseif ((int)$key === $key && isset($curMerge[1][$key])) {
                        $curMerge[1][] = $val;
                    } else {
                        $curMerge[1][$key] = $val;
                    }
                }
                unset ($val);
                unset($stack[$curKey]);
            }
            unset($curMerge);
        }
        return $return;
    }

    /**
     * return incremented position.
     *
     * @return int
     */
    private function _getPosition()
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
    private function _getTimerDay($day_of_week)
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
    private function _getTimerRepetition($repetitionstring)
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
    public function GetTokenFromXiaomi(): bool
    {
        // read properties
        $user     = $this->ReadPropertyString(self::PROPERTY_XIAOMI_USER);
        $password = $this->ReadPropertyString(self::PROPERTY_XIAOMI_PASSWORD);

        $clientId = $this->randomClientId();
        $this->SendDebug(__FUNCTION__, 'user/password: ' . json_encode([$user, $password], JSON_THROW_ON_ERROR), 0);

        // -- login --
        $loginData = $this->login($user, $clientId);

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
        $loginAccountData = $this->login_account($user, $password, $clientId, $loginData['qs'], $loginData['callback'], $loginData['_sign']);

        if (!$loginAccountData || !isset($loginAccountData['ssecurity'], $loginAccountData['userId'], $loginAccountData['location'])) {
            $this->SendDebug(__FUNCTION__ . ': ERROR', 'Login failed, please check user/password at https://account.xiaomi.com', 0);
            return false;
        }

        $this->WriteAttributeString(self::ATTRIBUTE_LOGIN_ACCOUNT_DATA, json_encode($loginAccountData));

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
        $loginLocationData = $this->login_location($clientId, $loginAccountData['location']);
        if (!$loginLocationData || !isset($loginLocationData['userId'], $loginLocationData['serviceToken'])) {
            $this->SendDebug(__FUNCTION__ . ': ERROR', 'Login Location failed', 0);
            return false;
        }
        $this->WriteAttributeString(self::ATTRIBUTE_LOGIN_LOCATION_DATA, json_encode($loginLocationData));

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
        if ($deviceData === false) {
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

    private function randomClientId(): string
    {
        $clientId = '';
        for ($i = 0; $i < 7; $i++) {
            $clientId .= chr(random_int(97, 122)); // buchstaben a bis z
        }
        return $clientId;
    }

    private function login(string $user, string $clientId)
    {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Android-7.1.1-1.0.0-ONEPLUS A3010-136-' . $clientId . ' APP/xiaomi.smarthome APPV/62830',
            'Cookie: sdkVersion=3.8.6; userId=' . trim($user) . '; deviceId=' . $clientId
        ];
        $ch      = curl_init('https://account.xiaomi.com/pass/serviceLogin?sid=xiaomiio&_json=true');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result   = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($httpcode !== 200) {
            trigger_error(sprintf('%s: httpcode: %s', __FUNCTION__, (int)$httpcode));
            return false;
        }
        return $this->parseJson($result);
    }

    private function login_account(string $user, string $password, string $clientId, string $qs, string $callback, string $sign)
    {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Android-7.1.1-1.0.0-ONEPLUS A3010-136-9D28921C354D7 APP/xiaomi.smarthome APPV/62830',
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
        $ch   = curl_init('https://account.xiaomi.com/pass/serviceLoginAuth2');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result   = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        curl_close($ch);
        if ($httpcode !== 200) {
            trigger_error(sprintf('%s: httpcode: %s', __FUNCTION__, (int)$httpcode));
            return false;
        }
        return $this->parseJson($result);
    }

    function login_location(string $clientId, string $location)
    {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Android-7.1.1-1.0.0-ONEPLUS A3010-136-9D28921C354D7 APP/xiaomi.smarthome APPV/62830',
            'Cookie: sdkVersion=accountsdk-18.8.15; deviceId=' . $clientId
        ];
        $ch      = curl_init($location);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result      = curl_exec($ch);
        $httpcode    = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $header = substr($result, 0, $header_size);
        $result = substr($result, $header_size);

        curl_close($ch);

        if (($httpcode === false) || ($httpcode !== 200)) {
            trigger_error(sprintf('%s: httpcode: %s', __FUNCTION__, (int)$httpcode));
            return false;
        }

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
        $loginLocationData = json_decode($this->ReadAttributeString(self::ATTRIBUTE_LOGIN_LOCATION_DATA), true);
        if ($loginLocationData === []) {
            return [];
        }

        $server = 'de';

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'x-xiaomi-protocal-flag-cli: PROTOCAL-HTTP2',
            'User-Agent: Android-7.1.1-1.0.0-ONEPLUS A3010-136-9D28921C354D7 APP/xiaomi.smarthome APPV/62830',
            'Cookie: userId=' . $loginLocationData['userId'] . '; yetAnotherServiceToken=' . $loginLocationData['serviceToken'] . '; serviceToken='
            . $loginLocationData['serviceToken'] . '; locale=de_DE; timezone=GMT%2B01%3A00; is_daylight=1; dst_offset=3600000; channel=MI_APP_STORE'
        ];

        $params = [
            'key'   => 'data',
            'value' => json_encode($values)
        ];

        $loginAccountData = json_decode($this->ReadAttributeString(self::ATTRIBUTE_LOGIN_ACCOUNT_DATA), true);
        if ($loginAccountData === []) {
            return [];
        }

        $body = $this->generateSignature($loginAccountData['ssecurity'], $params, $path);
        $body = http_build_query($body);

        $ch = curl_init('https://' . $server . '.api.io.mi.com/app' . $path);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result   = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        curl_close($ch);
        if (($httpcode !== 200)) {
            trigger_error(sprintf('%s: httpcode: %s', __FUNCTION__, (int)$httpcode));
            return [];
        }
        return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    }

    private function getDeviceStatus()
    {
        $device_list = $this->getApiIO('/home/device_list', ['getVirtualModel' => false, 'getHuamiDevices' => 0]);
        $this->_debug(__FUNCTION__, sprintf('device_list: %s', json_encode($device_list)));

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

    function generateSignature(string $ssecurity, array $params, string $path): array
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
     */
    private function get_serial_number_callback(array $data)
    {
        $serial = isset($data['result'][0]['serial_number']) ? $data['result'][0]['serial_number'] : '';
        $this->SetRoborockValue('serial_number', $serial);

        return $serial;
    }

    /**
     * Callback: Serial Number.
     *
     * @param array $data
     *
     * @return string
     */
    protected function load_multi_map_callback(array $data)
    {
        //       $this->SendDebug(__FUNCTION__, json_encode($data), 0);

        if ($data['result'][0] === 'ok') {
            $map_status = $data['params'][0];
            $this->SetRoborockValue(self::IDENT_MAP_STATUS, $map_status);

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
     */
    protected function get_timezone_callback(array $data)
    {
        $timezone = isset($data['result'][0]) ? $data['result'][0] : '';
        $this->SetRoborockValue('timezone', $timezone);

        return $timezone;
    }

    /**
     * Callback: Device Info.
     *
     * @param array $data
     *
     * @return array
     */
    protected function miio_info_callback(array $data): array
    {
        if (isset($data['result'])) {
            $info = $data['result'];

            $hardware_version = $info['hw_ver'];
            $this->SetRoborockValue('hw_ver', $hardware_version);

            $firmware_version = $info['fw_ver'];
            $this->SetRoborockValue('fw_ver', $firmware_version);

            $ssid = $info['ap']['ssid'];
            $this->SetRoborockValue('ssid', $ssid);

            $rssi = $info['ap']['rssi'];
            $this->SetRoborockValue('rssi', $rssi);

            $ip = $info['netif']['localIp'];
            $this->SetRoborockValue('local_ip', $ip);

            $model = $info['model'];
            $this->SetRoborockValue(self::IDENT_MODEL, $model);
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
            $this->SetRoborockValue('mac', $mac);

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
        $this->SetRoborockValue('battery', $battery);
        $ret['battery'] = $battery;

        $state = (int)$result['state'];
        if ($state === 8 && $battery === 100) {
            $this->SetRoborockValue('state', 100);
        } else {
            $this->SetRoborockValue('state', $state);
        }
        $ret['state'] = $state;

        $clean_area = (float)($result['clean_area'] / 1000000); // cm2 -> m2
        $this->SetRoborockValue('clean_area', $clean_area);
        $ret['clean_area'] = $clean_area;

        $clean_time = $result['clean_time']; // sec
        $this->SetRoborockValue('clean_time', $clean_time);
        $ret['clean_time'] = $clean_time;

        $error_code = (int)$result['error_code'];
        $this->SetRoborockValue('error_code', $error_code);
        $ret['error_code'] = $error_code;

        $fan_power = (int)$result['fan_power'];
        $this->SetRoborockValue(self::IDENT_FAN_POWER, $fan_power);
        $ret['fan_power'] = $fan_power;

        if (isset($result['water_box_mode'])) {
            $water_box_mode = (int)$result['water_box_mode'];
            $this->SetRoborockValue(self::IDENT_WATER_QUANTITY, $water_box_mode);
            $ret['water_box_mode'] = $water_box_mode;
        }

        if (isset($result['water_box_status'])) {
            $water_box_status = (bool)$result['water_box_status'];
            $this->SetRoborockValue(self::IDENT_WATER_BOX_STATUS, $water_box_status);
            $ret['water_box_status'] = $water_box_status;
        }

        if (isset($result['water_box_carriage_status'])) {
            $water_box_carriage_status = (bool)$result['water_box_carriage_status'];
            $this->SetRoborockValue(self::IDENT_WATER_BOX_CARRIAGE_STATUS, $water_box_carriage_status);
            $ret['water_box_carriage_status'] = $water_box_carriage_status;
        }

        if (isset($result['map_status'])) {
            $map_status = (int)$result['map_status'] >> 2;
            $this->SetRoborockValue(self::IDENT_MAP_STATUS, $map_status);
            $ret['map_status'] = $map_status;
        }


        // send push notifications
        $this->SendPushNotification($state);
        $this->SendPushNotification('errors', $error_code);

        // return values
        return $ret;
    }

    /**
     * Callback: Consumables.
     *
     * @param array $data
     *
     * @return array
     */
    private function get_consumable_callback(array $data): array
    {
        if (isset($data['result'][0])) {
            $consumables = [];
            $ret         = [];

            foreach ($this->device::CONSUMABLES as $ident => $name) {
                $max_work_time = consumable::GetMaxWorkTime($ident);
                if (!isset($data['result'][0][$name])){
                    $this->_debug(__FUNCTION__, sprintf('Cunsumable \'%s\' not found.', $name));
                    continue;
                }
                $work_time     = $data['result'][0][$name];
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
                    $this->SetRoborockValue($ident, $work_time_percent);
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

                $this->SetRoborockValue(self::IDENT_CONSUMABLES, $html);
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
     */
    protected function get_clean_summary_callback(array $data)
    {
        $total_cleaning_time = null;
        $area_cleaned        = null;
        $cleanups            = null;
        $clean_records       = null;

        //clean_time
        if (isset($data['result'][0])) {
            $total_cleaning_time = $data['result'][0];
            $this->SetRoborockValue('total_clean_time', $total_cleaning_time); // sec
        }

        if (isset($data['result']['clean_time'])) {
            $total_cleaning_time = $data['result']['clean_time'];
            $this->SetRoborockValue('total_clean_time', $total_cleaning_time); // sec
        }

        //clean_area
        if (isset($data['result'][1])) {
            $area_cleaned = (float)$data['result'][1] / 1000000; // cm2 -> m2
            $this->SetRoborockValue('total_clean_area', $area_cleaned);
        }

        if (isset($data['result']['clean_area'])) {
            $area_cleaned = (float)$data['result']['clean_area'] / 1000000; // cm2 -> m2
            $this->SetRoborockValue('total_clean_area', $area_cleaned);
        }

        //clean_count
        if (isset($data['result'][2])) {
            $cleanups = (int)$data['result'][2];
            $this->SetRoborockValue('total_cleans', $cleanups);
        }

        if (isset($data['result']['clean_count'])) {
            $cleanups = (int)$data['result']['clean_count'];
            $this->SetRoborockValue('total_cleans', $cleanups);
        }

        //records
        if (isset($data['result'][3])) {
            $clean_records = $data['result'][3];
            $this->SetBuffer('CleanRecords', json_encode($clean_records));
            // update clean record details
            foreach ($clean_records as $record_id) {
                $this->GetCleanRecord($record_id);
            }
        }

        if (isset($data['result']['records'])) {
            $clean_records = $data['result']['records'];
            $this->SetBuffer('CleanRecords', json_encode($clean_records));
            // update clean record details
            foreach ($clean_records as $record_id) {
                $this->GetCleanRecord($record_id);
            }
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
     */
    protected function get_multi_maps_list_callback(array $data)
    {
        $ass = [];
        if (isset($data['result'][0]['multi_map_count'])) {
            $result = $data['result'][0];
            foreach ($result['map_info'] as $index => $mapInfo) {
                if ($mapInfo['name']){
                    $ass[] = [$index, $mapInfo['name'], '', -1];
                } else {
                    $ass[] = [$index, $this->Translate('Room') . $mapInfo['mapFlag'], '', -1];
                }
            }

            if (count($ass)){
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
        }
    }


    /**
     * Callback: Clean Record Details.
     *
     * @param array $data
     *
     * @return array
     */
    protected function get_clean_record_callback(array $data)
    {
        if (isset($data['result'][0])) {
            $record = $data['result'][0];

            if (isset($record[0])) {
                $start_time = $record[0];
            }
            if (isset($record['begin'])) {
                $start_time = $record['begin'];
            }


            if (isset($record[1])) {
                $end_time = $record[1];
            }
            if (isset($record['end'])) {
                $end_time = $record['end'];
            }

            if (isset($record[2])) {
                $cleaning_duration = $record[2];
            }
            if (isset($record['duration'])) {
                $cleaning_duration = $record['duration'];
            }

            if (isset($record[3])) {
                $area = (float)$record[3] / 1000000; //cm2 -> m2
            }
            if (isset($record['area'])) {
                $area = (float)$record['area'] / 1000000; //cm2 -> m2
            }

            if (isset($record[4])) {
                $errors = $record[4];
            }
            if (isset($record['error'])) {
                $errors = $record['error'];
            }

            if (isset($record[5])) {
                $completed = $record[5];
            }
            if (isset($record['complete'])) {
                $completed = $record['complete'];
            }


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

            // update html, when enabled
            if ($this->ReadPropertyBoolean('clean_time')) {
                $html_data = [
                    $data['starttime'] => $data
                ];

                if ($tmp_data = $this->ReadAttributeString(self::ATTRIBUTE_CLEANING_RECORDS)) {
                    $tmp_data = json_decode($tmp_data, true, 512, JSON_THROW_ON_ERROR);

                    // merge temporary data with html data
                    $html_data = $this->_merge(
                        $tmp_data,
                        $html_data
                    );

                    // sort by key (time)
                    krsort($html_data);

                    // show last 5 records, only
                    if (count($html_data) > 5) {
                        $html_data = array_slice($html_data, 0, 5, true);
                    }
                }

                $this->WriteAttributeString(self::ATTRIBUTE_CLEANING_RECORDS, json_encode($html_data));

                // build html
                $cleaning_records = [];
                foreach ($html_data as $clean_record) {
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

                    $cleaning_records[] = [
                        $this->Translate($clean_day),
                        $clean_date . ' ' . $start_hour . ':' . $start_minutes . ' - ' . $end_hour . ':' . $end_minutes,
                        $cleaning_duration,
                        $area . ' m<sup>2</sup>',
                        ($errors ? '<span class="unicode red">✖</span>' : '-'),
                        ($completed ? '<span class="unicode green">✔</span>' : '<span class="unicode red">✖</span>')
                    ];
                }

                // build html table
                $html = $this->_convertDataToTable([
                                                       'table' => [
                                                           'head' => [
                                                               $this->Translate('Day'),
                                                               $this->Translate('Date'),
                                                               $this->Translate('Cleaning Duration'),
                                                               $this->Translate('Area'),
                                                               $this->Translate('Errors'),
                                                               $this->Translate('Completed'),
                                                           ],
                                                           'body' => $cleaning_records
                                                       ]
                                                   ]);

                // save html table
                $this->SetRoborockValue('cleaning_records', $html);
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
    protected function get_dnd_timer_callback(array $data)
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
            $this->SetRoborockValue('dnd_starttime', $start_unixtime);

            $end_time     = $end_hour . ':' . $end_minute;
            $end_unixtime = strtotime($end_time);

            $this->SetRoborockValue('dnd_endtime', $end_unixtime);
            $this->SetRoborockValue('dnd_mode', $dnd_state);

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
     * Callback: Timer.
     *
     * @param array $data
     *
     * @return array
     */
    protected function get_timer_callback(array $data)
    {
        if (isset($data['result'])) {
            $timers = $data['result'];
            if (empty($timers)) {
                // save html table
                $this->SetRoborockValue('timer_details', '');
                return ['timer' => 'no timer set'];
            }
            $timer_list = [];
            foreach ($timers as $key => $timer) {
                $setuptime = $timer[0]; // setup time of this schedule (Unix time)
                // $setuptimestring = date('h:i:s',$setuptime);
                $timer_active = $timer[1]; // Is this schedule active
                $timing       = $timer[2];
                $time_detail  = $timing[0];
                $command      = $timing[1][0];
                // $unknown = $timing[1][1];
                $timer_data = explode(' ', $time_detail);
                $minute     = $timer_data[0];
                if ($minute == '0') {
                    $minute = '00';
                }
                $hour         = $timer_data[1];
                $day_of_month = $timer_data[2];
                $month        = $timer_data[3];
                $day_of_week  = $timer_data[4];
                $repetition   = $this->_getTimerDay($day_of_week);
                $time_string  = $hour . ':' . $minute;

                $timer_entry[]                          = [
                    $time_string . '<br>' . $repetition,
                    $timer_active
                ];
                $timer_list[$setuptime]['timer_active'] = $timer_active;
                $timer_list[$setuptime]['minute']       = $minute;
                $timer_list[$setuptime]['hour']         = $hour;
                $timer_list[$setuptime]['day_of_month'] = $day_of_month;
                $timer_list[$setuptime]['month']        = $month;
                $timer_list[$setuptime]['time_string']  = $time_string;
                $timer_list[$setuptime]['repetition']   = $repetition;
                $timer_list[$setuptime]['command']      = $command;
            }

            // build html table
            $html = $this->_convertDataToTable([
                                                   'table' => [
                                                       'head' => [
                                                           $this->Translate('Timer'),
                                                           $this->Translate('Status'),
                                                       ],
                                                       'body' => $timer_entry
                                                   ]
                                               ]);

            // save html table
            $this->SetRoborockValue('timer_details', $html);

            // return values
            return $timer_list;
        }

        // fallback
        return [];
    }

    /**
     * Callback: Fan Power.
     *
     * @param array $data
     *
     * @return int
     */
    protected function get_custom_mode_callback(array $data)
    {
        if (isset($data['result'][0])) {
            $fan_power = $data['result'][0];
            $this->SetRoborockValue(self::IDENT_FAN_POWER, $fan_power);

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
     */
    protected function get_water_box_custom_mode_callback(array $data)
    {
        if (isset($data['result'][0])) {
            $water_flow_mode = $data['result'][0];
            $this->SetRoborockValue(self::IDENT_WATER_QUANTITY, $water_flow_mode);

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
     */
    protected function get_sound_volume_callback(array $data)
    {
        if (isset($data['result'][0])) {
            $volume = $data['result'][0];
            $type   = gettype($volume);
            if ($type === 'integer') {
                $this->SetRoborockValue(self::IDENT_VOLUME, $volume);
            }

            return $volume;
        }

        // fallback
        return 0;
    }

    /**
     * Callback: Change Sound Volume.
     *
     * @param array $data
     *
     * @return bool
     */
    protected function change_sound_volume_callback(array $data)
    {
        // start & stop device quickly, to check volume
        if (in_array($this->GetValue('state'), [2, 3, 8, 10, 15, 100], true)) {
            $this->Start();
            $this->Stop();
        }

        // fallback
        return true;
    }

    /**
     * Callback: Start Remote Control.
     *
     * @param array $data
     *
     * @return bool
     */
    protected function app_rc_start_callback(array $data)
    {
        // update state to 'Remote Control'
        $this->SetRoborockValue('state', 4);
        return true;
    }

    /**
     * Callback: Stop Remote Control.
     *
     * @param array $data
     *
     * @return bool
     */
    protected function app_rc_end_callback(array $data)
    {
        // update state to 'Waiting'
        $this->SetRoborockValue('state', 3);
        return true;
    }

    /**
     * Callback: Clean Record Map.
     *
     * @param array $data
     */
    protected function get_clean_record_map_callback(array $data)
    {
    }

    /**
     * Callback: Map v1
     *
     * @param array $data
     */
    protected function get_map_v1_callback(array $data): string
    {
        return urldecode($data['result'][0]);
    }

    /**
     * Callback: Sound Progress.
     *
     * @param array $data
     *
     * @return bool|array
     */
    protected function get_sound_progress_callback(array $data)
    {
        return $data['result']['progress'] ?? false;
    }

    private function createPicture_old($data): string
    {
        // see also https://github.com/marcelrv/XiaomiRobotVacuumProtocol/tree/master/RRMapFile
        // Viewer: https://community.openhab.org/t/xiaomi-vacuum-map-viewer-to-find-coordinates-for-zone-cleaning/103500
        // https://github.com/marcelrv/openhab2/commits/276a4cfc0512d9a44d87d43505c01d66561ea65e/bundles/org.openhab.binding.miio/src/main/java/org/openhab/binding/miio/internal/robot/RRMapFileParser.java

        $newImage        = null;
        $multi           = 1;
        $picsize         = 30;
        $divsize         = 50 / $multi;
        $pic_zonen       = 0;
        $pic_toppos      = 0;
        $pic_leftpos     = 0;
        $pic_imageheight = 0;
        $pic_imagewidth  = 0;
        $Log             = [];

        $filedatapos = 0;
        $i           = $filedatapos;
        if ($data[$i++] !== 'r') {
            return '';
        }
        if ($data[$i++] !== 'r') {
            return '';
        }

        $headerlength   = ord($data[$i++]) | (ord($data[$i++]) << 8);
        $locationfooter = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
        $Major          = ord($data[$i++]) | (ord($data[$i++]) << 8);
        $Minor          = ord($data[$i++]) | (ord($data[$i++]) << 8);
        $MapIndex       = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
        $MapSequence    = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
        $filedatapos    = $headerlength;


        $Log['Map'] = ['Version' => $Major . '.' . $Minor, 'Index' => $MapIndex, 'Sequence' => $MapSequence];
        /*
        echo "Filelen        :" . strlen($data) . "\r\n";
        echo "Headerlength   :" . $headerlength . "\r\n";
        echo "Locationfooter :" . $locationfooter . "\r\n";
        echo "Major          :" . $Major . "\r\n";
        echo "Minor          :" . $Minor . "\r\n";
        echo "MapIndex       :" . $MapIndex . "\r\n";
        echo "MapSequence    :" . $MapSequence . "\r\n";
        echo "\r\n";
        */

        $pngStream = '';

        while ($filedatapos < strlen($data)) {
            $i = $filedatapos;
            //  echo $blocktype;
            $blocktype         = ord($data[$i++]) | (ord($data[$i++]) << 8);
            $blockheaderlength = ord($data[$i++]) | (ord($data[$i++]) << 8);
            $blockdatlength    = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            echo sprintf("block type: %s, headerlength: %s, datalength: %s", $blocktype, $blockheaderlength, $blockdatlength) . "\r\n";
            echo 'Header: ' . bin2hex(substr($data, $filedatapos, $headerlength)) . "\r\n";
            //echo "Filedatapos       :" . $filedatapos . "\r\n";

            $filedatapos = $filedatapos + $blockheaderlength + $blockdatlength;

            switch ($blocktype) {
                case 1: //Charger POS
                    $chargerposx = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    $chargerposy = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    echo sprintf("chargerposx: %s, chargerposY: %s", $chargerposx, $chargerposy) . "\r\n";

                    $picchargerposx = (int)(($chargerposx - ($pic_leftpos * $divsize)) / $divsize);
                    $picchargerposy = (int)($pic_imageheight - (($chargerposy - ($pic_toppos * $divsize)) / $divsize));
                    $im1            = imagecreatefromstring(
                        base64_decode(
                            '
            iVBORw0KGgoAAAANSUhEUgAAACoAAAAqCAMAAADyHTlpAAADAFBMVEVHcExF5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5
            o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o7////w/fa69tVm6qL1/vlt66br/PNf6p7+//7o/PGE7rR37ay39dOL77nM+OCh8sbB99n2/vlH5o9e6Z2c8sPk++/j++5s66Vj6qBg6p6i88dn66Nq66VY6Zq99td67a6S8L30/vjb+ul57a2C7rNW6Jiv9M5Q55
            UAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
            AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
            AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
            AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABmUTLUAAAALnRSTlMAJPkvN+wI+4DP1eoxP47t7v4GrpmcB6+Ptj7w+vMJ9Lm11DIlMI0m0DUuM/G06wfbVgAAAfxJREFUeNqNletzmzAMwGVhXg0NTbLkbtl12fvW/f
            //ya7Xy7h82Nruwa5p8yhhgPGAFrCN6VWfbOmHZEtCNkCSMUX/ZZ6S4SvkrneQbETceE66ELY/aLzXo8bru1M5Blw6IWvt9cIa8LmvkOAPbqcJU7wS5yPoJYi55PUs+tBDwmSNuYBSew69MqVRxWIV3R7BEzKySe3VsjTRrUWSNtneInu41vCNhuTZ0X27jb9VBzD+aUm
            YCPvvXyqvb4+1pBuJGv618OrddcjTgoSppLryCtRVqwn0Oqtz037tAp2ZHTKvypMqZ5ohnejJ0UEpGsW1ngR2oxjW6OtJuZGrLsOdtJ/XJGQqukPp9M5NTYJpVNKmLKVMJHnSrB+z3RaXYQ9Zpyhv12i062GXJHH7a1GhAuZjCCvWkIVV6JVrVsmotpOxQMIxbjrNUh+D
            4C9RvcFxB61rgHJqTczUAsKRlsxdDNVrfwp1JPwJEYa6+B0SZkUDr7ayrswYnank9rw0mEtJWUCU/FbI5WXlI7BF5eefBdkpm80ewp0EgjKONWRyUp6qHLlk9b7tbdIlVxFvxhu3nOa/4EwlN7e58F9QctY73S541qSmvPYi6CODd5k84It5e/VCAy7zKFTfArYHMuiQf
            3c79rzHyF/1vVvF0E3TwcHyYJ+496Ypj5P/uAmtfUpJqE0AAAAASUVORK5CYII=
            '
                        )
                    );
                    $im1            = $this->rotate_transparent_img($im1, 0);
                    $r              = $picsize;
                    $im1            = imagescale($im1, $r, $r, IMG_BILINEAR_FIXED);
                    imagecopy($newImage, $im1, $picchargerposx - ($r / 2), $picchargerposy - ($r / 2), 0, 0, $r, $r);
                    $Log['Charger Pos'] = ['X' => $chargerposx, 'Y' => $chargerposy];

                    /*echo "Charger POS\r\n";
                    echo "-----------\r\n";
                    echo "Charger POS X     :".($chargerposx). "\r\n";
                    echo "Charger POS Y     :".($chargerposy). "\r\n";
                    echo "Charger POS X PIC :".$picchargerposx. "\r\n";
                    echo "Charger POS Y PIC :".$picchargerposy. "\r\n";
                    */
                    break;

                case 2: //image
                    $pic_zonen       = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    $pic_toppos      = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    $pic_leftpos     = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    $pic_imageheight = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    $pic_imagewidth  = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    $newImage        = imagecreatetruecolor($pic_imagewidth, $pic_imageheight);
                    for ($y = $pic_imageheight; $y > 0; $y--) {
                        for ($x = 0; $x < $pic_imagewidth; $x++) {
                            $color = null;
                            $pixel = ord($data[$i++]);

                            // Hintergrund
                            if ($pixel == 0) {
                                $color = imagecolorallocate($newImage, 0, 0, 255);
                            } // ist es eine Fläche
                            elseif (($pixel >> 1 & 0x03) == 3) {
                                $zone  = $pixel >> 3;
                                $color = imagecolorallocate($newImage, $zone * 8, 255 - ($zone * 4), 255 - ($zone * 8));
                            } // Wand
                            elseif (($pixel >> 0 & 0x01) == 1) {
                                $color = imagecolorallocate($newImage, 0, 0, 0);
                            } // Hindernis
                            else {
                                $color = imagecolorallocate($newImage, 128, 128, 128);
                            }


                            imagesetpixel($newImage, $x, $y, $color);
                        }
                    }
                    $newImage        = imagescale($newImage, $pic_imagewidth * $multi, $pic_imageheight * $multi, IMG_BILINEAR_FIXED);
                    $pic_toppos      = $pic_toppos * $multi;
                    $pic_leftpos     = $pic_leftpos * $multi;
                    $pic_imageheight = $pic_imageheight * $multi;
                    $pic_imagewidth  = $pic_imagewidth * $multi;
                    /*      echo "Bild\r\n";
                    echo "-----------\r\n";

                    echo "Zonen             :".$pic_zonen. "\r\n";
                    */
                    echo "Toppos            :" . $pic_toppos . "\r\n";
                    echo "Leftpos           :" . $pic_leftpos . "\r\n";
                    echo "Imageheight       :" . $pic_imageheight . "\r\n";
                    echo "Imagewidth        :" . $pic_imagewidth . "\r\n";
                    //*/
                    break;

                case 3: //path
                case 4: //goto path
                case 5: //predicted goto path
                    $pointlengths = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    $pointsize    = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    $angle        = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);

                    $xold = ord($data[$i++]) | (ord($data[$i++]) << 8);
                    $yold = ord($data[$i++]) | (ord($data[$i++]) << 8);

                    for ($pointlength = 1; $pointlength < $pointlengths; $pointlength++) {
                        $x               = ord($data[$i++]) | (ord($data[$i++]) << 8);
                        $y               = ord($data[$i++]) | (ord($data[$i++]) << 8);
                        $pointlengthxold = (int)(($xold - ($pic_leftpos * $divsize)) / $divsize);
                        $pointlengthyold = (int)($pic_imageheight - (($yold - ($pic_toppos * $divsize)) / $divsize));
                        $pointlengthx    = (int)(($x - ($pic_leftpos * $divsize)) / $divsize);
                        $pointlengthy    = (int)($pic_imageheight - (($y - ($pic_toppos * $divsize)) / $divsize));
                        $xold            = $x;
                        $yold            = $y;

                        $color = imagecolorallocate($newImage, 255, 255, 0);
                        imageline($newImage, $pointlengthxold, $pointlengthyold, $pointlengthx, $pointlengthy, $color);
                    }
                    /*echo "Pfad (".$blocktype. ") \r\n";
                    echo "----------\r\n";
                    echo "PointLengths      :".$pointlengths. "\r\n";
                    echo "PointSize         :".$pointsize. "\r\n";
                    echo "Winkel            :".$angle. "\r\n";
                    */
                    break;

                case 6: //currently cleaned zones
                    $counters = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    for ($counter = 0; $counter < $counters; $counter++) {
                        $x1 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                        $y1 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                        $x2 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                        $y2 = ord($data[$i++]) | (ord($data[$i++]) << 8);


                        $x1pic = (int)(($x1 - ($pic_leftpos * $divsize)) / $divsize);
                        $y1pic = (int)($pic_imageheight - (($y1 - ($pic_toppos * $divsize)) / $divsize));
                        $x2pic = (int)(($x2 - ($pic_leftpos * $divsize)) / $divsize);
                        $y2pic = (int)($pic_imageheight - (($y2 - ($pic_toppos * $divsize)) / $divsize));

                        imagefilledrectangle($newImage, $x1pic, $y1pic, $x2pic, $y2pic, imagecolorallocatealpha($newImage, 0, 255, 0, 64));
                        ImageRectangle($newImage, $x1pic, $y1pic, $x2pic, $y2pic, imagecolorallocate($newImage, 0, 255, 0));
                    }
                    /*
                                    echo "Zonen\r\n";
                                    echo "----------\r\n";
                                    echo "Conter      :" . $counter . "\r\n";
                    */
                    break;

                case 7: //Target Position
                    $targetxpos    = ord($data[$i++]) | (ord($data[$i++]) << 8);
                    $targetypos    = ord($data[$i++]) | (ord($data[$i++]) << 8);
                    $pictargetxpos = (int)(($targetxpos - ($pic_leftpos * $divsize)) / $divsize);
                    $pictargetypos = (int)($pic_imageheight - (($targetypos - ($pic_toppos * $divsize)) / $divsize));

                    $coordinates    = [];
                    $coordinates[0] = $pictargetxpos + ($picsize / 2);            // Point 1 x
                    $coordinates[1] = $pictargetypos + ($picsize / 2);          // Point 1 y
                    $coordinates[2] = $pictargetxpos - ($picsize / 2);          // Point 2 x
                    $coordinates[3] = $pictargetypos + ($picsize / 2);          // Point 2 y
                    $coordinates[4] = $pictargetxpos;                      // Point 3 x
                    $coordinates[5] = $pictargetypos - ($picsize / 2);           // Point 3 y


                    ImageFilledPolygon($newImage, $coordinates, 3, ImageColorAllocate($newImage, 255, 255, 0));
                    /*echo "Target Position\r\n";
                    echo "----- ---------\r\n";

                    echo "Target X POS       :".$targetxpos. "\r\n";
                    echo "Target Y POS       :".$targetypos. "\r\n";
                    echo "Target X POS PIC   :".$pictargetxpos. "\r\n";
                    echo "Target Y POS PIC   :".$pictargetypos. "\r\n";
                    */
                    break;

                case 8: //Robot Position
                    $robotxpos    = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    $robotypos    = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    $robotangle   = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    $picrobotxpos = (int)(($robotxpos - ($pic_leftpos * $divsize)) / $divsize);
                    $picrobotypos = (int)($pic_imageheight - (($robotypos - ($pic_toppos * $divsize)) / $divsize));

                    $im = imagecreatefromstring(
                        base64_decode(
                            '
            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
            '
                        )
                    );
                    $im = $this->rotate_transparent_img($im, $robotangle - 90);
                    $r  = $picsize * 2;
                    $im = imagescale($im, $r, $r, IMG_BILINEAR_FIXED);
                    imagecopy($newImage, $im, $picrobotxpos - ($r / 2), $picrobotypos - ($r / 2), 0, 0, $r, $r);
                    /*echo "Robot Position\r\n";
                    echo "--------------\r\n";

                    echo "Robot X POS       :".$robotxpos. "\r\n";
                    echo "Robot Y POS       :".$robotypos. "\r\n";
                    echo "Robot X POS PIC   :".$picrobotxpos. "\r\n";
                    echo "Robot Y POS PIC   :".$picrobotypos. "\r\n";
                    echo "Robot ANGLE       :".$robotangle. "\r\n";
                    */
                    break;

                case 9: //no go areas
                case 12: //mob forbidden area
                case 19: //Carpet forbidden area
                    $counters = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
                    for ($counter = 0; $counter < $counters; $counter++) {
                        $x1 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                        $y1 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                        $x2 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                        $y2 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                        $x3 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                        $y3 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                        $x4 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                        $y4 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                        echo sprintf('%s, %s, %s, %s, %s', __FUNCTION__, $x1, $y1, $x3, $y3) . PHP_EOL;

                        $x1pic = (int)(($x1 - ($pic_leftpos * $divsize)) / $divsize);
                        $y1pic = (int)($pic_imageheight - (($y1 - ($pic_toppos * $divsize)) / $divsize));
                        $x2pic = (int)(($x2 - ($pic_leftpos * $divsize)) / $divsize);
                        $y2pic = (int)($pic_imageheight - (($y2 - ($pic_toppos * $divsize)) / $divsize));
                        $x3pic = (int)(($x3 - ($pic_leftpos * $divsize)) / $divsize);
                        $y3pic = (int)($pic_imageheight - (($y3 - ($pic_toppos * $divsize)) / $divsize));
                        $x4pic = (int)(($x4 - ($pic_leftpos * $divsize)) / $divsize);
                        $y4pic = (int)($pic_imageheight - (($y4 - ($pic_toppos * $divsize)) / $divsize));
                        echo sprintf('%s, %s, %s, %s, %s', __FUNCTION__, $x1pic, $y1pic, $x3pic, $y3pic) . PHP_EOL;

                        imagefilledrectangle($newImage, $x1pic, $y1pic, $x3pic, $y3pic, imagecolorallocatealpha($newImage, 255, 0, 0, 64));
                        ImageRectangle($newImage, $x1pic, $y1pic, $x3pic, $y3pic, imagecolorallocate($newImage, 255, 0, 0));
                    }
                    /*echo "Nogo-Zonen\r\n";
                    echo "----------\r\n";
                    echo "Conter      :".$counter. "\r\n";
                    */
                    break;

                case 10: //Virtual Walls
                case 11: //blocks
                case 13: //Obstacles
                case 15: //Obstacles II
                case 16: //Ignored Obstacles
                case 17: //Carpet Map
                case 18: //Mop path
                case 21: //unknown
                    break;

                case 1024: // check the file of sha1 sum
                    $file     = sha1(substr($data, 0, $i));
                    $filesha1 = bin2hex(substr($data, $i, 20));
                    if ($file == $filesha1) {
                        ob_start();
                        imagepng($newImage, null, 9, PNG_NO_FILTER);      //imagepng() creates a PNG file from the given image.
                        $pngStream = ob_get_clean();
                    }

                    break;

                default:
                    trigger_error('Unknown blocktype: ' . $blocktype, E_USER_NOTICE);
            }
        }

        $this->_debug(__FUNCTION__, 'MapInfo: ' . json_encode($Log));

        return $pngStream;
    }

    private function rotate_transparent_img($img_resource, int $angle)
    {
        $pngTransparency = imagecolorallocatealpha($img_resource, 0, 0, 0, 127);
        imagefill($img_resource, 0, 0, $pngTransparency);

        $result = imagerotate($img_resource, $angle, $pngTransparency);
        imagealphablending($result, true);
        imagesavealpha($result, true);

        return $result;
    }

    private function getUInt16(string $bytes, int $pos)
    {
        $i = $pos;
        return ord($bytes[$i++]) | (ord($bytes[$i++]) << 8);
    }
}

