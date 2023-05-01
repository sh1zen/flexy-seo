<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace SHZN\core\addon;

use SHZN\core\UtilEnv;

class Exporter
{
    private string $format = 'csv';

    private array $items = [];

    private string $parsed = '';

    public function __construct()
    {
    }

    public function format(string $format): Exporter
    {
        $this->format = strtolower($format);
        return $this;
    }

    public function data($items, $delimiter = "\n"): Exporter
    {
        if (!is_array($items)) {
            $items = explode($delimiter, $items);
        }

        $this->items = $items;

        return $this;
    }

    public function prepare(): Exporter
    {
        switch ($this->format) {
            case 'csv':
                $this->parsed .= implode(',', array_keys($this->items[0])) . "\n";

                foreach ($this->items as $item) {
                    $this->parsed .= implode(',', $item) . "\n";
                }
                break;

            case 'php_array':
                $this->parsed = var_export($this->items, true);
                break;

            case 'xml':
                $this->parsed = $this->convert_to_xml();
                break;

            case 'json':
                $this->parsed = json_encode($this->items);
                break;

            case 'serialized':
                $this->parsed = serialize($this->items);
                break;
        }

        return $this;
    }

    private function convert_to_xml($rootNodeName = 'root')
    {
        function arrayToXMLHelper($data, \SimpleXMLElement &$xml)
        {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    if (is_numeric($key)) {
                        $key = 'item' . $key; // Generate a unique item key if the array is numerically indexed
                    }
                    $subnode = $xml->addChild($key);
                    arrayToXMLHelper($value, $subnode);
                }
                else {
                    $xml->addChild($key, htmlspecialchars($value));
                }
            }
        }

        try {
            $xml = new \SimpleXMLElement('<' . $rootNodeName . '/>');
        } catch (\Exception $e) {
            return '';
        }

        arrayToXMLHelper($this->items, $xml);

        // Format the XML with proper indentation
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    public function export(): string
    {
        return $this->parsed;
    }

    public function download($filename)
    {
        switch ($this->format) {

            case 'php_array':
            case 'serialized':
                $extension = 'txt';
                $contentType = "text/txt";
                break;

            default:
                $extension = $this->format;
                $contentType = "text/$this->format";
                break;
        }

        $filename = UtilEnv::change_file_extension($filename, $extension);

        header('Expires: 0');
        header("Cache-Control: private");
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $contentType . '; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Content-Length: ' . strlen($this->parsed));

        echo $this->parsed;
        exit();
    }
}