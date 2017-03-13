<?php
namespace StevieRay\Format;

class VarnishFormat extends AbstractFormat
{
    const FLAG_OR = '||';
    const RULE_TEMPLATE = '      req.http.Referer ~ "(?i)%domain%" %flags%';

    public function getHeader($projectUrl, $date)
    {
        return <<<VCL
# $projectUrl
# Updated $date
sub block_referral_spam {
    if (

VCL;

    }

    public function createRule($domain, $isLast = false)
    {
        return strtr(
                self::RULE_TEMPLATE,
                [
                    '%domain%' => $this->escape($domain),
                    '%flags%' => $this->getFlags($isLast),
                ]
            ) . "\n";
    }

    private function getFlags($isLast)
    {
        return $isLast ? '' : static::FLAG_OR;
    }

    public function getFooter()
    {
        return <<<VCL
    ) {
            return (synth(444, "No Response"));
    }
}
VCL;

    }

}