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
 * @version    $Id: Txxx.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/TextFrame.php';
/**#@-*/

/**
 * This frame is intended for one-string text information concerning the audio
 * file in a similar way to the other T-frames. The frame consists of a
 * description of the string followed by the actual string. There may be more
 * than one TXXX frame in each tag, but only one with the same description.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Txxx.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Txxx extends Zend_Media_Id3_TextFrame
{
    /** @var string */
    private $_description;

    /**
     * Constructs the class with given parameters and parses object related
     * data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        Zend_Media_Id3_Frame::__construct($reader, $options);

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
                list($this->_description, $this->_text) =
                    $this->_explodeString16
                        ($this->_reader->read($this->_reader->getSize()), 2);
                $this->_description =
                    $this->_convertString($this->_description, $encoding);
                $this->_text =
                    $this->_convertString(array($this->_text), $encoding);
                break;
            case self::UTF8:
                // break intentionally omitted
            default:
                list($this->_description, $this->_text) = $this->_convertString
                    ($this->_explodeString8
                     ($this->_reader->read($this->_reader->getSize()), 2),
                     $encoding);
                $this->_text = array($this->_text);
                break;
        }
    }

    /**
     * Returns the description text.
     *
     * @return string
     */
    public function getDescription() 
    {
         return $this->_description; 
    }

    /**
     * Sets the description text using given encoding.
     *
     * @param string $description The content description text.
     * @param integer $encoding The text encoding.
     */
    public function setDescription($description, $encoding = null)
    {
        $this->_description = $description;
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
                $writer->writeString16
                    ($this->_description,
                     Zend_Io_Writer::LITTLE_ENDIAN_ORDER, null, 1)
                       ->writeString16
                    ($this->_text[0],
                     Zend_Io_Writer::LITTLE_ENDIAN_ORDER);
                break;
            case self::UTF16:
                // break intentionally omitted
            case self::UTF16BE:
                $writer->writeString16($this->_description, null, 1)
                       ->writeString16($this->_text[0], null);
                break;
            default:
                $writer->writeString8($this->_description, 1)
                       ->writeString8($this->_text[0]);
                break;
        }
    }
}
