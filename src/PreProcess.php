<?php

namespace Neosmic\ArangoPhpOgm;

class PreProcess
{

    public function __construct()
    {
    }
    static function addPagination(int $page = 0, int $perPage = 6)
    {
        return " LIMIT $page, $perPage ";
    }
    static function filterOr($values = [], string $property, $variable = 'd')
    {
        $values = (is_string($values)) ? [$values] : $values;
        if ($values == []) {
            return "";
        } else {
            $out = " FILTER ";
            foreach ($values as $key => $value) {
                $or =  ($key == 0) ? '' : ' || ';
                $out .= $variable . $property . " == " . $value . $or . " ";
            }
            return $out;
        }
    }
}
