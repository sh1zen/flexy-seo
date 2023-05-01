<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
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

if(shzn('wpfs')->settings->get('seo.schema.enabled', false)) {

    require_once WPFS_SEO_ENGINE_GENERATORS . 'schema/GraphBuilder.php';
    require_once WPFS_SEO_ENGINE_GENERATORS . 'schema/GraphUtility.php';
    require_once WPFS_SEO_ENGINE_GENERATORS . 'schema/CommonGraphs.php';

    require_once WPFS_SEO_ENGINE_GENERATORS . 'schema/graph/Graph.php';
    require_once WPFS_SEO_ENGINE_GENERATORS . 'schema/graph/Person.php';
    require_once WPFS_SEO_ENGINE_GENERATORS . 'schema/graph/Article.php';
    require_once WPFS_SEO_ENGINE_GENERATORS . 'schema/graph/WebPage.php';
    require_once WPFS_SEO_ENGINE_GENERATORS . 'schema/graph/Organization.php';
}

require_once WPFS_SEO_ENGINE . 'wp_hooks.php';

/**
 * @param string $context
 * @return \FlexySEO\Engine\Generator|\FlexySEO\Engine\Helpers\Helpers|\FlexySEO\Engine\WPFS_SEO|string|null
 */
function wpfseo(string $context = 'helpers')
{
    switch ($context) {
        case 'helpers':
            return WPFS_SEO::getInstance()->helpers;

        case 'generator':
            return WPFS_SEO::getInstance()->generator;

        case 'salt':
            return "SHS16YW89RIF3489F08";

        default:
            return WPFS_SEO::getInstance();
    }
}

const WPFS_SEO_ENGINE_LOADED = true;
