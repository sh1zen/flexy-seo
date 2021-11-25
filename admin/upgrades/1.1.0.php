<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

shzn('wpfs')->settings->reset();

SHZN\core\UtilEnv::create_db("flexy_seo", [
        "fields"      => [
            "id"      => "bigint NOT NULL AUTO_INCREMENT",
            "obj_id"  => "varchar(255)",
            "context" => "varchar(255)",
            "item"    => "varchar(255)",
            "value"   => "longtext NOT NULL",
        ],
        "primary_key" => "id"
    ]
);