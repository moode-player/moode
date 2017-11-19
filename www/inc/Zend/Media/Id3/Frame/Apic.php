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
 * @version    $Id: Apic.php 241 2011-06-11 16:46:52Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
require_once 'Zend/Media/Id3/Encoding.php';
/**#@-*/

/**
 * The <i>Attached picture</i> frame contains a picture directly related to the
 * audio file. Image format is the MIME type and subtype for the image.
 *
 * There may be several pictures attached to one file, each in their individual
 * APIC frame, but only one with the same content descriptor. There may only
 * be one picture with the same picture type.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Apic.php 241 2011-06-11 16:46:52Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Apic extends Zend_Media_Id3_Frame
    implements Zend_Media_Id3_Encoding
{
    /**
     * The list of image types.
     *
     * @var Array
     */
    public static $types = array
        ('Other', '32x32 pixels file icon (PNG only)', 'Other file icon',
         'Cover (front)', 'Cover (back)', 'Leaflet page',
         'Media (e.g. label side of CD)', 'Lead artist/lead performer/soloist',
         'Artist/performer', 'Conductor', 'Band/Orchestra', 'Composer',
         'Lyricist/text writer', 'Recording Location', 'During recording',
         'During performance', 'Movie/video screen capture',
         'A bright coloured fish', 'Illustration', 'Band/artist logotype',
         'Publisher/Studio logotype');

    /** @var integer */
    private $_encoding;

    /** @var string */
    private $_mimeType = 'image/unknown';

    /** @var integer */
    private $_imageType = 0;

    /** @var string */
    private $_description;

    /** @var string */
    private $_imageData;

    /** @var integer */
    private $_imageSize = 0;

    /**
     * Constructs the class with given parameters and parses object related
     * data.
     *
     * @todo  There is the possibility to put only a link to the image file by
     *  using the MIME type '-->' and having a complete URL instead of picture
     *  data. Support for such needs further design considerations.
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
        $this->_imageType = $this->_reader->readUInt8();
        
        switch ($encoding) {
            case self::UTF16:
                // break intentionally omitted
            case self::UTF16BE:
                list ($this->_description, $this->_imageData) =
                    $this->_explodeString16
                        ($this->_reader->read($this->_reader->getSize()), 2);
                break;
            case self::UTF8:
                // break intentionally omitted
            default:
                list ($this->_description, $this->_imageData) =
                    $this->_explodeString8
                        ($this->_reader->read($this->_reader->getSize()), 2);
                break;
        }
        $this->_description =
            $this->_convertString($this->_description, $encoding);
        $this->_imageSize = strlen($this->_imageData);
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
     * Returns the MIME type. The MIME type is always ISO-8859-1 encoded.
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
     * Returns the image type.
     *
     * @return integer
     */
    public function getImageType() 
    {
        return $this->_imageType; 
    }

    /**
     * Sets the image type code.
     *
     * @param integer $imageType The image type code.
     */
    public function setImageType($imageType) 
    {
        $this->_imageType = $imageType; 
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
     * Sets the content description text using given encoding.
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
     * Returns the embedded image data.
     *
     * @return string
     */
    public function getImageData() 
    {
        return $this->_imageData; 
    }

    /**
     * Sets the embedded image data. Also updates the image size field to
     * correspond the new data.
     *
     * @param string $imageData The image data.
     */
    public function setImageData($imageData)
    {
        $this->_imageData = $imageData;
        $this->_imageSize = strlen($imageData);
    }

    /**
     * Returns the size of the embedded image data.
     *
     * @return integer
     */
    public function getImageSize() 
    {
        return $this->_imageSize; 
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
               ->writeString8($this->_mimeType, 1)
               ->writeUInt8($this->_imageType);
        switch ($this->_encoding) {
            case self::UTF16LE:
                $writer->writeString16
                    ($this->_description,
                     Zend_Io_Writer::LITTLE_ENDIAN_ORDER, 1);
                break;
            case self::UTF16:
                // break intentionally omitted
            case self::UTF16BE:
                $writer->writeString16($this->_description, null, 1);
                break;
            default:
                $writer->writeString8($this->_description, 1);
                break;
        }
        $writer->write($this->_imageData);
    }
}
