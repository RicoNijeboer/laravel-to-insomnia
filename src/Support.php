<?php

if ( ! function_exists('generate_insomnia_id'))
{
    /**
     * @param string $prefix
     *
     * @return string
     */
    function generate_insomnia_id(string $prefix = ''): string
    {
        return str_replace('.', '', uniqid($prefix, true)) . uniqid();
    }
}