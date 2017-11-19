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
 * @subpackage FLAC
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: MetadataBlock.php 241 2011-06-11 16:46:52Z svollbehr $
 */

/**
 * This class represents a FLAC metadata block. FLAC specifies a metadata system, which allows arbitrary information
 * about the stream to be included at the beginning of the stream. FLAC contains a mandatory metadata block (called the
 * STREAMINFO block), and any number of other metadata blocks, listed below. After metadata blocks follows the audio
 * frames.
 *
 * FLAC supports up to 128 kinds of metadata blocks; currently the following are defined:
 *  o STREAMINFO
 *    This block has information about the whole stream, like sample rate, number of channels, total number of samples,
 *    etc. It must be present as the first metadata block in the stream. Other metadata blocks may follow, and ones
 *    that the decoder doesn't understand, it will skip.
 *  o APPLICATION
 *    This block is for use by third-party applications. The only mandatory field is a 32-bit identifier. This ID is
 *    granted upon request to an application by the FLAC maintainers. The remainder is of the block is defined by the
 *    registered application. Visit the registration page if you would like to register an ID for your application with
 *    FLAC.
 *  o PADDING
 *    This block allows for an arbitrary amount of padding. The contents of a PADDING block have no meaning. This block
 *    is useful when it is known that metadata will be edited after encoding; the user can instruct the encoder to
 *    reserve a PADDING block of sufficient size so that when metadata is added, it will simply overwrite the padding
 *    (which is relatively quick) instead of having to insert it into the right place in the existing file (which would
 *    normally require rewriting the entire file).
 *  o SEEKTABLE
 *    This is an optional block for storing seek points. It is possible to seek to any given sample in a FLAC stream
 *    without a seek table, but the delay can be unpredictable since the bitrate may vary widely within a stream. By
 *    adding seek points to a stream, this delay can be significantly reduced. Each seek point takes 18 bytes, so 1%
 *    resolution within a stream adds less than 2k. There can be only one SEEKTABLE in a stream, but the table can have
 *    any number of seek points. There is also a special 'placeholder' seekpoint which will be ignored by decoders but
 *    which can be used to reserve space for future seek point insertion.
 *  o VORBIS_COMMENT
 *    This block is for storing a list of human-readable name/value pairs. Values are encoded using UTF-8. It is an
 *    implementation of the Vorbis comment specification (without the framing bit). This is the only officially
 *    supported tagging mechanism in FLAC. There may be only one VORBIS_COMMENT block in a stream. In some external
 *    documentation, Vorbis comments are called FLAC tags to lessen confusion.
 *  o CUESHEET
 *    This block is for storing various information that can be used in a cue sheet. It supports track and index points,
 *    compatible with Red Book CD digital audio discs, as well as other CD-DA metadata such as media catalog number and
 *    track ISRCs. The CUESHEET block is especially useful for backing up CD-DA discs, but it can be used as a general
 *    purpose cueing mechanism for playback.
 *  o PICTURE
 *    This block is for storing pictures associated with the file, most commonly cover art from CDs. There may be more
 *    than one PICTURE block in a file. The picture format is similar to the APIC frame in ID3v2. The PICTURE block has
 *    a type, MIME type, and UTF-8 description like ID3v2, and supports external linking via URL (though this is
 *    discouraged). The differences are that there is no uniqueness constraint on the description field, and the MIME
 *    type is mandatory. The FLAC PICTURE block also includes the resolution, color depth, and palette size so that the
 *    client can search for a suitable picture without having to scan them all.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage FLAC
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: MetadataBlock.php 241 2011-06-11 16:46:52Z svollbehr $
 */
abstract class Zend_Media_Flac_MetadataBlock
{
    /**
     * The reader object.
     *
     * @var Zend_Io_Reader
     */
    protected $_reader;

    /** @var integer */
    private $_last;

    /** @var integer */
    private $_type;

    /** @var integer */
    private $_size;

    /**
     * Constructs the class with given parameters and reads object related data
     * from the Flac bitstream.
     *
     * @param Zend_Io_Reader $reader The reader object.
     */
    public function __construct($reader)
    {
        $this->_reader = $reader;

        $this->_last = ($tmp = $this->_reader->readUInt8()) >> 7 & 0x1;
        $this->_type = $tmp & 0x7f;
        $this->_size = $this->_reader->readUInt24BE();
    }

    /**
     * Returns the metadata block type. The type is one of the following.
     *
     *  o 0: STREAMINFO
     *  o 1: PADDING
     *  o 2: APPLICATION
     *  o 3: SEEKTABLE
     *  o 4: VORBIS_COMMENT
     *  o 5: CUESHEET
     *  o 6: PICTURE
     *  o 7-126: reserved
     *  o 127: invalid, to avoid confusion with a frame sync code
     *
     * @return integer
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * Returns the metadata block length without the header, in bytes.
     *
     * @return integer
     */
    public function getSize()
    {
        return $this->_size;
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
            require_once 'Zend/Media/Flac/Exception.php';
            throw new Zend_Media_Flac_Exception('Unknown field: ' . $name);
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
            require_once 'Zend/Media/Flac/Exception.php';
            throw new Zend_Media_Flac_Exception('Unknown field: ' . $name);
        }
    }
}
