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
 * @version    $Id: Comm.php 255 2012-01-21 19:46:18Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/LanguageTextFrame.php';
/**#@-*/

/**
 * The <i>Comments</i> frame is intended for any kind of full text information
 * that does not fit in any other frame. It consists of a frame header followed
 * by encoding, language and content descriptors and is ended with the actual
 * comment as a text string. Newline characters are allowed in the comment text
 * string. There may be more than one comment frame in each tag, but only one
 * with the same language and content descriptor.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Comm.php 255 2012-01-21 19:46:18Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Comm extends Zend_Media_Id3_LanguageTextFrame
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
        $this->_language = strtolower($this->_reader->read(3));
        if ($this->_language == 'xxx' || trim($this->_language, "\0") == '') {
            $this->_language = 'und';
        }

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
                    $this->_convertString($this->_text, $encoding);
                break;
            case self::UTF8:
                // break intentionally omitted
            default:
                list($this->_description, $this->_text) = $this->_convertString
                    ($this->_explodeString8
                     ($this->_reader->read($this->_reader->getSize()), 2),
                     $encoding);
                break;
        }
    }

    /**
     * Returns the short content description.
     *
     * @return string
     */
    public function getDescription() 
    {
         return $this->_description; 
    }

    /**
     * Sets the content description text using given encoding. The description
     * language and encoding must be that of the actual text.
     *
     * @param string $description The content description text.
     * @param string $language The language code.
     * @param integer $encoding The text encoding.
     */
    public function setDescription
        ($description, $language = null, $encoding = null)
    {
        $this->_description = $description;
        if ($language !== null) {
            $this->setLanguage($language);
        }
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
        $writer->writeUInt8($this->_encoding)
               ->write($this->_language);
        switch ($this->_encoding) {
            case self::UTF16LE:
                $writer->writeString16
                    ($this->_description,
                     Zend_Io_Writer::LITTLE_ENDIAN_ORDER, 1)
                       ->writeString16
                    ($this->_text,Zend_Io_Writer::LITTLE_ENDIAN_ORDER);
                break;
            case self::UTF16:
                // break intentionally omitted
            case self::UTF16BE:
                $writer->writeString16($this->_description, null, 1)
                       ->writeString16($this->_text);
                break;
            default:
                $writer->writeString8($this->_description, 1)
                       ->writeString8($this->_text);
                break;
        }
    }
}
