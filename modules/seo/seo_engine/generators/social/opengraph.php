<?php

namespace FlexySEO\Engine\Generators;

use FlexySEO\Engine\Helpers\SEOTag;

/**
 * Class Open_Graph_Locale_Generator.
 */
class OpenGraph
{
    /**
     * The version number
     */
    const VERSION = '2.0.0';

    /**
     * Define a prefix for tag names
     */
    const NAME_PREFIX = 'og:';

    /**
     * Array containing the tags
     *
     * @var array (name -> value)
     */
    private $tags;

    private $tag_index = 0;

    /**
     * @var bool
     */
    private $validate;

    /**
     * Constructor call
     * @param bool $validate
     */
    public function __construct($validate = false)
    {
        $this->tags = array();

        $this->validate = $validate;

        $this->locale();
    }

    /**
     * Generates the OG Locale.
     * @param string $locale
     */
    public function locale($locale = '')
    {
        if (empty($locale))
            $locale = get_locale();

        $locale = apply_filters('wpfs_locale', $locale);

        // Catch some weird locales served out by WP that are not easily doubled up.
        $fix_locales = [
            'ca' => 'ca_ES',
            'en' => 'en_US',
            'el' => 'el_GR',
            'et' => 'et_EE',
            'ja' => 'ja_JP',
            'sq' => 'sq_AL',
            'uk' => 'uk_UA',
            'vi' => 'vi_VN',
            'zh' => 'zh_CN',
        ];

        if (isset($fix_locales[$locale])) {
            $locale = $fix_locales[$locale];
        }

        // Convert locales like "es" to "es_ES", in case that works for the given locale (sometimes it does).
        if (strlen($locale) === 2) {
            $locale = strtolower($locale) . '_' . strtoupper($locale);
        }
        else {
            $locale = substr($locale, 0, 2);
            $locale = strtolower($locale) . '_' . strtoupper($locale);
        }

        // These are the locales FB supports.
        $fb_valid_fb_locales = [
            'af_ZA', // Afrikaans.
            'ak_GH', // Akan.
            'am_ET', // Amharic.
            'ar_AR', // Arabic.
            'as_IN', // Assamese.
            'ay_BO', // Aymara.
            'az_AZ', // Azerbaijani.
            'be_BY', // Belarusian.
            'bg_BG', // Bulgarian.
            'bp_IN', // Bhojpuri.
            'bn_IN', // Bengali.
            'br_FR', // Breton.
            'bs_BA', // Bosnian.
            'ca_ES', // Catalan.
            'cb_IQ', // Sorani Kurdish.
            'ck_US', // Cherokee.
            'co_FR', // Corsican.
            'cs_CZ', // Czech.
            'cx_PH', // Cebuano.
            'cy_GB', // Welsh.
            'da_DK', // Danish.
            'de_DE', // German.
            'el_GR', // Greek.
            'en_GB', // English (UK).
            'en_PI', // English (Pirate).
            'en_UD', // English (Upside Down).
            'en_US', // English (US).
            'em_ZM',
            'eo_EO', // Esperanto.
            'es_ES', // Spanish (Spain).
            'es_LA', // Spanish.
            'es_MX', // Spanish (Mexico).
            'et_EE', // Estonian.
            'eu_ES', // Basque.
            'fa_IR', // Persian.
            'fb_LT', // Leet Speak.
            'ff_NG', // Fulah.
            'fi_FI', // Finnish.
            'fo_FO', // Faroese.
            'fr_CA', // French (Canada).
            'fr_FR', // French (France).
            'fy_NL', // Frisian.
            'ga_IE', // Irish.
            'gl_ES', // Galician.
            'gn_PY', // Guarani.
            'gu_IN', // Gujarati.
            'gx_GR', // Classical Greek.
            'ha_NG', // Hausa.
            'he_IL', // Hebrew.
            'hi_IN', // Hindi.
            'hr_HR', // Croatian.
            'hu_HU', // Hungarian.
            'ht_HT', // Haitian Creole.
            'hy_AM', // Armenian.
            'id_ID', // Indonesian.
            'ig_NG', // Igbo.
            'is_IS', // Icelandic.
            'it_IT', // Italian.
            'ik_US',
            'iu_CA',
            'ja_JP', // Japanese.
            'ja_KS', // Japanese (Kansai).
            'jv_ID', // Javanese.
            'ka_GE', // Georgian.
            'kk_KZ', // Kazakh.
            'km_KH', // Khmer.
            'kn_IN', // Kannada.
            'ko_KR', // Korean.
            'ks_IN', // Kashmiri.
            'ku_TR', // Kurdish (Kurmanji).
            'ky_KG', // Kyrgyz.
            'la_VA', // Latin.
            'lg_UG', // Ganda.
            'li_NL', // Limburgish.
            'ln_CD', // Lingala.
            'lo_LA', // Lao.
            'lt_LT', // Lithuanian.
            'lv_LV', // Latvian.
            'mg_MG', // Malagasy.
            'mi_NZ', // Maori.
            'mk_MK', // Macedonian.
            'ml_IN', // Malayalam.
            'mn_MN', // Mongolian.
            'mr_IN', // Marathi.
            'ms_MY', // Malay.
            'mt_MT', // Maltese.
            'my_MM', // Burmese.
            'nb_NO', // Norwegian (bokmal).
            'nd_ZW', // Ndebele.
            'ne_NP', // Nepali.
            'nl_BE', // Dutch (Belgie).
            'nl_NL', // Dutch.
            'nn_NO', // Norwegian (nynorsk).
            'nr_ZA', // Southern Ndebele.
            'ns_ZA', // Northern Sotho.
            'ny_MW', // Chewa.
            'om_ET', // Oromo.
            'or_IN', // Oriya.
            'pa_IN', // Punjabi.
            'pl_PL', // Polish.
            'ps_AF', // Pashto.
            'pt_BR', // Portuguese (Brazil).
            'pt_PT', // Portuguese (Portugal).
            'qc_GT', // Quiché.
            'qu_PE', // Quechua.
            'qr_GR',
            'qz_MM', // Burmese (Zawgyi).
            'rm_CH', // Romansh.
            'ro_RO', // Romanian.
            'ru_RU', // Russian.
            'rw_RW', // Kinyarwanda.
            'sa_IN', // Sanskrit.
            'sc_IT', // Sardinian.
            'se_NO', // Northern Sami.
            'si_LK', // Sinhala.
            'su_ID', // Sundanese.
            'sk_SK', // Slovak.
            'sl_SI', // Slovenian.
            'sn_ZW', // Shona.
            'so_SO', // Somali.
            'sq_AL', // Albanian.
            'sr_RS', // Serbian.
            'ss_SZ', // Swazi.
            'st_ZA', // Southern Sotho.
            'sv_SE', // Swedish.
            'sw_KE', // Swahili.
            'sy_SY', // Syriac.
            'sz_PL', // Silesian.
            'ta_IN', // Tamil.
            'te_IN', // Telugu.
            'tg_TJ', // Tajik.
            'th_TH', // Thai.
            'tk_TM', // Turkmen.
            'tl_PH', // Filipino.
            'tl_ST', // Klingon.
            'tn_BW', // Tswana.
            'tr_TR', // Turkish.
            'ts_ZA', // Tsonga.
            'tt_RU', // Tatar.
            'tz_MA', // Tamazight.
            'uk_UA', // Ukrainian.
            'ur_PK', // Urdu.
            'uz_UZ', // Uzbek.
            've_ZA', // Venda.
            'vi_VN', // Vietnamese.
            'wo_SN', // Wolof.
            'xh_ZA', // Xhosa.
            'yi_DE', // Yiddish.
            'yo_NG', // Yoruba.
            'zh_CN', // Simplified Chinese (China).
            'zh_HK', // Traditional Chinese (Hong Kong).
            'zh_TW', // Traditional Chinese (Taiwan).
            'zu_ZA', // Zulu.
            'zz_TR', // Zazaki.
        ];

        // Check to see if the locale is a valid FB one, if not, use en_US as a fallback.
        if (!in_array($locale, $fb_valid_fb_locales, true)) {
            $locale = 'en_US';
        }

        $this->add_tag('locale', $locale);
    }

    public function add_tag($name, $value, $prefix = self::NAME_PREFIX)
    {
        if ($prefix)
            $name = $prefix . $name;

        $this->tags[$this->tag_index++] = new SEOTag($name, $value);
    }

    /**
     * True if at least one tag with the given name exists.
     * It's possible that a tag has multiple values.
     *
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        foreach ($this->tags as $tag_name => $value) {
            if ($tag_name == $name) {
                return true;
            }
        }

        return false;
    }


    /**
     * @return  SEOTag[]
     */
    public function get_tags()
    {
        return $this->tags;
    }

    /**
     * Remove all tags with the given name
     *
     * @param string $name
     */
    public function remove($name)
    {
        foreach ($this->tags as $tag_name => $value) {
            if ($tag_name == $name) {
                unset($this->tags[$tag_name]);
            }
        }
    }

    /**
     * Adds a title tag
     *
     * @param string $title
     * @return bool
     */
    public function title($title)
    {
        $title = trim($title);

        if ($this->validate and !$title) {
            return false;
        }

        $this->add_tag('title', strip_tags($title));

        return true;
    }

    /**
     * Adds a type tag.
     *
     * @param string $type
     * @return bool
     */
    public function type($type)
    {
        $types = [
            'music.song',
            'music.album',
            'music.playlist',
            'music.radio_station',
            'video.movie',
            'video.episode',
            'video.tv_show',
            'video.other',
            'article',
            'book',
            'profile',
            'website',
        ];

        if ($this->validate and !in_array($type, $types)) {
            return false;
        }

        $this->add_tag('type', $type);

        return true;
    }

    /**
     * Adds an image tag.
     * If the URL is relative it's converted to an absolute one.
     *
     * @param string $imageFile The URL of the image file
     * @param array|null $attributes Array with additional attributes (pairs of name and value)
     * @return bool
     */
    public function image($imageFile, array $attributes = null)
    {
        if ($this->validate and !$imageFile) {
            return false;
        }

        if (strpos($imageFile, '://') === false and function_exists('asset')) {
            $imageFile = asset($imageFile);
        }

        if ($this->validate and !filter_var($imageFile, FILTER_VALIDATE_URL)) {
            return false;
        }

        $this->add_tag('image', $imageFile);

        if ($attributes) {
            $valid = [
                'secure_url',
                'type',
                'width',
                'height',
            ];

            $this->attributes('image', $attributes, $valid);
        }

        return true;
    }

    /**
     * Adds attribute tags to the list of tags
     *
     * @param string $tagName The name of the base tag
     * @param array $attributes Array with attributes (pairs of name and value)
     * @param string[] $valid Array with names of valid attributes
     * @param bool $prefixed Add the "og"-prefix?
     */
    private function attributes($tagName, array $attributes = [], array $valid = [], $prefixed = true)
    {
        foreach ($attributes as $name => $value) {

            if (!empty($valid) and !in_array($name, $valid)) {
                continue;
            }

            $value = $this->convertDate($value);

            if (!$prefixed)
                $this->add_tag($tagName . ':' . $name, $value, false);
            else
                $this->add_tag($tagName . ':' . $name, $value);
        }
    }

    /**
     * Converts a DateTime object to a string (ISO 8601)
     *
     * @param string|\DateTime $date The date (string or DateTime)
     * @return string
     */
    protected function convertDate($date)
    {
        if (is_a($date, 'DateTime')) {
            return (string)$date->format(\DateTime::ISO8601);
        }

        return $date;
    }

    /**
     * Adds a description tag
     *
     * @param int $description If the text is longer than this it is shortened
     * @param int $maxLength
     */
    public function description($description, $maxLength = 250)
    {
        $description = trim(strip_tags($description));
        $description = preg_replace("/\r|\n/", '', $description);

        $length = mb_strlen($description);

        $description = mb_substr($description, 0, $maxLength);

        if (mb_strlen($description) < $length) {
            $description .= '...';
        }

        $this->add_tag('description', $description);
    }

    /**
     * Adds a URL tag
     *
     * @param string $url
     * @return bool
     */
    public function url($url = null)
    {
        if (!$url) {
            $url = null;

            $httpHost = getenv('APP_URL'); // Has to start with a protocol - for example "http://"!

            if ($httpHost === false) {
                $url = 'http';

                // Quick and dirty
                if (isset($_SERVER['HTTPS'])) {
                    $url .= 's';
                }

                $url .= '://';

                $httpHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost/';
            }

            $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

            $safeRequestURI = htmlentities(strip_tags(urldecode($requestUri)));

            $url .= "{$httpHost}{$safeRequestURI}";
        }

        if ($this->validate and !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $this->add_tag('url', $url);

        return true;
    }

    /**
     * Adds locale:alternate tags
     *
     * @param string[] $locales An array of alternative locales
     * @return bool
     */
    public function localeAlternate(array $locales = [])
    {
        if (is_string($locales)) {
            $locales = (array)$locales;
        }

        foreach ($locales as $key => $locale) {

            if ($this->validate and !$locale) {
                return false;
            }

            $this->add_tag('locale:alternate', $locale);
        }

        return true;
    }

    /**
     * Adds a site_name tag
     *
     * @param string $siteName
     * @return bool
     */
    public function siteName($siteName)
    {
        if ($this->validate and !$siteName) {
            return false;
        }

        $this->add_tag('site_name', $siteName);

        return true;
    }

    /**
     * Adds a determiner tag.
     *
     * @param string $determiner
     * @return bool
     */
    public function determiner($determiner = '')
    {
        $enum = [
            'a',
            'an',
            'the',
            'auto',
            ''
        ];

        if ($this->validate and !in_array($determiner, $enum)) {
            return false;
        }

        $this->add_tag('determiner', $determiner);

        return true;
    }

    /**
     * Adds an audio tag.
     * If the URL is relative its converted to an absolute one.
     *
     * @param string $audioFile The URL of the video file
     * @param array|null $attributes Array with additional attributes (pairs of name and value)
     * @return bool
     */
    public function audio($audioFile, array $attributes = null)
    {
        if ($this->validate and !$audioFile) {
            return false;
        }

        if (strpos($audioFile, '://') === false and function_exists('asset')) {
            $audioFile = asset($audioFile);
        }

        if ($this->validate and !filter_var($audioFile, FILTER_VALIDATE_URL)) {
            return false;
        }

        $this->add_tag('audio', $audioFile);

        if ($attributes) {
            $valid = [
                'secure_url',
                'type',
            ];

            $tag = $this->getTag('type');

            $specialValid = [];

            if ($tag == 'music.song') {
                $specialValid = [
                    'duration',
                    'album',
                    'album:disc',
                    'album:track',
                    'musician',
                ];
            }

            if ($tag == 'music.album') {
                $specialValid = [
                    'song',
                    'song:disc',
                    'song:track',
                    'musician',
                    'release_date',
                ];
            }

            if ($tag == 'music.playlist') {
                $specialValid = [
                    'song',
                    'song:disc',
                    'song:track',
                    'creator',
                ];
            }

            if ($tag == 'music.radio_station') {
                $specialValid = [
                    'creator',
                ];
            }

            $valid = array_merge($valid, $specialValid);

            $this->attributes('audio', $attributes, $valid);
        }

        return true;
    }

    /**
     * Returns the last tag in the lists of tags with matching name
     *
     * @param string $name The name of the ta
     * @return string|null
     */
    public function getTag($name)
    {
        $lastTag = null;

        $name = self::NAME_PREFIX . $name;

        foreach ($this->tags as $key => $value) {
            if ($key == $name) {
                $lastTag = $value;
                break;
            }
        }

        return $lastTag;
    }

    /**
     * Adds a video tag
     * If the URL is relative its converted to an absolute one.
     *
     * @param string $videoFile The URL of the video file
     * @param array|null $attributes Array with additional attributes (pairs of name and value)
     * @return bool
     */
    public function video($videoFile, array $attributes = null)
    {
        if ($this->validate and !$videoFile) {
            return false;
        }

        if (strpos($videoFile, '://') === false and function_exists('asset')) {
            $videoFile = asset($videoFile);
        }

        if ($this->validate and !filter_var($videoFile, FILTER_VALIDATE_URL)) {
            return false;
        }

        $this->add_tag('video', $videoFile);

        if ($attributes) {
            $valid = [
                'secure_url',
                'type',
                'width',
                'height',
            ];

            $tag = $this->getTag('type');
            if ($tag and strpos($tag, 'video.') !== false) {
                $specialValid = [
                    'actor',
                    'role',
                    'director',
                    'writer',
                    'duration',
                    'release_date',
                    'tag',
                ];

                if ($tag == 'video.episode') {
                    $specialValid[] = 'video:series';
                }

                $valid = array_merge($valid, $specialValid);
            }

            $this->attributes('video', $attributes, $valid);
        }

        return true;
    }

    /**
     * Adds article attributes
     *
     * @param array $attributes Array with attributes (pairs of name and value)
     * @return bool
     */
    public function article(array $attributes = [])
    {
        if ($this->getTag('type') != 'article') {
            return false;
        }

        $valid = [
            'published_time',
            'modified_time',
            'expiration_time',
            'author',
            'section',
            'tag',
        ];

        $this->attributes('article', $attributes, $valid, false);

        return true;
    }

    /**
     * Adds book attributes
     *
     * @param array $attributes Array with attributes (pairs of name and value)
     * @return bool
     */
    public function book(array $attributes = [])
    {
        if ($this->getTag('type') != 'book') {
            return false;
        }

        $valid = [
            'author',
            'isbn',
            'release_date',
            'tag',
        ];

        $this->attributes('book', $attributes, $valid, false);

        return true;
    }

    /**
     * Adds profile attributes
     *
     * @param array $attributes Array with attributes (pairs of name and value)
     * @return bool
     */
    public function profile(array $attributes = [])
    {
        if ($this->getTag('type') != 'profile') {
            return false;
        }

        $valid = [
            'first_name',
            'last_name',
            'username',
            'gender',
        ];

        $this->attributes('profile', $attributes, $valid, false);

        return true;
    }

    /**
     * Remove all tags
     */
    public function clear()
    {
        $this->tags = array();
    }
}
