<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Axelweb\AwPickupStore\Form;

use Configuration;
use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

/**
 * Reads and writes the global appointment schedule from/to ps_configuration.
 *
 * Storage format (AWPICKUPSTORE_SCHEDULE):
 *   {"1":{"open":"09:00","close":"18:00"}, "5":{"open":"09:00","close":"17:00"}}
 *
 * Keys are JS day numbers (0=Sun, 1=Mon … 6=Sat). Only open days are stored.
 * Form fields are flat: day_{jsDay}_enabled, day_{jsDay}_open, day_{jsDay}_close.
 */
class ScheduleFormDataProvider implements FormDataProviderInterface
{
    private const CONFIG_KEY = 'AWPICKUPSTORE_SCHEDULE';

    public function getData(): array
    {
        $schedule = $this->loadSchedule();
        $data     = [];

        foreach (array_keys(ScheduleFormType::DAYS) as $jsDay) {
            $day = $schedule[$jsDay] ?? null;
            $data['day_' . $jsDay . '_enabled'] = $day !== null;
            $data['day_' . $jsDay . '_open']    = $day['open']  ?? '';
            $data['day_' . $jsDay . '_close']   = $day['close'] ?? '';
        }

        return $data;
    }

    public function setData(array $data): array
    {
        $schedule = [];

        foreach (array_keys(ScheduleFormType::DAYS) as $jsDay) {
            if (!empty($data['day_' . $jsDay . '_enabled'])) {
                $open  = trim($data['day_' . $jsDay . '_open']  ?? '');
                $close = trim($data['day_' . $jsDay . '_close'] ?? '');
                if ($open !== '' && $close !== '') {
                    $schedule[$jsDay] = ['open' => $open, 'close' => $close];
                }
            }
        }

        Configuration::updateValue(self::CONFIG_KEY, json_encode($schedule));

        return [];
    }

    /** @return array<int, array{open: string, close: string}> */
    public function loadSchedule(): array
    {
        $json = Configuration::get(self::CONFIG_KEY);
        if (empty($json)) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_map(
            static fn (array $d): array => ['open' => (string) $d['open'], 'close' => (string) $d['close']],
            $decoded
        ) : [];
    }
}
