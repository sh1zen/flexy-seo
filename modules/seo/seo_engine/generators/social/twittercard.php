<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators;
/**
 * Represents the generator class for the Twitter images.
 */
class TwitterCard
{
    /**
     * The version number
     */
    const VERSION = '1.0.0';

    private array $tags;

    private bool $validate;

    /**
     * Constructor call
     * @param bool $validate
     */
    public function __construct(bool $validate = false)
    {
        $this->tags = array();

        $this->validate = $validate;
    }

    public function add_card($card): bool
    {
        if ($this->validate and !in_array($card, ['summary_large_image', 'summary'])) {
            return false;
        }

        return $this->add_tag('card', $card);
    }

    public function add_tag($name, $value): bool
    {
        $this->tags["twitter:" . $name] = $value;
        return true;
    }

    public function add_creator($creator): bool
    {
        return $this->add_tag('creator', $creator);
    }

    public function add_site($site): bool
    {
        return $this->add_tag('site', $site);
    }

    public function add_title($title): bool
    {
        return $this->add_tag('title', $title);
    }

    public function get_tags(): array
    {
        return $this->tags;
    }

    public function add_description($description): bool
    {
        return $this->add_tag('description', $description);
    }

    public function add_image($image_url): bool
    {
        return $this->add_tag('image', $image_url);
    }
}
