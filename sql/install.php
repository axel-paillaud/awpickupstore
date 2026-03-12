<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2007-2024 Axelweb
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'awpickupstore_carrier` (
    `id_carrier`          INT(10) UNSIGNED NOT NULL,
    `require_appointment` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `show_store_picker`   TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_carrier`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'awpickupstore_carrier_lang` (
    `id_carrier` INT(10) UNSIGNED NOT NULL,
    `id_lang`    INT(10) UNSIGNED NOT NULL,
    `message`    TEXT DEFAULT NULL,
    PRIMARY KEY (`id_carrier`, `id_lang`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'awpickupstore_appointment` (
    `id_awpickupstore_appointment` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_order`                     INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `id_cart`                      INT(10) UNSIGNED NOT NULL,
    `id_store`                     INT(10) UNSIGNED DEFAULT NULL,
    `appointment_datetime`         DATETIME NOT NULL,
    PRIMARY KEY (`id_awpickupstore_appointment`),
    UNIQUE KEY `id_cart` (`id_cart`),
    KEY `id_order` (`id_order`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
