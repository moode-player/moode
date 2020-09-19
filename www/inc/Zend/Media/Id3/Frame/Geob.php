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
 * @version    $Id: Geob.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
require_once 'Zend/Media/Id3/Encoding.php';
/**#@-*/

/**
 * In the <i>General encapsulated object</i> frame any type of file can be
 * encapsulated.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Geob.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Geob extends Zend_Media_Id3_Frame
    implements Zend_Media_Id3_Encoding
{
    /** @var integer */
    private $_encoding;

    /** @var string */
    private $_mimeType;

    /** @var string */
    private $_filename;

    /** @var string */
    private $_description;

    /** @var string */
    private $_data;

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
        list($this->_mimeType) = $this->_explodeString8
            ($this->_reader->read($this->_reader->getSize()), 2);
        $this->_reader->setOffset(1 + strlen($this->_mimeType) + 1);

        switch ($encoding) {
            case self::UTF16:
                // break intentionally omitted
            case self::UTF16BE:
                list ($this->_filename, $this->_description,
                      $this->_data) =
                    $this->_explodeString16
                        ($this->_reader->read($this->_reader->getSize()), 3);
                break;
            case self::UTF8:
                // break intentionally omitted
            default:
                list ($this->_filename, $this->_description,
                      $this->_data) =
                    $this->_explodeString8
                        ($this->_reader->read($this->_reader->getSize()), 3);
                break;
        }
        $this->_filename =
            $this->_convertString($this->_filename, $encoding);
        $this->_description =
            $this->_convertString($this->_description, $encoding);
    }

    /**
     * Returns the text encoding.
     *
     * All the strings read from a file are automatically converted to the
     * character encoding specified with the <var>encoding</var> option. See
     * {@link Zend_Media_Id3v2} for details. This method returns that character
     * encoding, or any value set after read, translated into a string form
     * regarless if it was set using a {@link Zend_Media_Id3_Encoding} constant
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
     * Returns the MIME type. The MIME type is always encoded with ISO-8859-1.
     *
     * @return string
     */
    public function getMimeType() 
    {
        return $this->_mimeType; 
    }

    /**
     * Sets the MIME type. The MIME type is always ISO-8859-1 encoded.
     *
     * @param string $mimeType The MIME type.
     */
    public function setMimeType($mimeType) 
    {
        $this->_mimeType = $mimeType; 
    }

    /**
     * Returns the file name.
     *
     * @return string
     */
    public function getFilename() 
    {
        return $this->_filename; 
    }

    /**
     * Sets the file name using given encoding. The file name encoding must be
     * that of the description text.
     *
     * @param string $description The file description text.
     * @param integer $encoding The text encoding.
     */
    public function setFilename($filename, $encoding = null)
    {
        $this->_filename = $filename;
        if ($encoding !== null) {
            $this->_encoding = $encoding;
        }
    }

    /**
     * Returns the file description.
     *
     * @return string
     */
    public function getDescription() 
    {
        return $this->_description; 
    }

    /**
     * Sets the file description text using given encoding. The description
     * encoding must be that of the file name.
     *
     * @param string $description The file description text.
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
     * Returns the embedded object binary data.
     *
     * @return string
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * Sets the embedded object binary data.
     *
     * @param string $data The object data.
     */
    public function setData($data)
    {
        $this->_data = $data;
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
               ->writeString8($this->_mimeType, 1);
        switch ($this->_encoding) {
            case self::UTF16LE:
                $writer->writeString16
                    ($this->_filename, Zend_Io_Writer::LITTLE_ENDIAN_ORDER, 1)
                       ->writeString16
                    ($this->_description,
                     Zend_Io_Writer::LITTLE_ENDIAN_ORDER, 1);
                break;
            case self::UTF16:
                // break intentionally omitted
            case self::UTF16BE:
                $writer->writeString16($this->_filename, null, 1)
                       ->writeString16($this->_description, null, 1);
                break;
            default:
                $writer->writeString8($this->_filename, 1)
                       ->writeString8($this->_description, 1);
                break;
        }
        $writer->write($this->_data);
    }
}
