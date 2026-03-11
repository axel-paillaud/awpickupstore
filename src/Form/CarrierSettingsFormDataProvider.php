<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Axelweb\AwPickupStore\Form;

use Axelweb\AwPickupStore\Repository\PickupStoreRepository;
use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

/**
 * Provides carrier settings data to the form and persists submitted data.
 * Reads/writes directly via PickupStoreRepository — no ps_configuration involved.
 *
 * TranslatableType keys fields by id_lang integer (not locale string).
 * No locale conversion needed — we pass id_lang arrays directly to/from the repository.
 */
class CarrierSettingsFormDataProvider implements FormDataProviderInterface
{
    private PickupStoreRepository $repository;

    public function __construct(PickupStoreRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Returns all active carriers with their current pickup settings.
     * The 'name' key in each entry is for Twig display only (not a form field).
     * Message is keyed by id_lang integer, as expected by TranslatableType.
     *
     * @return array{carriers: array<int, array{id_carrier: int, name: string, require_appointment: bool, show_store_picker: bool, message: array<int, string>}>}
     */
    public function getData(): array
    {
        $carriers = $this->repository->getAllCarriersBasic();

        return [
            'carriers' => array_map(function (array $row): array {
                $idCarrier = (int) $row['id_carrier'];

                return [
                    'id_carrier'          => $idCarrier,
                    'name'                => (string) ($row['name'] ?? ''),
                    'require_appointment' => (bool) $row['require_appointment'],
                    'show_store_picker'   => (bool) $row['show_store_picker'],
                    'message'             => $this->repository->getAllCarrierMessages($idCarrier),
                ];
            }, $carriers),
        ];
    }

    /**
     * Persists the submitted carrier settings.
     * TranslatableType submits message as [id_lang => text] — passed directly to the repository.
     * Returns an array of error messages (empty on success).
     *
     * @param array{carriers?: array<int, array{id_carrier: mixed, require_appointment: mixed, show_store_picker: mixed, message: mixed}>} $data
     *
     * @return string[]
     */
    public function setData(array $data): array
    {
        foreach ($data['carriers'] ?? [] as $entry) {
            $messages = [];
            foreach ($entry['message'] ?? [] as $idLang => $text) {
                if (trim((string) $text) !== '') {
                    $messages[(int) $idLang] = (string) $text;
                }
            }

            $this->repository->saveCarrierConfig(
                (int) $entry['id_carrier'],
                (bool) ($entry['require_appointment'] ?? false),
                (bool) ($entry['show_store_picker'] ?? false),
                $messages
            );
        }

        return [];
    }
}
