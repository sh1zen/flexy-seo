<?php

namespace FlexySEO\Engine\Generators\Schema\Graphs;

/**
 * Person Author graph class.
 *
 * This a secondary Person graph for post authors and BuddyPress profile pages.
 *
 * @since 1.2.0
 */
class PersonAuthor extends Person
{
    /**
     * Returns the graph data.
     * @return array $data The graph data.
     * @since 1.2.0
     */
    public function get($type = '')
    {
        $post = wpfseo()->currentPage->get_post();

        if (!$post) {
            return [];
        }

        $userId = $post->post_author;
        if (function_exists('bp_is_user') && bp_is_user()) {
            $userId = intval(wp_get_current_user()->ID);
        }

        if (!$userId) {
            return [];
        }

        $authorUrl = get_author_posts_url($post->post_author);

        $data = [
            '@type' => 'Person',
            '@id'   => $authorUrl . '#author',
            'url'   => $authorUrl,
            'name'  => get_the_author_meta('display_name', $userId)
        ];

        $avatar = $this->avatar($userId, 'authorImage');
        if ($avatar) {
            $data['image'] = $avatar;
        }

        $socialUrls = $this->socialUrls($userId);
        if ($socialUrls) {
            $data['sameAs'] = $socialUrls;
        }

        if (is_author()) {
            $data['mainEntityOfPage'] = [
                '#id' => $this->generator->get_permalink() . '#profilepage'
            ];
        }
        return $data;
    }
}