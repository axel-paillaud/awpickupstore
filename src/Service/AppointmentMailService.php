<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Axelweb\AwPickupStore\Service;

class AppointmentMailService
{
    /** Subjects indexed by language ISO code. */
    private const SUBJECTS = [
        'fr' => 'Confirmation de retrait — Commande {order_reference}',
        'en' => 'Pickup confirmation — Order {order_reference}',
    ];

    /**
     * Send the appointment confirmation email to the customer.
     *
     * @param \Customer $customer
     * @param string    $orderReference
     * @param string|null $datetime   Formatted appointment datetime, or null.
     * @param string|null $storeName  Collection point name, or null.
     * @param int         $idLang
     * @param int         $idShop
     */
    public function send(
        \Customer $customer,
        string $orderReference,
        ?string $datetime,
        ?string $storeName,
        int $idLang,
        int $idShop
    ): void {
        if (!$datetime && !$storeName) {
            return;
        }

        $langIso = \Language::getIsoById($idLang) ?: 'en';
        $subject = str_replace(
            '{order_reference}',
            $orderReference,
            self::SUBJECTS[$langIso] ?? self::SUBJECTS['en']
        );

        \Mail::Send(
            $idLang,
            'awpickupstore_appointment',
            $subject,
            [
                '{firstname}'         => $customer->firstname,
                '{lastname}'          => $customer->lastname,
                '{order_reference}'   => $orderReference,
                '{appointment_rows}'  => $this->buildHtmlRows($datetime, $storeName),
                '{appointment_lines}' => $this->buildTextLines($datetime, $storeName),
            ],
            $customer->email,
            $customer->firstname . ' ' . $customer->lastname,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'awpickupstore/mails/',
            false,
            $idShop
        );
    }

    private function buildHtmlRows(?string $datetime, ?string $storeName): string
    {
        $rows = '';

        if ($datetime) {
            $rows .= '<tr>'
                . '<td style="padding:4px 10px 4px 0;color:#777;white-space:nowrap">Date de rendez-vous :</td>'
                . '<td style="padding:4px 0"><strong>' . htmlspecialchars($datetime) . '</strong></td>'
                . '</tr>';
        }

        if ($storeName) {
            $rows .= '<tr>'
                . '<td style="padding:4px 10px 4px 0;color:#777;white-space:nowrap">Point de collecte :</td>'
                . '<td style="padding:4px 0"><strong>' . htmlspecialchars($storeName) . '</strong></td>'
                . '</tr>';
        }

        return $rows;
    }

    private function buildTextLines(?string $datetime, ?string $storeName): string
    {
        $lines = '';

        if ($datetime) {
            $lines .= 'Date de rendez-vous : ' . $datetime . "\n";
        }

        if ($storeName) {
            $lines .= 'Point de collecte : ' . $storeName . "\n";
        }

        return $lines;
    }
}
