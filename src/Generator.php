<?php
namespace StevieRay;

use Mso\IdnaConvert\IdnaConvert;
use RuntimeException;
use StevieRay\Format\ApacheFormat;
use StevieRay\Format\NginxFormat;

class Generator
{
    private $projectUrl = "https://github.com/Stevie-Ray/referrer-spam-blocker";

    /** @var string string */
    private $outputDir;

    /** @var IdnaConvert */
    private $idnaConverter;

    /**
     * @param string      $outputDir
     * @param IdnaConvert $idnaConvert
     */
    public function __construct($outputDir, IdnaConvert $idnaConvert)
    {
        $this->outputDir = $outputDir;
        $this->idnaConverter = $idnaConvert;
    }

    public function generateFiles()
    {
        $date = date('Y-m-d H:i:s');
        $lines = $this->processDomainList();
        $this->createApache($date, $lines);
        $this->createNginx($date, $lines);
        $this->createVarnish($date, $lines);
        $this->createIIS($date, $lines);
        $this->createuWSGI($date, $lines);
        $this->createGoogleExclude($lines);
    }

    /**
     * @return array
     * @throws RuntimeException
     */
    protected function processDomainList()
    {
        $domainsFile = __DIR__ . '/domains.txt';

        $handle = fopen($domainsFile, 'rb');

        if (! $handle) {
            throw new RuntimeException('Error opening file ' . $domainsFile);
        }

        $lines = array();
        while (($line = fgets($handle)) !== false) {
            $line = mb_strtolower(trim(preg_replace('/\s+/u', '', $line)), 'UTF-8');

            // convert internationalized domain names
            if ($this->isUnicode($line)) {
                $line = $this->idnaConverter->encode($line);
            }

            if (empty($line)) {
                continue;
            }
            $lines[] = $line;
        }

        fclose($handle);
        $uniqueLines = array_unique($lines, SORT_STRING);
        sort($uniqueLines, SORT_STRING);

        if (!is_writable($domainsFile)) {
            trigger_error("Permission denied");
        }

        file_put_contents($domainsFile, implode("\n", $uniqueLines));
        return $uniqueLines;
    }

    /**
     * @param $line
     * @return bool
     */
    protected function isUnicode($line)
    {
        return strlen($line) !== mb_strlen($line, 'UTF-8');
    }

    /**
     * @param string $date
     * @param array  $domains
     */
    public function createApache($date, array $domains)
    {
        $apacheFormat = new ApacheFormat();


        $noOfLines = count($domains) - 1;
        $rewriteRules = $envVars = '';
        foreach ($domains as $k => $domain) {
            $domain = $this->escape($domain);
            $rewriteRules .= $apacheFormat->createRewriteRule($domain, $k === $noOfLines);
            $envVars .= $apacheFormat->createSetEnv($domain);
        }

        $data = $apacheFormat->getHeader($this->projectUrl, $date)
            . $rewriteRules
            . ApacheFormat::REWRITE_RULE
            . $envVars
            . $apacheFormat->getFooter();

        $this->writeToFile('.htaccess', $data);
    }

    /**
     * @param string $filename
     * @param string $data
     */
    protected function writeToFile($filename, $data)
    {
        $file = $this->outputDir . '/' . $filename;
        if (is_writable($file)) {
            file_put_contents($file, $data);
            if (! chmod($file, 0644)) {
                trigger_error("Couldn't not set " . $filename . " permissions to 644");
            }
        } else {
            trigger_error("Permission denied");
        }
    }

    /**
     * @param string $date
     * @param array  $domains
     */
    public function createNginx($date, array $domains)
    {
        $nginxFormat = new NginxFormat();
        $data = $nginxFormat->getHeader($this->projectUrl, $date);

        foreach ($domains as $domain) {
            $data .= $nginxFormat->createDirective($this->escape($domain));
        }
        $data .= $nginxFormat->getFooter();

        $this->writeToFile('referral-spam.conf', $data);
    }

    /**
     * @param string $date
     * @param array  $lines
     */
    public function createVarnish($date, array $lines)
    {
        $data = "# " . $this->projectUrl . "\n# Updated " . $date . "\nsub block_referral_spam {\n\tif (\n";
        foreach ($lines as $line) {
            if ($line === end($lines)) {
                $data .= "\t\treq.http.Referer ~ \"(?i)" . $this->escape($line) . "\"\n";
                break;
            }

            $data .= "\t\treq.http.Referer ~ \"(?i)" . $this->escape($line) . "\" ||\n";
        }

        $data .= "\t) {\n\t\t\treturn (synth(444, \"No Response\"));\n\t}\n}";

        $this->writeToFile('referral-spam.vcl', $data);
    }

    /**
     * @param string $date
     * @param array  $lines
     */
    public function createIIS($date, array $lines)
    {
        $data = "<!-- " . $this->projectUrl . " -->\n<!-- Updated " . $date . " -->\n" .
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
            "<configuration>\n\t<system.webServer>\n\t\t<rewrite>\n\t\t\t<rules>\n";
        foreach ($lines as $line) {

            $data .= "\t\t\t\t<rule name=\"Referrer Spam " . $line . "\" stopProcessing=\"true\">" .
                "<match url=\".*\" /><conditions><add input=\"{HTTP_REFERER}\" pattern=\"(" .
                $this->escape($line) .
                ")\"/></conditions><action type=\"AbortRequest\" /></rule>\n";
        }

        $data .= "\t\t\t</rules>\n\t\t</rewrite>\n\t</system.webServer>\n</configuration>";

        $this->writeToFile('web.config', $data);
    }

    /**
     * @param string $date
     * @param array  $lines
     */
    public function createuWSGI($date, array $lines)
    {
        $data = "# " . $this->projectUrl . "\n# Updated " . $date . "\n#\n" .
            "# Put referral-spam.res in /path/to/vassals, then include it from within /path/to/vassals/vassal.ini:\n" .
            "#\n# ini = referral_spam.res:blacklist_spam\n\n" .
            "[blacklist_spam]\n";
        foreach ($lines as $line) {
            $data .= "route-referer = (?i)" . $this->escape($line) . " break:403 Forbidden\n";
        }
        $data .= "route-label = referral_spam";

        $this->writeToFile('referral_spam.res', $data);
    }

    /**
     * @param array $lines
     */
    public function createGoogleExclude(array $lines)
    {

        $regexLines = [];

        foreach ($lines as $line) {
            $regexLines[] = $this->escape($line);
        }
        $data = implode('|', $regexLines);

        $googleLimit = 30000;
        $dataLength = strlen($data);

        // keep track of the last split
        $lastPosition = 0;
        for ($x = 1; $lastPosition < $dataLength; $x++) {

            // already in the boundary limits?
            if (($dataLength - $lastPosition) >= $googleLimit) {
                // search for the last occurrence of | in the boundary limits
                $pipePosition = strrpos(substr($data, $lastPosition, $googleLimit), '|');

                $dataSplit = substr($data, $lastPosition, $pipePosition);

                // without trailing pipe at the beginning of next round
                $lastPosition = $lastPosition + $pipePosition + 1;
            } else {
                // Rest of the regex (no pipe at the end)
                $dataSplit = substr($data, $lastPosition);
                $lastPosition = $dataLength; // Break
            }

            $this->writeToFile('google-exclude-' . $x . '.txt', $dataSplit);
        }

    }

    /**
     * @param string $line
     * @param string $delimiterChar
     * @return string string
     */
    protected function escape($line, $delimiterChar = '/')
    {
        return preg_quote($line, $delimiterChar);
    }
}