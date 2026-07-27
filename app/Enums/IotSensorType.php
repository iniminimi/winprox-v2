<?php

declare(strict_types=1);

namespace App\Enums;

enum IotSensorType: string
{
    case WaterLeak = 'water_leak';
    case Temperature = 'temperature';
    case Humidity = 'humidity';
    case Door = 'door';
    case Vibration = 'vibration';
    case Sound = 'sound';
    case Energy = 'energy';
    case FillLevel = 'fill_level';
    case Other = 'other';

    public function labelKey(): string
    {
        return 'iot.sensor_types.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
