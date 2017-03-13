<?php
namespace StevieRay\Format;

class IisFormat extends AbstractFormat
{
    public function getHeader($projectUrl, $date)
    {
        /** @lang XML */
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!-- $projectUrl -->
<!-- Updated $date -->
<configuration>
    <system.webServer>
        <rewrite>
            <rules>

XML;
    }

    public function createRule($domain)
    {
        $escapedDomain = $this->escape($domain);
        $name = htmlspecialchars($domain, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        /** @lang XML */
        return <<<XML
                <rule name="Referrer Spam $name" stopProcessing="true">
                    <match url=".*"/><conditions><add input="{HTTP_REFERER}" pattern="($escapedDomain)"/></conditions><action type="AbortRequest"/>
                </rule>

XML;
    }

    public function getFooter()
    {
        return <<<XML
            </rules>
        </rewrite>
    </system.webServer>
</configuration>
XML;
    }

}