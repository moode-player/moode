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
 * @package    Zend_Media
 * @subpackage ID3
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Object.php 215 2011-04-30 10:37:09Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Io/Reader.php';
require_once 'Zend/Io/Writer.php';
/**#@-*/

/**
 * The base class for all ID3v2 objects.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Object.php 215 2011-04-30 10:37:09Z svollbehr $
 */
abstract class Zend_Media_Id3_Object
{
    /**
     * The reader object.
     *
     * @var Zend_Io_Reader
     */
    protected $_reader;

    /**
     * The options array.
     *
     * @var Array
     */
    private $_options;

    /**
     * Constructs the class with given parameters.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        $this->_reader = $reader;
        $this->_options = &$options;
    }

    /**
     * Returns the options array.
     *
     * @return Array
     */
    public final function &getOptions()
    {
        return $this->_options;
    }

    /**
     * Returns the given option value, or the default value if the option is not
     * defined.
     *
     * @param string $option The name of the option.
     * @param mixed $defaultValue The default value to be returned.
     */
    public final function getOption($option, $defaultValue = null)
    {
        if (isset($this->_options[$option])) {
            return $this->_options[$option];
        }
        return $defaultValue;
    }

    /**
     * Sets the options array. See {@link Zend_Media_Id3v2} class for available
     * options.
     *
     * @param Array $options The options array.
     */
    public final function setOptions(&$options) 
    {
        $this->_options = &$options; 
    }

    /**
     * Sets the given option the given value.
     *
     * @param string $option The name of the option.
     * @param mixed $value The value to set for the option.
     */
    public final function setOption($option, $value)
    {
        $this->_options[$option] = $value;
    }

    /**
     * Clears the given option value.
     *
     * @param string $option The name of the option.
     */
    public final function clearOption($option)
    {
        unset($this->_options[$option]);
    }

    /**
     * Magic function so that $obj->value will work.
     *
     * @param string $name The field name.
     * @return mixed
     */
    public function __get($name)
    {
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func(array($this, 'get' . ucfirst($name)));
        } else {
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception('Unknown field: ' . $name);
        }
    }

    /**
     * Magic function so that assignments with $obj->value will work.
     *
     * @param string $name  The field name.
     * @param string $value The field value.
     * @return mixed
     */
    public function __set($name, $value)
    {
        if (method_exists($this, 'set' . ucfirst($name))) {
            call_user_func(array($this, 'set' . ucfirst($name)), $value);
        } else {
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception('Unknown field: ' . $name);
        }
    }

    /**
     * Encodes the given 32-bit integer to 28-bit synchsafe integer, where the
     * most significant bit of each byte is zero, making seven bits out of eight
     * available.
     *
     * @param integer $val The integer to encode.
     * @return integer
     */
    protected final function _encodeSynchsafe32($val)
    {
        return ($val & 0x7f) | ($val & 0x3f80) << 1 |
            ($val & 0x1fc000) << 2 | ($val & 0xfe00000) << 3;
    }

    /**
     * Decodes the given 28-bit synchsafe integer to regular 32-bit integer.
     *
     * @param integer $val The integer to decode
     * @return integer
     */
    protected final function _decodeSynchsafe32($val)
    {
        return ($val & 0x7f) | ($val & 0x7f00) >> 1 |
            ($val & 0x7f0000) >> 2 | ($val & 0x7f000000) >> 3;
    }

    /**
     * Applies the unsynchronisation scheme to the given data string.
     *
     * Whenever a false synchronisation is found within the data, one zeroed
     * byte is inserted after the first false synchronisation byte. This has the
     * side effect that all 0xff00 combinations have to be altered, so they will
     * not be affected by the decoding process.
     * 
     * Therefore all the 0xff00 combinations are replaced with the 0xff0000 combination and all the 0xff[0xe0-0xff]
     * combinations are replaced with 0xff00[0xe0-0xff] during the unsynchronisation.
     *
     * @param string $data The input data.
     * @return string
     */
    protected final function _encodeUnsynchronisation(&$data)
    {
        return preg_replace('/\xff(?=[\xe0-\xff])/', "\xff\x00", preg_replace('/\xff\x00/', "\xff\x00\x00", $data));
    }

    /**
     * Reverses the unsynchronisation scheme from the given data string.
     *
     * @see _encodeUnsynchronisation
     * @param string $data The input data.
     * @return string
     */
    protected final function _decodeUnsynchronisation(&$data)
    {
        return preg_replace('/\xff\x00\x00/', "\xff\x00", preg_replace('/\xff\x00(?=[\xe0-\xff])/', "\xff", $data));
    }

    /**
     * Splits UTF-16 formatted binary data up according to null terminators
     * residing in the string, up to a given limit.
     *
     * @param string $value The input string.
     * @return Array
     */
    protected final function _explodeString16($value, $limit = null)
    {
        $i = 0;
        $array = array();
        while (count($array) < $limit - 1 || $limit === null) {
            $start = $i;
            do {
                $i = strpos($value, "\x00\x00", $i);
                if ($i === false) {
                    $array[] = substr($value, $start);
                    return $array;
                }
            } while ($i & 0x1 != 0 && $i++); // make sure its aligned
            $array[] = substr($value, $start, $i - $start);
            $i += 2;
        }
        $array[] = substr($value, $i);
        return $array;
    }

    /**
     * Splits UTF-8 or ISO-8859-1 formatted binary data according to null
     * terminators residing in the string, up to a given limit.
     *
     * @param string $value The input string.
     * @return Array
     */
    protected final function _explodeString8($value, $limit = null)
    {
        return preg_split('/\x00/', $value, $limit);
    }

    /**
     * Converts string from the given character encoding to the target encoding
     * specified by the options as the encoding to display all the texts with,
     * and returns the converted string.
     *
     * Character encoding sets can be {@link Zend_Media_Id3_Encoding}
     * constants or already in the string form accepted by iconv.
     *
     * @param string|Array $string
     * @param string|integer $source The source encoding.
     * @param string|integer $target The target encoding. Defaults to the
     *  encoding value set in options.
     */
    protected final function _convertString($string, $source, $target = null)
    {
        if ($target === null) {
            $target = $this->getOption('encoding', 'utf-8');
        }

        $source = $this->_translateIntToEncoding($source);
        $target = $this->_translateIntToEncoding($target);

        if ($source == $target) {
            return $string;
        }

        if (is_array($string)) {
            foreach ($string as $key => $value) {
                $string[$key] = iconv($source, $target, $value);
            }
        } else {
            $string = iconv($source, $target, $string);
        }
        return $string;
    }

    /**
     * Returns given encoding in the form accepted by iconv.
     *
     * Character encoding set can be a {@link Zend_Media_Id3_Encoding}
     * constant or already in the string form accepted by iconv.
     *
     * @param string|integer $encoding The encoding.
     * @return string
     */
    protected final function _translateIntToEncoding($encoding)
    {
        if (is_string($encoding)) {
            return strtolower($encoding);
        }
        if (is_integer($encoding)) {
            switch ($encoding) {
                case Zend_Media_Id3_Encoding::UTF16:
                    return 'utf-16';
                case Zend_Media_Id3_Encoding::UTF16LE:
                    return 'utf-16le';
                case Zend_Media_Id3_Encoding::UTF16BE:
                    return 'utf-16be';
                case Zend_Media_Id3_Encoding::ISO88591:
                    return 'iso-8859-1';
                default:
                    return 'utf-8';
            }
        }
        return 'utf-8';
    }

    /**
     * Returns given encoding in the form possible to write to the tag frame.
     *
     * Character encoding set can be in the string form accepted by iconv or
     * already a {@link Zend_Media_Id3_Encoding} constant.
     *
     * @param string|integer $encoding The encoding.
     * @return integer
     */
    protected final function _translateEncodingToInt($encoding)
    {
        if (is_integer($encoding)) {
            if ($encoding >= 0 && $encoding <= 4) {
                return $encoding;
            }
        }
        if (is_string($encoding)) {
            switch ($encoding) {
                case 'utf-16':
                    return Zend_Media_Id3_Encoding::UTF16;
                case 'utf-16le':
                    return Zend_Media_Id3_Encoding::UTF16;
                case 'utf-16be':
                    return Zend_Media_Id3_Encoding::UTF16BE;
                case 'iso-8859-1':
                    return Zend_Media_Id3_Encoding::ISO88591;
                default:
                    return Zend_Media_Id3_Encoding::UTF8;
            }
        }
        return Zend_Media_Id3_Encoding::UTF8;
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    abstract public function write($writer);
}
