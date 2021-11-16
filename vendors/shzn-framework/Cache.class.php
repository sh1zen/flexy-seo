<?php

namespace SHZN\core;

class Cache
{
    private $use_wp_cache;

    private $cache = array();

    public function __construct($use_wp_cache = false)
    {
        $this->use_wp_cache = $use_wp_cache;
    }

    public function get_cache($key, $group = 'core', $default = false)
    {
        if ($this->use_wp_cache)
            return wp_cache_get($key, $group);

        if ($this->cache_exists($key, $group)) {
            if (is_object($this->cache[$group][$key])) {
                return clone $this->cache[$group][$key];
            }
            else {
                return $this->cache[$group][$key];
            }
        }

        return $default;
    }

    public function set_cache($key, $data, $group = 'core', $force = false)
    {
        if ($this->use_wp_cache)
            return wp_cache_add($key, $data, $group);

        if (!$force and $this->cache_exists($key, $group))
            return false;

        return $this->force_cache($key, $data, $group);
    }

    private function cache_exists($key, $group)
    {
        return isset($this->cache[$group]) and (isset($this->cache[$group][$key]) or array_key_exists($key, $this->cache[$group]));
    }

    public function force_cache($key, $data, $group)
    {
        if ($this->use_wp_cache)
            return wp_cache_set($key, $data, $group);

        if (is_object($data)) {
            $data = clone $data;
        }

        $this->cache[$group][$key] = $data;

        return true;
    }

    public function dump_cache($group = 'core')
    {
        if ($this->use_wp_cache)
            return null;

        if (empty($group))
            return $this->cache;

        if (is_object($this->cache[$group])) {
            return clone $this->cache[$group];
        }
        else {
            return $this->cache[$group];
        }
    }

    public function delete_cache($key, $group)
    {
        if ($this->use_wp_cache)
            wp_cache_delete($key, $group);

        if (!$this->cache_exists($key, $group)) {
            return false;
        }

        unset($this->cache[$group][$key]);

        return true;
    }
}