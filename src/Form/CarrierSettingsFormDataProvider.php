<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Axelweb\AwPickupStore\Form;

use Axelweb\AwPickupStore\Repository\PickupStoreRepository;
use Language;
use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

/**
 * Provides carrier settings data to the form and persists submitted data.
 * Reads/writes directly via PickupStoreRepository — no ps_configuration involved.
 *
 * TranslatableType uses locale strings (e.g. 'fr-FR') as keys.
 * The DB stores id_lang integers. This provider converts between both.
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
     *
     * @return array{carriers: array<int, array{id_carrier: int, name: string, require_appointment: bool, show_store_picker: bool, message: array<string, string>}>}
     */
    public function getData(): array
    {
        $carriers = $this->repository->getAllCarriersBasic();
        $localeByIdLang = $this->getLocaleByIdLang();

        return [
            'carriers' => array_map(function (array $row) use ($localeByIdLang): array {
                $idCarrier = (int) $row['id_carrier'];
                $dbMessages = $this->repository->getAllCarrierMessages($idCarrier);

                $messages = [];
                foreach ($dbMessages as $idLang => $message) {
                    $locale = $localeByIdLang[$idLang] ?? null;
                    if ($locale !== null) {
                        $messages[$locale] = $message;
                    }
                }

                return [
                    'id_carrier'          => $idCarrier,
                    'name'                => (string) ($row['name'] ?? ''),
                    'require_appointment' => (bool) $row['require_appointment'],
                    'show_store_picker'   => (bool) $row['show_store_picker'],
                    'message'             => $messages,
                ];
            }, $carriers),
        ];
    }

    /**
     * Persists the submitted carrier settings.
     * Returns an array of error messages (empty on success).
     *
     * @param array{carriers?: array<int, array{id_carrier: mixed, require_appointment: mixed, show_store_picker: mixed, message: mixed}>} $data
     *
     * @return string[]
     */
    public function setData(array $data): array
    {
        $idLangByLocale = $this->getIdLangByLocale();

        foreach ($data['carriers'] ?? [] as $entry) {
            $messages = [];
            foreach ($entry['message'] ?? [] as $locale => $text) {
                $idLang = $idLangByLocale[$locale] ?? null;
                if ($idLang !== null && trim((string) $text) !== '') {
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

    /** @return array<int, string> id_lang => locale */
    private function getLocaleByIdLang(): array
    {
        return array_column(Language::getLanguages(true), 'locale', 'id_lang');
    }

    /** @return array<string, int> locale => id_lang */
    private function getIdLangByLocale(): array
    {
        return array_column(Language::getLanguages(true), 'id_lang', 'locale');
    }
}
