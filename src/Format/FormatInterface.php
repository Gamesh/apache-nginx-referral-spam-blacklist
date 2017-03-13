<?php
namespace StevieRay\Format;

interface FormatInterface
{
    public function getHeader($projectUrl, $date);

    public function getFooter();
}