<?php
namespace StevieRay\Format;

class ApacheFormat implements FormatInterface
{
    const FLAG_CASE_INSENSITIVE = 'NC';
    const FLAG_OR = 'OR';
    const REWRITE_CONDITION_TEMPLATE = 'RewriteCond %{HTTP_REFERER} ^http(s)?://(www.)?.*%domain%.*$ [%flag%]';
    const REWRITE_RULE = "RewriteRule ^(.*)$ – [F,L]\n\n</IfModule>\n\n<IfModule mod_setenvif.c>\n\n";

    const SET_ENV_TEMPLATE = 'SetEnvIfNoCase Referer %s spambot=yes';

    public function getHeader($projectUrl, $date)
    {
        return <<<HTACCESS
# $projectUrl
# Updated $date

<IfModule mod_rewrite.c>

RewriteEngine On

HTACCESS;
    }

    public function createRewriteRule($domain, $isLast = false)
    {
        return strtr(
                static::REWRITE_CONDITION_TEMPLATE,
                [
                    '%domain%' => $domain,
                    '%flag%' => $this->getFlags($isLast),
                ]
            ) . "\n";
    }

    /**
     * @param bool $isLast
     * @return string
     */
    private function getFlags($isLast)
    {
        $flags = [static::FLAG_CASE_INSENSITIVE];
        if (! $isLast) {
            $flags[] = static::FLAG_OR;
        }

        return implode(',', $flags);
    }

    public function getFooter()
    {
        return <<<HTACCESS

</IfModule>

# Apache 2.2
<IfModule !mod_authz_core.c>
    <IfModule mod_authz_host.c>
        Order allow,deny
        Allow from all
        Deny from env=spambot
    </IfModule>
</IfModule>
# Apache 2.4
<IfModule mod_authz_core.c>
    <RequireAll>
        Require all granted
        Require not env spambot
    </RequireAll>
</IfModule>
HTACCESS;
    }

    public function createSetEnv($domain)
    {
        return sprintf(self::SET_ENV_TEMPLATE, $domain) . "\n";
    }
}