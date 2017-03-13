<?php
namespace StevieRay\Format;

abstract class AbstractFormat implements FormatInterface
{
    protected function escape($domain, $delimiter = '/')
    {
        return preg_quote($domain, $delimiter);
    }
}