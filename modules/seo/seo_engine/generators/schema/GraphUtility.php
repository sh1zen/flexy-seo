<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators;

class GraphUtility
{
    /**
     * Returns the social media URLs for the author.
     *
     * @param int $authorId The author ID.
     * @return array $socialUrls The social media URLs.
     */
    public static function socialUrls($authorId = false)
    {
        $socialUrls = [];

        $urls = [
            'facebook'   => "https://facebook.com/%s",
            'twitter'    => "https://twitter.com/%s",
            'instagram'  => "https://instagram.com/%s",
            'pinterest'  => "https://pinterest.com/%s",
            'youtube'    => "https://youtube.com/%s",
            'linkedin'   => "https://linkedin.com/in/%s",
            'tumblr'     => "https://%s.tumblr.com",
            'yelp'       => "https://yelp.com/biz/%s",
            'soundcloud' => "https://soundcloud.com/%s",
            'wikipedia'  => "https://en.wikipedia.org/wiki/%s"
        ];

        $sameUsername = false;

        if ($authorId) {
            $socialUrls = [
                'this' => shzn()->utility->home_url,
            ];
        }
        else {

            if (shzn('wpfs')->settings->get("seo.social.sameUsername.enable", false)) {
                $sameUsername = shzn('wpfs')->settings->get("seo.social.sameUsername.username", '');
                if (empty($sameUsername)) {
                    return $socialUrls;
                }
            }

            foreach ($urls as $name => $value) {

                if ($sameUsername) {
                    $username = shzn('wpfs')->settings->get("seo.social.{$name}.enable", true) ? $sameUsername : '';
                }
                else {
                    $username = shzn('wpfs')->settings->get("seo.social.{$name}.url", '');
                }

                if (!empty($username)) {

                    if (!str_contains($username, "/")) {
                        $socialUrls[] = sprintf($value, $username);
                    }
                    else {
                        $socialUrls[] = $username;
                    }
                }
            }
        }

        return array_values(array_filter($socialUrls));
    }
}