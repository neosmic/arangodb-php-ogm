<?php

namespace Neosmic\ArangoPhpOgm;

class PreProcess
{

    public function __construct()
    {
    }
    public static function addPagination(int $page = 0, int $perPage = 6)
    {
        return " LIMIT $page, $perPage ";
    }
    public static function filterOr($property, $values = [], $variable = 'd')
    {
        $values = (is_string($values)) ? [$values] : $values;
        if ($values == []) {
            return '';
        } else {
            $out = ' FILTER ';
            foreach ($values as $key => $value) {
                $or =  ($key == 0) ? '' : ' || ';
                $out .= $or . $variable . '.'  . $property . ' == \'' . $value . '\'' . ' ';
            }
            return $out;
        }
    }
}
