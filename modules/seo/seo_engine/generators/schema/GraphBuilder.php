<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators;

class GraphBuilder
{
    private array $graph = [];

    public function __construct($type = [], $id = '')
    {
        if (is_array($type)) {
            $this->graph = $type;
        }
        else {

            $this->graph['@type'] = $type;

            if (is_array($id)) {
                $this->graph = array_merge($this->graph, $id);
            }
            else {
                $this->graph['@id'] = $id;
            }
        }
    }

    public function remove($name, $value = null): void
    {
        if ($value) {
            if (($key = array_search($value, $this->graph[$name])) !== false) {
                unset($this->graph[$name][$key]);
            }
        }
        else {
            unset($this->graph[$name]);
        }
    }

    public function add($name, $value): void
    {
        $value = array_filter($value);

        if (!isset($this->graph[$name])) {
            $this->graph[$name] = array();
        }

        $this->graph[$name][] = $value;
    }

    public function get($name, $default = false, $value = null)
    {
        if ($value and ($key = array_search($value, $this->graph[$name])) !== false) {

            return $this->graph[$name][$key];

        }
        elseif (isset($this->graph[$name])) {
            return $this->graph[$name];
        }

        return $default;
    }

    public function set($name, $value): void
    {
        if ($value instanceof GraphBuilder) {
            $value = $value->export();
        }
        elseif (is_array($value)) {
            $value = array_filter($value);
        }

        if (empty($value)) {
            return;
        }

        $this->graph[$name] = $value;
    }

    public function export(): array
    {
        return array_filter($this->graph);
    }

    public function join($graph): void
    {
        if ($graph instanceof GraphBuilder) {
            $graph = $graph->export();
        }
        elseif (is_array($graph)) {
            $graph = array_filter($graph);
        }

        $this->graph = array_merge($this->graph, $graph);
    }

    public function empty(): void
    {
        $this->graph = [];
    }

    public function set_type($type): void
    {
        $this->graph['@type'] = $type;
    }
}