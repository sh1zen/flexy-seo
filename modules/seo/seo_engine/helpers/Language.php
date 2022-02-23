<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

/**
 * Contains i18n and language (code) helper methods.

 */
class Language
{
    /**
     * Returns the language of the current response in BCP 47 format.
     *
     * @return string The language code in BCP 47 format.

     *
     */
    public function currentLanguageCodeBCP47()
    {
        return str_replace('_', '-', $this->currentLanguageCode());
    }

    /**
     * Returns the language of the current response.
     *
     * @return string The language code.

     *
     */
    public function currentLanguageCode()
    {
        global $wp_version;
        if (version_compare($wp_version, '5.0', '<')) {
            return get_locale();
        }
        return determine_locale();
    }
}