<?php
namespace StevieRay\Format;

class NginxFormat extends AbstractFormat
{
    const DIRECTIVE_TEMPLATE = '    "~*%s" 1;';

    public function getHeader($projectUrl, $date)
    {
        return <<<CONF
# $projectUrl
# Updated $date
#
# /etc/nginx/referral-spam.conf
#
# With referral-spam.conf in /etc/nginx, include it globally from within /etc/nginx/nginx.conf:
#
# include referral-spam.conf;
#
# Add the following to each /etc/nginx/site-available/your-site.conf that needs protection:
#
# server {
#     if (\$bad_referer) {
#         return 444;
#     }
# }
#
map \$http_referer \$bad_referer {
    default 0;


CONF;

    }

    public function createDirective($domain)
    {
        return sprintf(self::DIRECTIVE_TEMPLATE, $this->escape($domain)) . "\n";
    }

    public function getFooter(){
        return '}';
    }
}