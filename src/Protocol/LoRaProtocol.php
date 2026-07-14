<?php
declare(strict_types=1);
namespace CreatCode\ThinkIotMonitor\Protocol;
use Workerman\Connection\ConnectionInterface;
class LoRaProtocol extends BaseProtocol
{
    const START_FLAG=0x6C, END_FLAG=0x16, SCHEME=0x11, PROTOCOL_NAME='lora';
    public static function input($buffer, ConnectionInterface $connection)
    {
        $length=strlen($buffer); if (!$length) return 0;
        if (ord($buffer[0])!==self::START_FLAG) return self::closeInvalidConnection($connection);
        if ($length<3) return 0;
        if (ord($buffer[2])!==self::SCHEME) return self::closeInvalidConnection($connection);
        if ($length<7) return 0;
        $payload=ord($buffer[1]); if ($payload<2 || $payload>250) return self::closeInvalidConnection($connection);
        $frame=5+$payload; if ($length<$frame) return 0;
        if (ord($buffer[$frame-1])!==self::END_FLAG) return self::closeInvalidConnection($connection);
        $sum=0; for($i=0;$i<$frame-2;$i++) $sum+=ord($buffer[$i]);
        return (($sum&0xFF)===ord($buffer[$frame-2]))?$frame:self::closeInvalidConnection($connection);
    }
    protected static function decodePayload($buffer): array
    {
        $type='report'; $command=substr($buffer,3,2);
        if($command==="\x04\x01")$type='imei'; elseif($command==="\x04\x02")$type='ping';
        $data=bin2hex($buffer); $protocol=static::protocolName(); return compact('type','data','protocol');
    }
}
