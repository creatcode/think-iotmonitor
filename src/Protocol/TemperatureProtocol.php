<?php
declare(strict_types=1);
namespace CreatCode\IotMonitor\Protocol;
use Workerman\Connection\ConnectionInterface;
class TemperatureProtocol extends BaseProtocol
{
    const REPORT_PACKET_SIZE = 7;
    const PROTOCOL_NAME = 'temp';
    public static function input($buffer, ConnectionInterface $connection)
    {
        $received = strlen($buffer);
        if ($received < 4) return 0;
        $extra = static::extraPacketLength(substr($buffer, 0, 4));
        $length = $extra === null ? ($received < self::REPORT_PACKET_SIZE ? 0 : self::validateReportPacket($buffer)) : $extra;
        if ($length === false) return self::closeInvalidConnection($connection);
        return $received < $length ? 0 : $length;
    }
    protected static function decodePayload($buffer): array
    {
        $tag = substr($buffer, 0, 4); $type = static::extraPacketLength($tag) === null ? 'report' : $tag;
        $data = $type === 'report' ? bin2hex($buffer) : $buffer; $protocol = static::protocolName(); return compact('type','data','protocol');
    }
    public static function validateReportPacket($binaryData)
    {
        $sum = 0; for ($i = 0; $i < 5; $i++) $sum += ord($binaryData[$i]);
        return (($sum & 0xFF) === ord($binaryData[5])) ? self::REPORT_PACKET_SIZE : false;
    }
}
