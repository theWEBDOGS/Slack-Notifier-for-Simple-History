<?php

if (!function_exists('wrap_each')) {
    /**
     * Wrap each value in an array of strings.
     *
     * @param	array	$array
     * @param	string	$prepend_string	string to prepend 
     * @param	string	$append_string	string to append
     * @return	array	new values
     */
    function wrap_each(array $array, $prepend_string = '', $append_string = '')
    {
        $new_array = [];
        foreach ($array as $key => $string) {
            $new_array[$key] = (string) $prepend_string . (string) $string . (string) $append_string;
        }
        return $new_array;
    }
}

if (!function_exists('array_md5')) {
    /**
     * Return the md5 hash string for multi-dimensional array.
     *
     * @param	array	$array
     * @param	string	$prepend_string	additional string to prepend for md5 hash
     * @param	string	$append_string	additional string to append for md5 hash
     * @param	boolean	$use_serialize	option toString method using serialize
     * @return	string	md5 hash
     */
    function array_md5(array $array, $prepend_string = '', $append_string = '', bool $use_serialize = false)
    {
        array_multisort($array);
        $array_string = $use_serialize ? serialize($array) : json_encode($array);
        return md5($prepend_string . $array_string . $append_string);
    }
}
