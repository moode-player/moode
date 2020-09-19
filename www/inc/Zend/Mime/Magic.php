<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Mime
 * @subpackage Magic
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Magic.php 193 2010-04-08 14:46:41Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Io/FileReader.php';
/**#@-*/

/**
 * This class is used to classify the given file using some magic bytes
 * characteristic to a particular file type. The classification information can
 * be a MIME type or just text describing the file.
 *
 * This method is slower than determining the type by file suffix but on the
 * other hand reduces the risk of fail positives during the test.
 *
 * The magic file consists of ASCII characters defining the magic numbers for
 * different file types. Each row has 4 to 5 columns, empty and commented lines
 * (those starting with a hash character) are ignored. Columns are described
 * below.
 *
 *  o <b>1</b> -- byte number to begin checking from. '>' indicates a dependency
 *    upon the previous non-'>' line
 *  o <b>2</b> -- type of data to match. Can be one of following
 *    - <i>byte</i> (single character)
 *    - <i>short</i> (machine-order 16-bit integer)
 *    - <i>long</i> (machine-order 32-bit integer)
 *    - <i>string</i> (arbitrary-length string)
 *    - <i>date</i> (long integer date (seconds since Unix epoch/1970))
 *    - <i>beshort</i> (big-endian 16-bit integer)
 *    - <i>belong</i> (big-endian 32-bit integer)
 *    - <i>bedate</i> (big-endian 32-bit integer date)
 *    - <i>leshort</i> (little-endian 16-bit integer)
 *    - <i>lelong</i> (little-endian 32-bit integer)
 *    - <i>ledate</i> (little-endian 32-bit integer date)
 *  o <b>3</b> -- contents of data to match
 *  o <b>4</b> -- file description/MIME type if matched
 *  o <b>5</b> -- optional MIME encoding if matched and if above was a MIME type
 *
 * @category   Zend
 * @package    Zend_Mime
 * @subpackage Magic
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Magic.php 193 2010-04-08 14:46:41Z svollbehr $
 */
final class Zend_Mime_Magic
{
    /** @var string */
    private $_magic;

    /**
     * Reads the magic information from given magic file.
     *
     * @param string $filename The path to the magic file.
     */
    public function __construct($filename)
    {
        $reader = new Zend_Io_FileReader($filename);
        $this->_magic = $reader->read($reader->getSize());
    }

    /**
     * Returns the recognized MIME type/description of the given file. The type
     * is determined by the content using magic bytes characteristic for the
     * particular file type.
     *
     * If the type could not be found, the function returns the default value,
     * or <var>null</var>.
     *
     * @param string $filename The file path whose type to determine.
     * @param string $default  The default value.
     * @return string|false
     */
    public function getMimeType($filename, $default = null)
    {
        $reader = new Zend_Io_FileReader($filename);

        $parentOffset = 0;
        foreach (preg_split('/^/m', $this->_magic) as $line) {
            $chunks = array();
            if (!preg_match("/^(?P<Dependant>>?)(?P<Byte>\d+)\s+(?P<MatchType" .
                            ">\S+)\s+(?P<MatchData>\S+)(?:\s+(?P<MIMEType>[a-" .
                            "z]+\/[a-z-0-9]+)?(?:\s+(?P<Description>.?+))?)?$/",
                            $line, $chunks)) {
                continue;
            }

            if ($chunks['Dependant']) {
                $reader->setOffset($parentOffset);
                $reader->skip($chunks['Byte']);
            } else {
                $reader->setOffset($parentOffset = $chunks['Byte']);
            }

            $matchType = strtolower($chunks['MatchType']);
            $matchData = preg_replace
                (array("/\\\\ /", "/\\\\\\\\/", "/\\\\([0-7]{1,3})/e",
                       "/\\\\x([0-9A-Fa-f]{1,2})/e", "/0x([0-9A-Fa-f]+)/e"),
                 array(" ", "\\\\",
                       "pack(\"H*\", base_convert(\"$1\", 8, 16));",
                       "pack(\"H*\", \"$1\");", "hexdec(\"$1\");"),
                 $chunks["MatchData"]);

            switch ($matchType) {
                case 'byte':    // single character
                    $data = $reader->readInt8();
                    break;
                case 'short':   // machine-order 16-bit integer
                    $data = $reader->readInt16();
                    break;
                case 'long':    // machine-order 32-bit integer
                    $data = $reader->readInt32();
                    break;
                case 'string':  // arbitrary-length string
                    $data = $reader->readString8(strlen($matchData));
                    break;
                case 'date':    // long integer date (seconds since Unix epoch)
                    $data = $reader->readInt64BE();
                    break;
                case 'beshort': // big-endian 16-bit integer
                    $data = $reader->readUInt16BE();
                    break;
                case 'belong':  // big-endian 32-bit integer
                    // break intentionally omitted
                case 'bedate':  // big-endian 32-bit integer date
                    $data = $reader->readUInt32BE();
                    break;
                case 'leshort': // little-endian 16-bit integer
                    $data = $reader->readUInt16LE();
                    break;
                case 'lelong':  // little-endian 32-bit integer
                    // break intentionally omitted
                case 'ledate':  // little-endian 32-bit integer date
                    $data = $reader->readUInt32LE();
                    break;
                default:
                    $data = null;
                    break;
            }

            if (strcmp($data, $matchData) == 0) {
                if (!empty($chunks['MIMEType'])) {
                    return $chunks['MIMEType'];
                }
                if (!empty($chunks['Description'])) {
                    return rtrim($chunks['Description'], "\n");
                }
            }
        }
        return $default;
    }

    /**
     * Returns the results of the mime type check either as a boolean or an
     * array of boolean values.
     *
     * @param string|Array $filename The file path whose type to test.
     * @param string|Array $mimeType The mime type to test against.
     * @return boolean|Array
     */
    public function isMimeType($filename, $mimeType)
    {
        if (is_array($filename)) {
            $result = array();
            foreach ($filename as $key => $value) {
                $result[] =
                    ($this->getMimeType($value) ==
                     (is_array($mimeType) ? $mimeType[$key] : $mimeType)) ?
                    true : false;
            }
            return $result;
        } else {
            return $this->getMimeType($filename) == $mimeType ? true : false;
        }
    }
}
