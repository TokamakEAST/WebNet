<?php

class phpMQTT
{
    protected $socket;            /* holds the socket	*/
    protected $msgid = 1;            /* counter for message id */
    public $keepalive = 10;        /* default keepalive timmer */
    public $timesinceping;        /* host unix time, used to detect disconects */
    public $topics = [];    /* used to store currently subscribed topics */
    public $debug = false;        /* should output debug messages */
    public $address;            /* broker address */
    public $port;                /* broker port */
    public $clientid;            /* client id sent to brocker */
    public $will;                /* stores the will of the client */
    protected $username;            /* stores username */
    protected $password;            /* stores password */

    public $cafile;
    protected static $known_commands = [
        1 => 'CONNECT',
        2 => 'CONNACK',
        3 => 'PUBLISH',
        4 => 'PUBACK',
        5 => 'PUBREC',
        6 => 'PUBREL',
        7 => 'PUBCOMP',
        8 => 'SUBSCRIBE',
        9 => 'SUBACK',
        10 => 'UNSUBSCRIBE',
        11 => 'UNSUBACK',
        12 => 'PINGREQ',
        13 => 'PINGRESP',
        14 => 'DISCONNECT'
    ];

    public function __construct($address, $port, $clientid, $cafile = null)
    {
        $this->broker($address, $port, $clientid, $cafile);
    }

    public function broker($address, $port, $clientid, $cafile = null): void
    {
        $this->address = $address;
        $this->port = $port;
        $this->clientid = $clientid;
        $this->cafile = $cafile;
    }

    public function connect_auto($clean = true, $will = null, $username = null, $password = null): bool
    {
        while ($this->connect($clean, $will, $username, $password) === false) {
            sleep(10);
        }
        return true;
    }

    public function connect($clean = true, $will = null, $username = null, $password = null): bool
    {
        if ($will) {
            $this->will = $will;
        }
        if ($username) {
            $this->username = $username;
        }
        if ($password) {
            $this->password = $password;
        }

        if ($this->cafile) {
            $socketContext = stream_context_create(
                [
                    'ssl' => [
                        'verify_peer_name' => true,
                        'cafile' => $this->cafile
                    ]
                ]
            );
            $this->socket = stream_socket_client('tls://' . $this->address . ':' . $this->port, $errno, $errstr, 60, STREAM_CLIENT_CONNECT, $socketContext);
        } else {
            $this->socket = stream_socket_client('tcp://' . $this->address . ':' . $this->port, $errno, $errstr, 60, STREAM_CLIENT_CONNECT);
        }

        if (!$this->socket) {
            $this->_errorMessage("stream_socket_create() $errno, $errstr");
            return false;
        }

        stream_set_timeout($this->socket, 5);
        stream_set_blocking($this->socket, 0);

        $i = 0;
        $buffer = '';

        $buffer .= chr(0x00);
        $i++; // Length MSB
        $buffer .= chr(0x04);
        $i++; // Length LSB
        $buffer .= chr(0x4d);
        $i++; // M
        $buffer .= chr(0x51);
        $i++; // Q
        $buffer .= chr(0x54);
        $i++; // T
        $buffer .= chr(0x54);
        $i++; // T
        $buffer .= chr(0x04);
        $i++; // // Protocol Level

        //No Will
        $var = 0;
        if ($clean) {
            $var += 2;
        }

        //Add will info to header
        if ($this->will !== null) {
            $var += 4; // Set will flag
            $var += ($this->will['qos'] << 3); //Set will qos
            if ($this->will['retain']) {
                $var += 32;
            } //Set will retain
        }

        if ($this->username !== null) {
            $var += 128;
        }    //Add username to header
        if ($this->password !== null) {
            $var += 64;
        }    //Add password to header

        $buffer .= chr($var);
        $i++;

        //Keep alive
        $buffer .= chr($this->keepalive >> 8);
        $i++;
        $buffer .= chr($this->keepalive & 0xff);
        $i++;

        $buffer .= $this->strwritestring($this->clientid, $i);

        //Adding will to payload
        if ($this->will !== null) {
            $buffer .= $this->strwritestring($this->will['topic'], $i);
            $buffer .= $this->strwritestring($this->will['content'], $i);
        }

        if ($this->username !== null) {
            $buffer .= $this->strwritestring($this->username, $i);
        }
        if ($this->password !== null) {
            $buffer .= $this->strwritestring($this->password, $i);
        }

        $head = chr(0x10);

        while ($i > 0) {
            $encodedByte = $i % 128;
            $i /= 128;
            $i = (int)$i;
            if ($i > 0) {
                $encodedByte |= 128;
            }
            $head .= chr($encodedByte);
        }

        fwrite($this->socket, $head, 2);
        fwrite($this->socket, $buffer);

        $string = $this->read(4);

        if (ord($string{0}) >> 4 === 2 && $string{3} === chr(0)) {
            $this->_debugMessage('Connected to Broker');
        } else {
            $this->_errorMessage(
                sprintf(
                    "Connection failed! (Error: 0x%02x 0x%02x)\n",
                    ord($string{0}),
                    ord($string{3})
                )
            );
            return false;
        }

        $this->timesinceping = time();

        return true;
    }

    public function read($int = 8192, $nb = false)
    {
        $string = '';
        $togo = $int;

        if ($nb) {
            return fread($this->socket, $togo);
        }

        while (!feof($this->socket) && $togo > 0) {
            $fread = fread($this->socket, $togo);
            $string .= $fread;
            $togo = $int - strlen($string);
        }

        return $string;
    }

    public function subscribeAndWaitForMessage($topic, $qos): string
    {
        $this->subscribe(
            [
                $topic => [
                    'qos' => $qos,
                    'function' => '__direct_return_message__'
                ]
            ]
        );

        do {
            $return = $this->proc();
        } while ($return === true);

        return $return;
    }

    public function subscribe($topics, $qos = 0): void
    {
        $i = 0;
        $buffer = '';
        $id = $this->msgid;
        $buffer .= chr($id >> 8);
        $i++;
        $buffer .= chr($id % 256);
        $i++;

        foreach ($topics as $key => $topic) {
            $buffer .= $this->strwritestring($key, $i);
            $buffer .= chr($topic['qos']);
            $i++;
            $this->topics[$key] = $topic;
        }

        $cmd = 0x82;
        //$qos
        $cmd += ($qos << 1);

        $head = chr($cmd);
        $head .= $this->setmsglength($i);
        fwrite($this->socket, $head, strlen($head));

        $this->_fwrite($buffer);
        $string = $this->read(2);

        $bytes = ord(substr($string, 1, 1));
        $this->read($bytes);
    }

    /**
     * Sends a keep alive ping
     */
    public function ping(): void
    {
        $head = chr(0xc0);
        $head .= chr(0x00);
        fwrite($this->socket, $head, 2);
        $this->timesinceping = time();
        $this->_debugMessage('ping sent');
    }

    /**
     *  sends a proper disconnect cmd
     */
    public function disconnect(): void
    {
        $head = ' ';
        $head{0} = chr(0xe0);
        $head{1} = chr(0x00);
        fwrite($this->socket, $head, 2);
    }

    /**
     * Sends a proper disconnect, then closes the socket
     */
    public function close(): void
    {
        $this->disconnect();
        stream_socket_shutdown($this->socket, STREAM_SHUT_WR);
    }

    /**
     * Publishes $content on a $topic
     *
     * @param $topic
     * @param $content
     * @param int $qos
     * @param bool $retain
     */
    public function publish($topic, $content, $qos = 0, $retain = false): void
    {
        $i = 0;
        $buffer = '';

        $buffer .= $this->strwritestring($topic, $i);

        if ($qos) {
            $id = $this->msgid++;
            $buffer .= chr($id >> 8);
            $i++;
            $buffer .= chr($id % 256);
            $i++;
        }

        $buffer .= $content;
        $i += strlen($content);

        $head = ' ';
        $cmd = 0x30;
        if ($qos) {
            $cmd += $qos << 1;
        }
        if (empty($retain) === false) {
            ++$cmd;
        }

        $head{0} = chr($cmd);
        $head .= $this->setmsglength($i);

        fwrite($this->socket, $head, strlen($head));
        $this->_fwrite($buffer);
    }

    /**
     * Writes a string to the socket
     *
     * @param $buffer
     *
     * @return bool|int
     */
    protected function _fwrite($buffer)
    {
        $buffer_length = strlen($buffer);
        for ($written = 0; $written < $buffer_length; $written += $fwrite) {
            $fwrite = fwrite($this->socket, substr($buffer, $written));
            if ($fwrite === false) {
                return false;
            }
        }
        return $buffer_length;
    }

    /**
     * Processes a received topic
     *
     * @param $msg
     *
     * @retrun bool|string
     */
    public function message($msg)
    {
        $tlen = (ord($msg{0}) << 8) + ord($msg{1});
        $topic = substr($msg, 2, $tlen);
        $msg = substr($msg, ($tlen + 2));
        $found = false;
        foreach ($this->topics as $key => $top) {
            if (preg_match(
                '/^' . str_replace(
                    '#',
                    '.*',
                    str_replace(
                        '+',
                        "[^\/]*",
                        str_replace(
                            '/',
                            "\/",
                            str_replace(
                                '$',
                                '\$',
                                $key
                            )
                        )
                    )
                ) . '$/',
                $topic
            )) {
                if ($top['function'] === '__direct_return_message__') {
                    return $msg;
                }

                if (is_callable($top['function'])) {
                    call_user_func($top['function'], $topic, $msg);
                } else {
                    $this->_errorMessage('Message received on topic ' . $topic. ' but function is not callable.');
                }
            }
        }

        if ($found === false) {
            $this->_debugMessage('msg received but no match in subscriptions');
        }

        return $found;
    }

    /**
     * The processing loop for an "always on" client
     * set true when you are doing other stuff in the loop good for
     * watching something else at the same time
     *
     * @param bool $loop
     *
     * @return bool | string
     */
    public function proc(bool $loop = true)
    {
        if (feof($this->socket)) {
            $this->_debugMessage('eof receive going to reconnect for good measure');
            fclose($this->socket);
            $this->connect_auto(false);
            if (count($this->topics)) {
                $this->subscribe($this->topics);
            }
        }

        $byte = $this->read(1, true);

        if ((string)$byte === '') {
            if ($loop === true) {
                usleep(100000);
            }
        } else {
            $cmd = (int)(ord($byte) / 16);
            $this->_debugMessage(
                sprintf(
                    'Received CMD: %d (%s)',
                    $cmd,
                    isset(static::$known_commands[$cmd]) === true ? static::$known_commands[$cmd] : 'Unknown'
                )
            );

            $multiplier = 1;
            $value = 0;
            do {
                $digit = ord($this->read(1));
                $value += ($digit & 127) * $multiplier;
                $multiplier *= 128;
            } while (($digit & 128) !== 0);

            $this->_debugMessage('Fetching: ' . $value . ' bytes');

            $string = $value > 0 ? $this->read($value) : '';

            if ($cmd) {
                switch ($cmd) {
                    case 3: //Publish MSG
                        $return = $this->message($string);
                        if (is_bool($return) === false) {
                            return $return;
                        }
                        break;
                }
            }
        }

        if ($this->timesinceping < (time() - $this->keepalive)) {
            $this->_debugMessage('not had something in a while so ping');
            $this->ping();
        }

        if ($this->timesinceping < (time() - ($this->keepalive * 2))) {
            $this->_debugMessage('not seen a packet in a while, disconnecting/reconnecting');
            fclose($this->socket);
            $this->connect_auto(false);
            if (count($this->topics)) {
                $this->subscribe($this->topics);
            }
        }

        return true;
    }

    /**
     * Gets the length of a msg, (and increments $i)
     *
     * @param $msg
     * @param $i
     *
     * @return float|int
     */
    protected function getmsglength(&$msg, &$i)
    {
        $multiplier = 1;
        $value = 0;
        do {
            $digit = ord($msg{$i});
            $value += ($digit & 127) * $multiplier;
            $multiplier *= 128;
            $i++;
        } while (($digit & 128) !== 0);

        return $value;
    }

    /**
     * @param $len
     *
     * @return string
     */
    protected function setmsglength($len): string
    {
        $string = '';
        do {
            $digit = $len % 128;
            $len >>= 7;
            // if there are more digits to encode, set the top bit of this digit
            if ($len > 0) {
                $digit |= 0x80;
            }
            $string .= chr($digit);
        } while ($len > 0);
        return $string;
    }

    /**
     * @param $str
     * @param $i
     *
     * @return string
     */
    protected function strwritestring($str, &$i): string
    {
        $len = strlen($str);
        $msb = $len >> 8;
        $lsb = $len % 256;
        $ret = chr($msb);
        $ret .= chr($lsb);
        $ret .= $str;
        $i += ($len + 2);
        return $ret;
    }

    /**
     * Prints a sting out character by character
     *
     * @param $string
     */
    public function printstr($string): void
    {
        $strlen = strlen($string);
        for ($j = 0; $j < $strlen; $j++) {
            $num = ord($string{$j});
            if ($num > 31) {
                $chr = $string{$j};
            } else {
                $chr = ' ';
            }
            printf("%4d: %08b : 0x%02x : %s \n", $j, $num, $num, $chr);
        }
    }

    /**
     * @param string $message
     */
    protected function _debugMessage(string $message): void
    {
        if ($this->debug === true) {
            echo date('r: ') . $message . PHP_EOL;
        }
    }

    /**
     * @param string $message
     */
    protected function _errorMessage(string $message): void
    {
        error_log('Error:' . $message);
    }
}

$server    = '121.89.187.31';     // 服务器IP
$port      = 1883;            // 服务器端口
$username = 'TEMP';                   // set your username
$password = '654321';                   // set your password
$client_id = 'TEMP_001'; // make sure this is unique for connecting to sever - you could use uniqid()

$mqtt = new phpMQTT($server, $port, $client_id);
if(!$mqtt->connect(true, NULL, $username, $password)) {
	exit(1);
}

$mqtt->debug = true;

$topics['temp'] = array('qos' => 0, 'function' => 'procMsg');

$mqtt->subscribe($topics, 0);

while($mqtt->proc()) {

}

$mqtt->close();

function procMsg($topic, $msg){
		echo 'Msg Recieved: ' . date('r') . "\n";
		echo "Topic: {$topic}\n\n";
		echo "\t$msg\n\n";
$Qservername = "localhost";
$Qusername = "masterlu_top";
$Qpassword = "GHryLsZARMd5Arx5";
$Qdbname = "masterlu_top";
date_default_timezone_set("Asia/Shanghai");
$time = date('Y:m:d:H:i:s');
//

// 创建连接
$conn = new mysqli($Qservername, $Qusername, $Qpassword, $Qdbname);
// 检测连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
} 
 
 //ar_dump($msg);
$Qdata = explode("&",$msg);
//ar_dump($Qdata);
$temp = $Qdata[0];
//ar_dump($temp);
$hum = $Qdata[1];
 

$sql = "INSERT INTO temp (time,temp,hum)
VALUES ('$time','$temp','$hum')";
 
if ($conn->query($sql) === TRUE) {
    echo "新记录插入成功";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}


$conn->close();                                                            
}
           

