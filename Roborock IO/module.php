<?php

declare(strict_types=1);

use JetBrains\PhpStorm\NoReturn;

include __DIR__ . '/../libs/picture.php';

/**
 * Class RoborockIO
 * Xiaomi Mi Vacuum Cleaner I/O Device.
 */
class RoborockIO extends IPSModuleStrict
{
    // constants
    private const HELLO_MSG        = '21310020ffffffffffffffffffffffffffffffffffffffffffffffffffffffff';
    private const PORT_UDP         = 54321;
    private const TIMEOUT_SEND     = 5; //Timeout von 2 ist beim Befehl 'load_multi_map' zu klein
    private const TIMEOUT_DISCOVER = 5;

    // private properties
    private string $token;

    private string $ip;

    private \Socket|null  $socket = null;

    private int    $attempts      = 0;

    private int    $time_diff     = 0;

    private bool   $first_request = true;

    private string $magic         = '2131';

    private string $length        = '';

    private string $unknown1      = '00000000';

    private string $devicetype    = '';

    private string $serial        = '';

    private string $timestamp     = '';

    private string $checksum      = '';

    private string $key           = '';

    private string $iv            = '';

    /**
     * close socket on destruction.
     */
    public function __destruct()
    {
        if ($this->socket) {
            socket_close($this->socket);
        }
    }

    /**
     * create instance.
     *
     */
    public function Create(): void
    {
        parent::Create();

        // initiate buffer
        $this->SetBuffer('message_id', '0');
        $this->SetBuffer('queue', '[]');

        // register timer
        $this->RegisterTimer('RoborockQueue', 0, sprintf('IPS_RequestAction(%d, \'HandleQueue\', 0);', $this->InstanceID));

        //we will wait until the kernel is ready
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

    }

    /**
     * apply changes from the configuration form.
     *
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        // register webhook via native IPSModuleStrict handling
        $this->RegisterHook('Roborock');

        $this->SetTimerInterval('RoborockQueue', 200);

        $this->SetStatus(IS_ACTIVE);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if (($Message === IPS_KERNELMESSAGE) && ($Data[0] === KR_READY)) {
            $this->ApplyChanges();
        }
    }

    /**
     * receive from children.
     *
     * @param string $JSONString
     *
     * @return string json data
     * @throws \JsonException
     */
    public function ForwardData(string $JSONString): string
    {
        // receive data
        $data    = json_decode($JSONString, false, 512, JSON_THROW_ON_ERROR);
        $payload = $data->Buffer;

        // debug log
        $this->_debug('forwarded data', json_encode($data->Buffer, JSON_THROW_ON_ERROR));

        // return immediately on discovery request
        if ($payload->method === 'discover') {
            return json_encode($this->Discover($payload->ip), JSON_THROW_ON_ERROR);
        }

        // send & receive command immediately
        if ($payload->immediate) {
            $result = $this->Send($payload);
            if (is_array($result) && isset($payload->request_id)) {
                $result['request_id'] = $payload->request_id;
            }
            return json_encode($result, JSON_THROW_ON_ERROR);
        }

        // otherwise, append to queue
        $queue   = json_decode($this->GetBuffer('queue'), false, 512, JSON_THROW_ON_ERROR);
        $queue[] = $payload;

        // save queue
        $this->SetBuffer('queue', json_encode($queue, JSON_THROW_ON_ERROR));

        return '';
    }

    public function GetConfigurationForm(): string
    {
        $form = [
            'elements' => [
                [
                    'type'  => 'Label',
                    'label' => 'Roborock I/O'
                ]
            ]
        ];

        return json_encode($form, JSON_THROW_ON_ERROR);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($Ident === 'HandleQueue') {
            $this->HandleQueue();
        } else {
            trigger_error('Unexpected Ident: ' . $Ident, E_USER_ERROR);
        }
    }

    /**
     * Queue Handler.
     *
     * @return void
     * @throws \JsonException
     */
    private function HandleQueue(): void
    {
        if (!$this->isQueueProcessingPossible()) {
            return;
        }

        // get current queue
        $queueBuffer = $this->GetBuffer('queue');
        if (!is_string($queueBuffer)) {
            return;
        }

        try {
            $queue = json_decode($queueBuffer, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->_debug(__FUNCTION__, 'Invalid queue JSON, resetting queue: ' . $exception->getMessage());
            $this->SetBuffer('queue', '[]');
            return;
        }

        if (!is_array($queue)) {
            return;
        }

        if ($queue) {
            // reset queue
            $this->SetBuffer('queue', '[]');

            // loop queue
            foreach ($queue as $item) {
                if (!$this->isQueueProcessingPossible()) {
                    return;
                }
                if (!is_object($item) || !isset($item->InstanceID, $item->method)) {
                    continue;
                }

                // short timeout
                IPS_Sleep(100);

                // receive data
                $buffer = $this->Send($item);

                // break loop on invalid requests
                if (!$buffer) {
                    return;
                }

                // append data
                if (is_array($buffer)) {
                    $buffer['method'] = $item->method;
                    $buffer['token']  = $this->token;
                    if (isset($item->request_id)) {
                        $buffer['request_id'] = $item->request_id;
                    }
                }

                // send it to children
                $this->SendDataToChildren(
                    json_encode([
                        'DataID'     => '{36FF43CE-F065-DD20-F1A8-A7C99C25D7A2}',
                        'InstanceID' => (int)$item->InstanceID,
                        'Buffer'     => $buffer
                    ],
                        JSON_THROW_ON_ERROR)
                );
            }
        }
    }

    private function isQueueProcessingPossible(): bool
    {
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return false;
        }

        if ($this->GetStatus() !== IS_ACTIVE){
            return false;
        }

        if (!$this->HasActiveParent()){
            return false;
        }

        return true;
    }

    /**
     * send a command and receive a response.
     *
     *
     * @param object $payload
     *
     * @return array|bool|string|null json data
     * @throws \JsonException
     */
    private function Send(object $payload): array|bool|string|null
    {
        $this->attempts++;

        // parse data
        $this->token = $this->_validateToken($payload->token);
        $this->ip    = $payload->ip;
        $message     = [
            'method' => $payload->method,
            'params' => $payload->params
        ];

        // remove params, when empty
        if (!$message['params']) {
            unset($message['params']);
        }

        // debug log
        if ($this->first_request) {
            $this->_debug('token used', $this->token);
        }

        // proceed on valid token
        // send HELLO
        if ($this->token && $this->SendHello('')) {
            // set message id
            $messageId     = $this->_getMessageId();
            $message['id'] = $messageId;

            $this->_debug('socket [message]', json_encode($message, JSON_THROW_ON_ERROR));

            // build message
            $packet = hex2bin($this->_buildMessage($message));
            $this->_debug('socket [packet]', $packet, 1);

            // send a message to socket
            if ($bytes = socket_sendto($this->socket, $packet, strlen($packet), 0, $this->ip, self::PORT_UDP)) {
                $this->_debug('socket [send]', $bytes . ' bytes');
            } else {
                $this->SocketErrorHandler();
            }

            // receive data from socket
            $buffer = '';
            if (($bytes = @socket_recvfrom($this->socket, $buffer, 4096, 0, $remote_ip, $remote_port)) !== false) {
                $this->_debug('socket [receive]', $bytes . ' bytes from ' . $remote_ip . ':' . $remote_port);

                // parse message
                $message = $this->_parseMessage(bin2hex($buffer));

                // decrypt data
                $data_decrypted = $this->_decrypt($message);

                // validate json response
                if (($data_decrypted !== false) && $result = $this->_validateResponse($data_decrypted, $messageId)) {
                    $this->SendDebug('socket [result]', json_encode($result, JSON_THROW_ON_ERROR), 0);
                    $this->attempts = 0;
                    return $result;
                }

                if ($this->attempts < 3) {
                    return $this->Retry($payload);
                } // on invalid response, retry an attempt

                return false;
            }

            if ($this->attempts === 1) {
                return $this->Retry($payload);
            }

            $this->SocketErrorHandler();
        }

        return false;
    }

    /**
     * Send commands and forward response to children.
     *
     * @param int          $instance_id
     * @param array|string $data
     * @param bool         $doTimeout
     *
     * @return void
     * @throws \JsonException
     */
    private function SendData(int $instance_id, array|string $data, bool $doTimeout = true): void
    {
        // get instance settings
        $token = IPS_GetProperty($instance_id, 'token');
        $ip    = IPS_GetProperty($instance_id, 'ip');

        // check $data
        if (!is_array($data)) {
            $data = [
                'method' => $data
            ];
        }

        // build payload
        $payload = [
            'token'     => $token,
            'ip'        => $ip,
            'method'    => $data['method'],
            'params'    => $data['params'] ?? [],
            'immediate' => true
        ];

        $payload = (object)$payload;

        // send command to robot
        $buffer = $this->Send($payload);

        // append data
        if (is_array($buffer)) {
            $buffer['method'] = $payload->method;
            $buffer['token']  = $payload->token;
        }

        // send buffer to children
        $this->SendDataToChildren(
            json_encode([
                'DataID'     => '{36FF43CE-F065-DD20-F1A8-A7C99C25D7A2}',
                'InstanceID' => $instance_id,
                'Buffer'     => $buffer
            ],
                JSON_THROW_ON_ERROR)
        );

        // sleep on specific commands
        if ($doTimeout && (in_array($payload->method, ['app_rc_start', 'app_rc_move']))) {
            IPS_Sleep(500);
        }
    }

    /**
     * Retry Message Send.
     *
     * @param object $payload
     *
     * @return array|bool|false[]|string|string[]|null
     * @throws \JsonException
     */
    private function Retry(object $payload): array|bool|string|null
    {
        IPS_Sleep(1000);
        $this->_increaseMessageId(100);
        $this->_debug('socket [response]', 'invalid response, retrying request...');
        return $this->Send($payload);
    }

    /**
     * send HELLO message initially.
     *
     * @param string $discover_ip
     *
     * @return string|bool
     * @throws \JsonException
     */
    protected function SendHello(string $discover_ip):bool|string
    {
        // check if a hello message was already sent
        if (!$this->first_request && !$discover_ip) {
            return true;
        }

        // get ip
        $ip = $discover_ip ? : $this->ip;

        // create socket
        $this->SocketCreate();

        // send timeout
        $this->SocketSetTimeout($discover_ip ? self::TIMEOUT_DISCOVER : self::TIMEOUT_SEND);

        // initiate HELLO message
        $this->_debug('socket [HELLO]', $ip);

        // build hello message
        $hello_packet = hex2bin(self::HELLO_MSG);

        // send a hello message
        if ($bytes = socket_sendto($this->socket, $hello_packet, strlen($hello_packet), 0, $ip, self::PORT_UDP)) {
            $this->_debug(($discover_ip ? 'discover' : 'socket') . ' [response]', $bytes . ' bytes sent to ' . $ip . ':' . self::PORT_UDP);
        } else {
            $this->SocketErrorHandler();
        }

        // receive response
        $buffer = '';
        if (($bytes = @socket_recvfrom($this->socket, $buffer, 4096, 0, $remote_ip, $remote_port)) !== false) {
            $this->_debug(($discover_ip ? 'discover' : 'socket') . ' [response]', $bytes . ' bytes received from ' . $remote_ip . ':' . $remote_port);

            // parse message
            $message = bin2hex($buffer);
            $hello   = $this->_parseMessage($message);

            if ($hello) {
                $this->_debug('socket [HELLO]', json_encode($hello, JSON_THROW_ON_ERROR));
            }

            // return HELLO message on discovery
            if ($discover_ip) {
                return $hello;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Discover device and get token.
     *
     * @param string $ip
     *
     * @return bool|array|string
     * @throws \JsonException
     */
    protected function Discover(string $ip): bool|array|string
    {
        // send HELLO and retrieve token
        if ($discover = $this->SendHello($ip)) {
            return $discover['token'] ?? false;
        }

        return false;
    }

    /**
     * creates an udp socket.
     */
    private function SocketCreate(): void
    {
        /** do nothing if the socket was already created */
        if ($this->socket) {
            $this->_debug('socket [instance]', 'already created');
        } /** create socket */ elseif ($this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
            $this->_debug('socket [instance]', 'created');
        } /** error handling */ else {
            $this->SocketErrorHandler();
        }
    }

    /**
     * sends a reception timeout to the socket.
     *
     * @param int $timeout
     */
    protected function SocketSetTimeout(int $timeout = 2): void
    {
        if (socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $timeout, 'usec' => 0])) {
            $this->_debug('socket [settings]', 'set timeout to ' . $timeout . 's');
        } else {
            $this->SocketErrorHandler();
        }
    }

    /**
     * handles socket error messages.
     */
    #[NoReturn]
    private function SocketErrorHandler(): void
    {
        $error_code = socket_last_error();
        $error_msg  = socket_strerror($error_code);

        $this->_debug('socket [error]', $error_code . ' message: ' . $error_msg);
        exit(-1);
    }

    /***********************************************************
     * Helper methods
     ***********************************************************/

    /**
     * validate token length.
     *
     * @param string $token
     *
     * @return string
     */
    private function _validateToken(string $token = ''): string
    {
        // validate token length
        if (strlen($token) === 32) {
            // set encryption key
            $this->key = md5(hex2bin($token));

            // set encryption vector
            $this->iv = md5(hex2bin($this->key . $token));

            // return token
            return $token;
        }

        return '';
    }

    /**
     * validate json response.
     *
     * @param string $json
     * @param int    $MessageId
     *
     * @return array|bool|mixed|string
     * @throws \JsonException
     */
    private function _validateResponse(string $json, int $MessageId): mixed
    {
        $result = false;
        $data   = @json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        // validate json
        if (($jsonErrCode = json_last_error()) !== JSON_ERROR_NONE) {
            $jsonErrMsg = json_last_error_msg();
            $this->_debug('data', 'json is not valid. Error: ' . $jsonErrMsg);
            if ($jsonErrCode === JSON_ERROR_CTRL_CHAR) {
                // handle control chars
                $this->_debug('data', 'trim()...');
                $result = @json_decode(trim($json), true, 512, JSON_THROW_ON_ERROR);
            }
        } else {
            $result = $data;
        }

        // validate result
        if (!isset($result['result'])) {
            $this->_debug('data [error]', json_encode($result, JSON_THROW_ON_ERROR));
            $result = [
                'error' => $result
            ];
        } elseif (empty($result)) {
            $this->_debug('data [error]', 'no json received');
            $result = [
                'error' => 'no json data received!'
            ];
        } elseif ($result['id'] !== $MessageId) {
            $this->_debug('data [error]', sprintf('Message Id not correct. Expected: %s, Received: %s', $MessageId, $result['id']));
            $result = [
                'error' => $result
            ];
        }

        return $result;
    }

    /**
     * generate almost unique message id.
     *
     * @return int
     */
    private function _getMessageId(): int
    {
        return $this->_increaseMessageId();
    }

    /**
     * increment message id.
     *
     * @param int $delta
     *
     * @return int
     */
    private function _increaseMessageId(int $delta = 1): int
    {
        // read last message id
        $message_id = (int)$this->GetBuffer('message_id');

        // increment by $delta
        $message_id += $delta;

        // if message id is 9999, reset to 0
        if ($message_id >= 9999) {
            $message_id = 0;
        }

        // save to buffer
        $this->SetBuffer('message_id', (string)$message_id);

        // return message id
        return $message_id;
    }

    /**
     * build socket message.
     *
     * @param $command
     *
     * @return string
     * @throws \JsonException
     */
    private function _buildMessage($command): string
    {
        if (!is_string($command)) {
            $command = json_encode($command, JSON_THROW_ON_ERROR);
        }

        $data            = $this->_encrypt($command);
        $this->length    = sprintf('%04x', strlen($data) / 2 + 32);
        $this->timestamp = sprintf('%08x', time() + $this->time_diff);
        $packet          =
            $this->magic . $this->length . $this->unknown1 . $this->devicetype . $this->serial . $this->timestamp . $this->token . $data;
        $this->checksum  = md5(hex2bin($packet));

        return $this->magic . $this->length . $this->unknown1 . $this->devicetype . $this->serial . $this->timestamp . $this->checksum . $data;
    }

    /**
     * parse socket message.
     *
     * @param string $message
     *
     * @return string
     */
    private function _parseMessage(string $message): string
    {
        $this->magic      = substr($message, 0, 4);
        $this->length     = substr($message, 4, 4);
        $this->unknown1   = substr($message, 8, 8);
        $this->devicetype = substr($message, 16, 4);
        $this->serial     = substr($message, 20, 4);
        $this->timestamp  = substr($message, 24, 8);
        $this->checksum   = substr($message, 32, 32);

        // retrieve token
        if (($this->length === '0020') && ((strlen($message) / 2) === 32)) {
            // get a new token
            $tmp_token = $this->_validateToken(substr($message, 32, 32));

            // set new token, if valid
            if (stripos($tmp_token, 'fffffffff') === false) {
                $this->token = $tmp_token;
            }

            // calculate time diff between client and server
            $time_diff = hexdec($this->timestamp) - time();

            if ($this->first_request && $time_diff !== 0) {
                $this->time_diff = $time_diff;
            }

            $this->first_request = false;
        } // get data
        else {
            $data_length = strlen($message) - 64;
            if ($data_length > 0) {
                return substr($message, 64, $data_length);
            }
        }

        return '';
    }

    /**
     * encrypt data.
     *
     * @param $data
     *
     * @return string
     */
    private function _encrypt($data): string
    {
        return bin2hex(openssl_encrypt($data, 'AES-128-CBC', hex2bin($this->key), OPENSSL_RAW_DATA, hex2bin($this->iv)));
    }

    /**
     * decrypt data.
     *
     * @param string $data
     *
     * @return false|string
     */
    private function _decrypt(string $data): false|string
    {
        if (!$ret = openssl_decrypt(hex2bin($data), 'AES-128-CBC', hex2bin($this->key), OPENSSL_RAW_DATA, hex2bin($this->iv))) {
            $this->_Debug(
                __FUNCTION__,
                sprintf('Data could not be decrypted. Data: %s, algo: AES-128-CBC, key: %s, iv: %s', $data, $this->key, $this->iv)
            );
            return false;
        }
        return trim($ret);
    }

    /**
     * send debug log.
     *
     * @param string $notification
     * @param string $message
     * @param int    $format 0 = Text, 1 = Hex
     */
    private function _debug(string $notification = '', string $message = '', int $format = 0): void
    {
        $this->SendDebug($notification, $message, $format);
    }

    /**
     * Process Webhook Data (Joystick.html or symcon.mapupload.sh
     */
    protected function ProcessHookData(): void
    {
        // set defaults
        $cmd         = $_GET['cmd'] ?? '';
        $instance_id = $_GET['id'] ?? '';

        // check instance id
        if (empty($instance_id)) {
            die('Instance ID Missing ($_GET[\'id\'])');
        }

        if (!@IPS_GetObject((int)$instance_id)) {
            die('Instance ID ' . $instance_id . ' does not exist!');
        }

        // handle commands
        if ($cmd === 'remote') {
            $rotation = (float)($_GET['rotation'] ?? 0);
            $speed    = (float)($_GET['speed'] ?? 0.1);
            $start    = isset($_GET['start']);
            $end      = isset($_GET['end']);

            if ($start) {
                $this->SendData(
                    (int)$instance_id,
                    'app_rc_start'
                );
            } elseif ($end) {
                $this->SendData(
                    (int)$instance_id,
                    'app_rc_end'
                );
            } elseif ($rotation) {
                $this->SendData(
                    (int)$instance_id,
                    'app_rc_start',
                    false
                );

                $this->SendData(
                    (int)$instance_id, [
                        'method' => 'app_rc_move',
                        'params' => [
                            [
                                'omega'    => $rotation,
                                'velocity' => $speed,
                                'seqnum'   => 1,
                                'duration' => 1000
                            ]
                        ]
                    ]
                );
            }
        } else {
            // upload map
            if (!isset($_FILES['image']['tmp_name']) || $_FILES['image']['name'] !== 'latest.png' || !file_exists($_FILES['image']['tmp_name'])) {
                die('Image missing!');
            }
            if (!isset($_FILES['coordinates']['tmp_name']) || !file_exists($_FILES['coordinates']['tmp_name'])) {
                die('Coordinates missing!');
            }

            // validate uploaded image
            if ($im = @imagecreatefrompng($_FILES['image']['tmp_name'])) {
                // define transparent color
                $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);

                // get image size
                $s        = getimagesize($_FILES['image']['tmp_name']);
                $center_x = ($s[0] / 2);
                $center_y = ($s[1] / 2);

                // rotate image by -90°
                $im = imagerotate($im, -90, $transparent, true);

                // convert coordinates from file to points
                $x     = 0;
                $y     = 0;
                $img_x = 0;
                $img_y = 0;
                foreach (file($_FILES['coordinates']['tmp_name']) as $line) {
                    if (str_contains($line, 'estimate')) {
                        $d = explode('estimate', $line);
                        $d = trim($d[1]);

                        [$y, $x] = explode(' ', $d, 3);

                        // calculate pixel from the center of the image, with offset
                        $img_x = $center_x + ($x * 20);
                        $img_y = $center_y + ($y * 20);

                        // draw pixel to image
                        imagesetpixel($im, $img_x, $img_y, imagecolorallocate($im, 125, 125, 125));
                    }
                }

                // draw current position
                imagefilledellipse($im, $img_x, $img_y - 3, 8, 8, imagecolorallocate($im, 220, 0, 0));

                // rotate image back by 90°
                $im = imagerotate($im, 90, $transparent, true);

                // save transparency
                imagesavealpha($im, true);

                // save image
                imagepng($im, $_FILES['image']['tmp_name']);
                imagedestroy($im);

                // reopen image
                $im = @imagecreatefrompng($_FILES['image']['tmp_name']);

                // crop background
                if ($cropped = imagecropauto($im)) {
                    imagepng($cropped, $_FILES['image']['tmp_name']);
                    imagedestroy($cropped);
                }

                imagedestroy($im);

                // create media image, if not exists
                $media_file = 'Map.' . $instance_id . '.png';

                if (!$media_id = @IPS_GetMediaIDByFile($media_file)) {
                    $media_id = IPS_CreateMedia(1);
                    IPS_SetName($media_id, $this->Translate('Map'));
                }

                // move to instance
                IPS_SetParent($media_id, (int)$instance_id);

                // update media content
                IPS_SetMediaFile($media_id, $media_file, false);
                IPS_SetMediaContent($media_id, base64_encode(file_get_contents($_FILES['image']['tmp_name'])));

                // send coordinates to children
                if ($x && $y) {
                    $this->SendDataToChildren(
                        json_encode([
                            'DataID'     => '{36FF43CE-F065-DD20-F1A8-A7C99C25D7A2}',
                            'InstanceID' => (int)$instance_id,
                            'Buffer'     => [
                                'token'  => false,
                                'method' => 'coordinates',
                                'x'      => $x,
                                'y'      => $y
                            ]
                        ],
                            JSON_THROW_ON_ERROR)
                    );
                }
            }

            // unlink temp files
            unlink($_FILES['image']['tmp_name']);
            unlink($_FILES['coordinates']['tmp_name']);
        }
    }

}
