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
 * @subpackage MPEG
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Object.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Io/Reader.php';
/**#@-*/

/**
 * The base class for all MPEG objects.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage MPEG
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Object.php 177 2010-03-09 13:13:34Z svollbehr $
 */
abstract class Zend_Media_Mpeg_Object
{
    /**
     * The reader object.
     *
     * @var Reader
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
    public function __construct($reader, &$options = array())
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
     * Sets the options array. See main class for available options.
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
     * Finds and returns the next start code. Start codes are reserved bit
     * patterns in the video file that do not otherwise occur in the video stream.
     *
     * All start codes are byte aligned and start with the following byte
     * sequence: 0x00 0x00 0x01.
     *
     * @return integer
     */
    protected final function nextStartCode()
    {
        $buffer = '    ';
        for ($i = 0; $i < 4; $i++) {
            $start = $this->_reader->getOffset();
            if (($buffer = substr($buffer, -4) .
                     $this->_reader->read(512)) === false) {
                require_once 'Zend/Media/Mpeg/Exception.php';
                throw new Zend_Media_Mpeg_Exception('Invalid data');
            }
            $limit = strlen($buffer);
            $pos = 0;
            while ($pos < $limit - 3) {
                if (ord($buffer{$pos++}) == 0) {
                    list(, $int) = unpack('n*', substr($buffer, $pos, 2));
                    if ($int == 1) {
                        if (($pos += 2) < $limit - 2) {
                            list(, $int) =
                                unpack('n*', substr($buffer, $pos, 2));
                            if ($int == 0 && ord($buffer{$pos + 2}) == 1) {
                                continue;
                            }
                        }
                        $this->_reader->setOffset($start + $pos - 3);
                        return ord($buffer{$pos++}) & 0xff | 0x100;
                    }
                }
            }
            $this->_reader->setOffset($start + $limit);
        }

        /* No start code found within 2048 bytes, the maximum size of a pack */
        require_once 'Zend/Media/Mpeg/Exception.php';
        throw new Zend_Media_Mpeg_Exception('Invalid data');
    }

    /**
     * Finds and returns the previous start code. Start codes are reserved bit
     * patterns in the video file that do not otherwise occur in the video
     * stream.
     *
     * All start codes are byte aligned and start with the following byte
     * sequence: 0x00 0x00 0x01.
     *
     * @return integer
     */
    protected final function prevStartCode()
    {
        $buffer = '    ';
        $start;
        $position = $this->_reader->getOffset();
        while ($position > 0) {
            $start = 0;
            $position = $position - 512;
            if ($position < 0) {
                require_once 'Zend/Media/Mpeg/Exception.php';
                throw new Zend_Media_Mpeg_Exception('Invalid data');
            }
            $this->_reader->setOffset($position);
            $buffer = $this->_reader->read(512) . substr($buffer, 0, 4);
            $pos = 512 - 8;
            while ($pos  > 3) {
                list(, $int) = unpack('n*', substr($buffer, $pos + 1, 2));
                if (ord($buffer{$pos}) == 0 && $int == 1) {
                    list(, $int) = unpack('n*', substr($buffer, $pos + 3, 2));
                    if ($pos + 2 < 512 && $int == 0 &&
                            ord($buffer{$pos + 5}) == 1) {
                        $pos--;
                        continue;
                    }
                    $this->_reader->setOffset($position + $pos);
                    return ord($buffer{$pos + 3}) & 0xff | 0x100;
                }
                $pos--;
            }
            $this->_reader->setOffset($position = $position + 3);
        }
        return 0;
    }

    /**
     * Formats given time in seconds into the form of
     * [hours:]minutes:seconds.milliseconds.
     *
     * @param integer $seconds The time to format, in seconds
     * @return string
     */
    protected final function formatTime($seconds)
    {
        $milliseconds = round(($seconds - floor($seconds)) * 1000);
        $seconds = floor($seconds);
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        return
            ($minutes > 0 ?
             ($hours > 0 ? $hours . ':' .
              str_pad($minutes % 60, 2, '0', STR_PAD_LEFT) : $minutes % 60) .
                ':' .
              str_pad($seconds % 60, 2, '0', STR_PAD_LEFT) : $seconds % 60) .
                '.' .
              str_pad($milliseconds, 3, '0', STR_PAD_LEFT);
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
            require_once 'Zend/Media/Mpeg/Exception.php';
            throw new Zend_Media_Mpeg_Exception('Unknown field: ' . $name);
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
            call_user_func
                (array($this, 'set' . ucfirst($name)), $value);
        } else {
            require_once 'Zend/Media/Mpeg/Exception.php';
            throw new Zend_Media_Mpeg_Exception('Unknown field: ' . $name);
        }
    }
}
