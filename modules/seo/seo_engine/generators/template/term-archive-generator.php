<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Templates;

use FlexySEO\Engine\Default_Generator;
use FlexySEO\Engine\Helpers\CurrentPage;
use WPS\core\StringHelper;

class TermArchive_Generator extends Default_Generator
{
    public function __construct(CurrentPage $current_page)
    {
        parent::__construct($current_page);

        $type = $this->current_page->get_queried_object()->taxonomy ?? 'none';

        $this->settings_path = "seo.tax.$type.";
    }

    public function redirect(): array
    {
        if (wps('wpfs')->settings->get($this->settings_path . "active", true)) {
            return parent::redirect();
        }

        return array(home_url('/'), 301);
    }

    public function get_robots(): array
    {
        return [
            'index' => wps('wpfs')->settings->get($this->settings_path . 'show', true) ? 'index' : 'noindex'
        ];
    }

    public function generate_title(): string
    {
        $value = wpfs_get_term_meta_title(wpfseo('helpers')->termHandler->term);

        if (!empty($value)) {
            return $value;
        }

        return wps('wpfs')->settings->get($this->settings_path . 'title', '%%title%%');
    }


    public function get_description(): string
    {
        /**
         * Try to get the term meta description, set in WordPress Term editor and filtered.
         */
        $value = wpfs_get_term_meta_description(wpfseo('helpers')->termHandler->term, false);

        if (!empty($value)) {
            return $value;
        }

        /**
         * Try to get the built-in WordPress Term description, set in WordPress Term editor.
         */
        $value = StringHelper::truncate($this->queried_object->description ?? '', 150);

        if (!empty($value)) {
            return $value;
        }

        /**
         * Get default Term description.
         */
        return wps('wpfs')->settings->get($this->settings_path . 'meta_desc', '%%description%%');
    }
}