<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

class StringHelper
{
    /**
     * Convert to snake case.
     *
     * @param \string $string The string to convert.
     * @return \string         The converted string.
     * @since 1.2.0
     *
     */
    public function toSnakeCase($string)
    {
        $string[0] = strtolower($string[0]);
        return \preg_replace_callback('/([A-Z])/', function ($value) {
            return '_' . strtolower($value[1]);
        }, $string);
    }

    /**
     * Convert to camel case.
     *
     * @param \string $string The string to convert.
     * @param bool $capitalize Whether or not to capitalize the first letter.
     * @return \string             The converted string.
     * @since 1.2.0
     *
     */
    public function toCamelCase($string, $capitalize = false)
    {
        $string[0] = strtolower($string[0]);
        if ($capitalize) {
            $string[0] = strtoupper($string[0]);
        }
        return preg_replace_callback('/_([a-z0-9])/', function ($value) {
            return strtoupper($value[1]);
        }, $string);
    }

    /**
     * Converts kebab case to camel case.
     *
     * @param \string $string The string to convert.
     * @param bool $capitalizeFirstCharacter
     * @return \string             The converted string.
     * @since 1.2.0
     */
    public function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
    {
        $string = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));
        if (!$capitalizeFirstCharacter) {
            $string[0] = strtolower($string[0]);
        }
        return $string;
    }

    /**
     * Truncates a given string.
     *
     * @param \string $string The string.
     * @param int $maxCharacters The max. amount of characters.
     * @param boolean $shouldHaveEllipsis Whether the string should have a trailing ellipsis (defaults to true).
     * @return \string  $string             The string.
     * @since 1.2.0
     *
     */
    public function truncate($string, $maxCharacters, $shouldHaveEllipsis = true)
    {
        $length = strlen($string);
        $excessLength = $length - $maxCharacters;
        if (0 < $excessLength) {
            // If the string is longer than 65535 characters, we first need to shorten it due to the character limit of the regex pattern quantifier.
            if (65535 < $length) {
                $string = substr($string, 0, 65534);
            }
            $string = preg_replace("#[^\pZ\pP]*.{{$excessLength}}$#", '', $string);
            if ($shouldHaveEllipsis) {
                $string = $string . ' ...';
            }
        }
        return $string;
    }

    /**
     * Escapes special regex characters.
     *
     * @param \string $string The string.
     * @return \string         The escaped string.
     * @since 1.2.0
     *
     */
    public function escapeRegex($string)
    {
        return preg_quote($string, '/');
    }

    /**
     * preg_replace but with the replacement escaped.
     *
     * @param \string $pattern The pattern to search for.
     * @param \string $replacement The replacement string.
     * @param \string $subject The subject to search in.
     * @return \string              The subject with matches replaced.
     * @since 1.2.0
     *
     */
    public function pregReplace($pattern, $replacement, $subject)
    {
        $replacement = $this->escapeRegexReplacement($replacement);
        return preg_replace($pattern, $replacement, $subject);
    }

    /**
     * Escapes special regex characters inside the replacement string.
     *
     * @param \string $string The string.
     * @return \string         The escaped string.
     * @since 1.2.0
     *
     */
    public function escapeRegexReplacement($string)
    {
        return str_replace('$', '\$', $string);
    }

    /**
     * Returns string after converting it to lowercase.
     *
     * @param \string $string The original string.
     * @return \string         The string converted to lowercase.
     * @since 1.2.0
     *
     */
    public function toLowercase($string)
    {
        return function_exists('mb_strtolower') ? \mb_strtolower($string, get_option('blog_charset')) : \strtolower($string);
    }

    /**
     * Checks if the given string contains the given substring.
     *
     * @param \string $stack The stack.
     * @param \string $needle The needle.
     * @param int $offset The offset.
     * @return int|bool         The index of the first occurence or false.
     * @since 1.2.0.2
     *
     */
    public function stringContains($stack, $needle, $offset = 0)
    {
        return function_exists('mb_strpos') ? mb_strpos($stack, $needle, $offset, get_option('blog_charset')) : strpos($stack, $needle, $offset);
    }

    /**
     * Check if a string is JSON encoded or not.
     *
     * @param \string $string The string to check.
     * @return bool           True if it is JSON or false if not.
     * @since 1.2.0
     *
     */
    public function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);

        // Return a boolean whether or not the last error matches.
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Strips punctuation from a given string.
     *
     * @param \string $string The string.
     * @return \string         The string without punctuation.
     * @since 1.2.0
     *
     */
    public function stripPunctuation($string)
    {
        $string = preg_replace('#\p{P}#u', '', $string);
        // Trim both internal and external whitespace.
        return preg_replace('/\s\s+/u', ' ', trim($string));
    }

    /**
     * Returns the string after it is encoded with htmlspecialchars().
     *
     * @param \string $string The \string to encode.
     * @return \string         The encoded string.
     * @since 1.2.0
     *
     */
    public function encodeOutputHtml($string)
    {
        return htmlspecialchars($string, ENT_COMPAT | ENT_HTML401, get_option('blog_charset'), false);
    }

    /**
     * Returns the string after all HTML entities have been decoded.
     *
     * @param \string $string The string to decode.
     * @return \string         The decoded string.
     * @since 1.2.0
     *
     */
    public function decodeHtmlEntities($string)
    {
        return html_entity_decode((string)$string, ENT_QUOTES);
    }
}