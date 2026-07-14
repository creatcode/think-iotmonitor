<?php

declare(strict_types=1);

namespace CreatCode\IotMonitor\Protocol;

use Workerman\Connection\ConnectionInterface;

class ModbusTcpProtocol extends BaseProtocol
{
    const PROTOCOL_NAME = 'mtcp';

    public static function input($buffer, ConnectionInterface $connection)
    {
        $receivedLength = strlen($buffer);
        if ($receivedLength < 4) {
            return 0;
        }

        $extraLength = static::extraPacketLength(substr($buffer, 0, 4));
        if ($extraLength === null) {
            if ($receivedLength < 6) {
                return 0;
            }
            $frameLength = self::getFrameLength($buffer);
            if ($frameLength === -1) {
                return self::closeInvalidConnection($connection);
            }
        } else {
            $frameLength = $extraLength;
        }

        return $receivedLength < $frameLength ? 0 : $frameLength;
    }

    protected static function decodePayload($buffer): array
    {
        $tag = substr($buffer, 0, 4);
        $type = static::extraPacketLength($tag) === null ? 'report' : $tag;
        $data = $type === 'report' ? bin2hex($buffer) : $buffer;
        $protocol = static::protocolName();
        return compact('type', 'data', 'protocol');
    }

    public static function getFrameLength($binaryData)
    {
        if ($binaryData[2] !== "\x00" || $binaryData[3] !== "\x00") {
            return -1;
        }
        $length = (ord($binaryData[4]) << 8) | ord($binaryData[5]);
        return $length < 2 || $length > 254 ? -1 : 6 + $length;
    }
}
