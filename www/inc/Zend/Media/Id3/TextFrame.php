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
 * @version    $Id: TextFrame.php 273 2012-08-21 17:22:52Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
require_once 'Zend/Media/Id3/Encoding.php';
/**#@-*/

/**
 * A base class for all the text frames.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: TextFrame.php 273 2012-08-21 17:22:52Z svollbehr $
 */
abstract class Zend_Media_Id3_TextFrame extends Zend_Media_Id3_Frame
    implements Zend_Media_Id3_Encoding
{
    /**
     * The text encoding.
     *
     * @var integer
     */
    protected $_encoding;

    /**
     * The text array.
     *
     * @var string
     */
    protected $_text;

    /**
     * Constructs the class with given parameters and parses object related
     * data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        $this->setEncoding
            ($this->getOption('encoding', Zend_Media_Id3_Encoding::UTF8));

        if ($this->_reader === null) {
            return;
        }

        $encoding = $this->_reader->readUInt8();
        switch ($encoding) {
            case self::UTF16:
                // break intentionally omitted
            case self::UTF16BE:
                $this->_text = $this->_convertString
                    ($this->_explodeString16
                     ($this->_reader->readString16($this->_reader->getSize())),
                     $encoding);
                break;
            case self::UTF8:
                // break intentionally omitted
            default:
                $this->_text = $this->_convertString
                    ($this->_explodeString8
                     ($this->_reader->readString8($this->_reader->getSize())),
                     $encoding);
                break;
        }
    }

    /**
     * Returns the text encoding.
     *
     * All the strings read from a file are automatically converted to the
     * character encoding specified with the <var>encoding</var> option. See
     * {@link Zend_Media_Id3v2} for details. This method returns that character
     * encoding, or any value set after read, translated into a string form
     * regardless if it was set using a {@link Zend_Media_Id3_Encoding} constant
     * or a string.
     * 
     * @return integer
     */
    public function getEncoding() 
    {
        return $this->_translateIntToEncoding($this->_encoding);
    }

    /**
     * Sets the text encoding.
     *
     * All the string written to the frame are done so using given character
     * encoding. No conversions of existing data take place upon the call to
     * this method thus all texts must be given in given character encoding.
     *
     * The character encoding parameter takes either a
     * {@link Zend_Media_Id3_Encoding} constant or a character set name string
     * in the form accepted by iconv. The default character encoding used to
     * write the frame is 'utf-8'.
     *
     * @see Zend_Media_Id3_Encoding
     * @param integer $encoding The text encoding.
     */
    public function setEncoding($encoding) 
    {
        $this->_encoding = $this->_translateEncodingToInt($encoding);
    }

    /**
     * Returns the first text chunk the frame contains.
     *
     * @return string
     */
    public function getText() 
    {
        return $this->_text[0]; 
    }

    /**
     * Returns an array of texts the frame contains.
     *
     * @return Array
     */
    public function getTexts() 
    {
        return $this->_text; 
    }

    /**
     * Sets the text using given encoding.
     *
     * @param mixed $text The text string or an array of strings.
     * @param integer $encoding The text encoding.
     */
    public function setText($text, $encoding = null)
    {
        $this->_text = is_array($text) ? $text : array($text);
        if ($encoding !== null) {
            $this->setEncoding($encoding);
        }
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeUInt8($this->_encoding);
        switch ($this->_encoding) {
            case self::UTF16LE:
                $count = count($this->_text);
                for ($i = 0; $i < $count; $i++) {
                    $writer->writeString16
                        ($this->_text, Zend_Io_Writer::LITTLE_ENDIAN_ORDER,
                         $i == $count ? null : 1);
                }
                break;
            case self::UTF16:
                // break intentionally omitted
            case self::UTF16BE:
                $writer->write(implode("\0\0", $this->_text));
                break;
            default:
                $writer->write(implode("\0", $this->_text));
                break;
        }
    }
}
