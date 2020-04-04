<?php
/**
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author Constantin Galbenu <xprt64@gmail.com>
 * @author Chris Kruining <chris@gmailkruining.eu>
 */

/**
 * @see https://github.com/Mark-H/Docs/blob/2.x/convert/util/TableConverter.php
 */

namespace xprt64\HtmlTableToMarkdownConverter;

use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\ElementInterface;

class TableConverter implements ConverterInterface
{
    /**
     * @param ElementInterface $element
     *
     * @return string
     */
    public function convert(ElementInterface $element)
    {
        switch ($element->getTagName()) {
            case 'tr':
                return sprintf(
                    "| %s |\n",
                    implode(' | ', array_map(function($td){ return trim($td->getValue()); }, $element->getChildren()))
                );

            case 'td':
            case 'th':
                return preg_replace("#\n+#", '\n', trim($element->getValue()));

            case 'tbody':
                return trim($element->getValue());

            case 'thead':
                $children = $element->getChildren();
                $headerLine = reset($children)->getValue();
                $headers = explode(' | ', trim(trim($headerLine, "\n"), '|'));

                $hr = [];
                foreach ($headers as $td) {
                    $length = strlen(trim($td)) + 2;
                    $hr[] = str_repeat('-', $length > 3 ? $length : 3);
                }
                $hr = '|' . implode('|', $hr) . '|';

                return $headerLine . $hr . "\n";
            case 'table':
                $inner = $element->getValue();
                $data = array_map(
                    function($r){
                        return array_slice(array_map(
                            function($r){ return preg_match('/^\-+$/', $r) ? '-' : trim($r); },
                            explode('|', $r)
                        ), 1, -1);
                    },
                    explode("\n", $inner)
                );
                $size = count($data[0]);

                for($i = 0; $i < $size; $i++)
                {
                    $width = max(array_map(function($r) use($i){ return mb_strlen($r[$i] ?? ''); }, $data));

                    foreach($data as &$row)
                    {
                        if($width < 2)
                        {
                            unset($row[$i]);
                        }
                        else
                        {
                            $cell = $row[$i] ?? '';

                            $format = $cell === '-'
                                ? '%\'-' . $width . 's'
                                : '%-' . $width . 's';

                            $row[$i] = $this->mb_sprintf($format, $cell);
                        }
                    }

                    unset($row);
                }

                $inner = join("\n", array_map(function($r){ return sprintf('| %s |', join(' | ', $r)); }, $data));
                return trim($inner) . "\n\n";
        }

        return $element->getValue();
    }

    /**
     * @return string[]
     */
    public function getSupportedTags()
    {
        return array('table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th');
    }

    /**
     * @see https://www.php.net/manual/en/function.sprintf.php#89020
     */
    protected function mb_sprintf($format) {
        $argv = func_get_args() ;
        array_shift($argv) ;
        return $this->mb_vsprintf($format, $argv) ;
    }

    /**
     * @see https://www.php.net/manual/en/function.sprintf.php#89020
     *
     * Works with all encodings in format and arguments.
     * Supported: Sign, padding, alignment, width and precision.
     * Not supported: Argument swapping.
     */
    protected function mb_vsprintf($format, $argv, $encoding=null) {
        if (is_null($encoding))
            $encoding = mb_internal_encoding();

        // Use UTF-8 in the format so we can use the u flag in preg_split
        $format = mb_convert_encoding($format, 'UTF-8', $encoding);

        $newformat = ""; // build a new format in UTF-8
        $newargv = array(); // unhandled args in unchanged encoding

        while ($format !== "") {

            // Split the format in two parts: $pre and $post by the first %-directive
            // We get also the matched groups
            list ($pre, $sign, $filler, $align, $size, $precision, $type, $post) =
                preg_split("!\%(\+?)('.|[0 ]|)(-?)([1-9][0-9]*|)(\.[1-9][0-9]*|)([%a-zA-Z])!u",
                    $format, 2, PREG_SPLIT_DELIM_CAPTURE) ;

            $newformat .= mb_convert_encoding($pre, $encoding, 'UTF-8');

            if ($type == '') {
                // didn't match. do nothing. this is the last iteration.
            }
            elseif ($type == '%') {
                // an escaped %
                $newformat .= '%%';
            }
            elseif ($type == 's') {
                $arg = array_shift($argv);
                $arg = mb_convert_encoding($arg, 'UTF-8', $encoding);
                $padding_pre = '';
                $padding_post = '';

                // truncate $arg
                if ($precision !== '') {
                    $precision = intval(substr($precision,1));
                    if ($precision > 0 && mb_strlen($arg,$encoding) > $precision)
                        $arg = mb_substr($precision,0,$precision,$encoding);
                }

                // define padding
                if ($size > 0) {
                    $arglen = mb_strlen($arg, $encoding);
                    if ($arglen < $size) {
                        if($filler==='')
                            $filler = ' ';
                        if ($align == '-')
                            $padding_post = str_repeat($filler, $size - $arglen);
                        else
                            $padding_pre = str_repeat($filler, $size - $arglen);
                    }
                }

                // escape % and pass it forward
                $newformat .= $padding_pre . str_replace('%', '%%', $arg) . $padding_post;
            }
            else {
                // another type, pass forward
                $newformat .= "%$sign$filler$align$size$precision$type";
                $newargv[] = array_shift($argv);
            }
            $format = strval($post);
        }
        // Convert new format back from UTF-8 to the original encoding
        $newformat = mb_convert_encoding($newformat, $encoding, 'UTF-8');
        return vsprintf($newformat, $newargv);
    }
}
