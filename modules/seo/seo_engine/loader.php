<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use FlexySEO\Engine\WPFS_SEO;

const WPFS_SEO_ENGINE_SUPPORT = WPFS_SEO_ENGINE . 'support/';
const WPFS_SEO_ENGINE_HELPERS = WPFS_SEO_ENGINE . 'helpers/';
const WPFS_SEO_ENGINE_GENERATORS = WPFS_SEO_ENGINE . 'generators/';
const WPFS_SEO_ENGINE_EXTENSIONS = WPFS_SEO_ENGINE . 'extensions/';

// load core helpers
require_once WPFS_SEO_ENGINE_HELPERS . 'ECommerce.php';
require_once WPFS_SEO_ENGINE_HELPERS . 'Images.php';
require_once WPFS_SEO_ENGINE_HELPERS . 'Post.php';
require_once WPFS_SEO_ENGINE_HELPERS . 'Term.php';
require_once WPFS_SEO_ENGINE_HELPERS . 'CurrentPage.php';
require_once WPFS_SEO_ENGINE_HELPERS . 'StringHelper.php';
require_once WPFS_SEO_ENGINE_HELPERS . 'Language.php';

require_once WPFS_SEO_ENGINE . 'Helpers.php';

require_once WPFS_SEO_ENGINE_SUPPORT . 'SEOTag.php';
require_once WPFS_SEO_ENGINE_SUPPORT . 'SEOScriptTag.php';

// load utility
require_once WPFS_SEO_ENGINE . 'Txt_Replacer.php';
require_once WPFS_SEO_ENGINE . 'Rewriter.php';
require_once WPFS_SEO_ENGINE . 'Indexable.php';
require_once WPFS_SEO_ENGINE . 'Generator.php';
require_once WPFS_SEO_ENGINE . 'Presenter.php';

// load extensions
require_once WPFS_SEO_ENGINE_EXTENSIONS . 'XRE_MetaBox.php';

// load social and schema generators
require_once WPFS_SEO_ENGINE_GENERATORS . 'social/opengraph.php';
require_once WPFS_SEO_ENGINE_GENERATORS . 'social/twittercard.php';
require_once WPFS_SEO_ENGINE_GENERATORS . 'schema/schema.php';

/**
 * @param string $context
 * @return \FlexySEO\Engine\WPFS_SEO|\FlexySEO\Engine\Helpers\Helpers
 */
function wpfseo($context = 'helpers')
{
    switch ($context) {
        case 'helpers':
            return WPFS_SEO::getInstance()->helpers;

        default:
            return WPFS_SEO::getInstance();
    }
}

const WPFS_SEO_ENGINE_LOADED = true;
