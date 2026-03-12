<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Axelweb\AwPickupStore\Form;

use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Global appointment schedule form.
 * Defines opening hours for each day of the week (JS day numbering: 0=Sun … 6=Sat).
 * Displayed in Mon→Sun order. Stored as JSON in ps_configuration (AWPICKUPSTORE_SCHEDULE).
 */
class ScheduleFormType extends TranslatorAwareType
{
    /** JS day => translatable label. Display order: Mon→Sun. */
    public const DAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        0 => 'Sunday',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (self::DAYS as $jsDay => $label) {
            $builder
                ->add('day_' . $jsDay . '_enabled', SwitchType::class, [
                    'label'    => false,
                    'required' => false,
                ])
                ->add('day_' . $jsDay . '_open', TextType::class, [
                    'label'    => false,
                    'required' => false,
                    'attr'     => [
                        'placeholder' => '09:00',
                        'pattern'     => '\d{2}:\d{2}',
                    ],
                ])
                ->add('day_' . $jsDay . '_close', TextType::class, [
                    'label'    => false,
                    'required' => false,
                    'attr'     => [
                        'placeholder' => '18:00',
                        'pattern'     => '\d{2}:\d{2}',
                    ],
                ]);
        }
    }
}
