<?php

namespace FlexySEO\Engine\Generators\Schema\Graphs;


/**
 * Person graph class.
 *
 * This is the main Person graph that can be set to represent the site.
 *
 * @since 1.2.0
 */
class Person extends Graph
{
    /**
     * Returns the graph data.
     *
     * @return array $data The graph data.
     * @since 1.2.0
     */
    public function get($type = '')
    {
        if (shzn('wpfs')->settings->get('seo.schema.organization', false)) {
            return [];
        }

        $person = shzn('wpfs')->settings->get('seo.schema.person', false);
        if ('manual' === $person) {
            return $this->manual();
        }

        $person = intval($person);
        if (empty($person)) {
            return [];
        }

        $data = [
            '@type' => 'Person',
            '@id'   => shzn()->utility->home_url . '#person',
            'name'  => get_the_author_meta('display_name', $person)
        ];

        $avatar = $this->avatar($person, 'personImage');
        if ($avatar) {
            $data['image'] = $avatar;
        }

        $socialUrls = $this->socialUrls($person);
        if ($socialUrls) {
            $data['sameAs'] = $socialUrls;
        }
        return $data;
    }

    /**
     * Returns the data for the person if it is set manually.
     *
     * @return array $data The graph data.
     * @since 1.2.0
     */
    private function manual()
    {
        $data = [
            '@type' => 'Person',
            '@id'   => shzn()->utility->home_url . '#person',
            'name'  => shzn('wpfs')->settings->get('seo.schema.person.name', '')
        ];

        $logo = shzn('wpfs')->settings->get('seo.schema.person.logoURL', '');
        if ($logo) {
            $data['image'] = $logo;
        }

        $socialUrls = $this->socialUrls();
        if ($socialUrls) {
            $data['sameAs'] = $socialUrls;
        }

        return $data;
    }
}