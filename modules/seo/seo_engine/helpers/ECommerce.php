<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

/**
 * Contains helper functions for eCommerce plugins.
 */
class ECommerce
{
    /**
     * Checks whether the queried object is the WooCommerce shop page.
     *
     * @param int $id The post ID to check against (optional).
     * @return bool     Whether the current page is the WooCommerce shop page.
     *
     */
    public function isWooCommerceShopPage($id = 0)
    {
        if (!$this->isWooCommerceActive()) {
            return false;
        }

        if (!is_admin() && !wpfseo()->isAjaxCronRest() && function_exists('is_shop')) {
            return is_shop();
        }

        $id = !$id && !empty($_GET['post']) ? (int)wp_unslash($_GET['post']) : (int)$id; // phpcs:ignore HM.Security.ValidatedSanitizedInput
        return $id && wc_get_page_id('shop') === $id;
    }

    /**
     * Checks whether WooCommerce is active.
     *
     * @return boolean Whether WooCommerce is active.
     */
    public function isWooCommerceActive()
    {
        return class_exists('woocommerce');
    }

    /**
     * Checks whether the queried object is the WooCommerce cart page.
     *
     * @param int $id The post ID to check against (optional).
     * @return bool     Whether the current page is the WooCommerce cart page.
     */
    public function isWooCommerceCartPage($id = 0)
    {
        if (!$this->isWooCommerceActive()) {
            return false;
        }

        if (!is_admin() && !wpfseo()->isAjaxCronRest() && function_exists('is_cart')) {
            return is_cart();
        }

        $id = !$id && !empty($_GET['post']) ? (int)wp_unslash($_GET['post']) : (int)$id; // phpcs:ignore HM.Security.ValidatedSanitizedInput
        return $id && wc_get_page_id('cart') === $id;
    }

    /**
     * Checks whether the queried object is the WooCommerce checkout page.
     *
     * @param int $id The post ID to check against (optional).
     * @return bool     Whether the current page is the WooCommerce checkout page.
     */
    public function isWooCommerceCheckoutPage($id = 0)
    {
        if (!$this->isWooCommerceActive()) {
            return false;
        }

        if (!is_admin() && !wpfseo()->isAjaxCronRest() && function_exists('is_checkout')) {
            return is_checkout();
        }

        $id = !$id && !empty($_GET['post']) ? (int)wp_unslash($_GET['post']) : (int)$id; // phpcs:ignore HM.Security.ValidatedSanitizedInput
        return $id && wc_get_page_id('checkout') === $id;
    }

    /**
     * Checks whether the queried object is the WooCommerce account page.
     *
     * @param int $id The post ID to check against (optional).
     * @return bool     Whether the current page is the WooCommerce account page.
     */
    public function isWooCommerceAccountPage($id = 0)
    {
        if (!$this->isWooCommerceActive()) {
            return false;
        }

        if (!is_admin() && !wpfseo()->isAjaxCronRest() && function_exists('is_account_page')) {
            return is_account_page();
        }

        $id = !$id && !empty($_GET['post']) ? (int)wp_unslash($_GET['post']) : (int)$id; // phpcs:ignore HM.Security.ValidatedSanitizedInput
        return $id && wc_get_page_id('myaccount') === $id;
    }
}