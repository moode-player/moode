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
 * @version    $Id: ExtendedHeader.php 211 2011-01-12 15:43:48Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Object.php';
/**#@-*/

/**
 * The extended header contains information that can provide further insight in
 * the structure of the tag, but is not vital to the correct parsing of the tag
 * information; hence the extended header is optional.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: ExtendedHeader.php 211 2011-01-12 15:43:48Z svollbehr $
 */
final class Zend_Media_Id3_ExtendedHeader extends Zend_Media_Id3_Object
{
    /**
     * A flag to denote that the present tag is an update of a tag found earlier
     * in the present file or stream. If frames defined as unique are found in
     * the present tag, they are to override any corresponding ones found in the
     * earlier tag. This flag has no corresponding data.
     *
     * @since ID3v2.4.0
     */
    const UPDATE = 64;

    /**
     * @since ID3v2.4.0 A flag to denote that a CRC-32 data is included in the
     * extended header. The CRC is calculated on all the data between the header
     * and footer as indicated by the header's tag length field, minus the
     * extended header. Note that this includes the padding (if there is any),
     * but excludes the footer. The CRC-32 is stored as an 35 bit synchsafe
     * integer, leaving the upper four bits always zeroed.
     *
     * @since ID3v2.3.0 The CRC is calculated before unsynchronisation on the
     * data between the extended header and the padding, i.e. the frames and
     * only the frames.
     */
    const CRC32 = 32;

    /**
     * A flag to denote whether or not the tag has restrictions applied on it.
     *
     * @since ID3v2.4.0
     */
    const RESTRICTED = 16;

    /** @var integer */
    private $_size;

    /** @var integer */
    private $_flags = 0;

    /** @var integer */
    private $_padding;

    /** @var integer */
    private $_crc;

    /** @var integer */
    private $_restrictions = 0;

    /**
     * Constructs the class with given parameters and reads object related data
     * from the ID3v2 tag.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        if ($reader === null)
            return;

        $offset = $this->_reader->getOffset();
        $this->_size = $this->_reader->readUInt32BE();

        /* ID3v2.3.0 ExtendedHeader */
        if ($this->getOption('version', 4) < 4) {
            if ($this->_reader->readUInt16BE() == 0x8000) {
                $this->_flags = self::CRC32;
            }
            $this->_padding = $this->_reader->readUInt32BE();
            if ($this->hasFlag(self::CRC32)) {
                $this->_crc = $this->_reader->readUInt32BE();
            }
        }

        /* ID3v2.4.0 ExtendedHeader */
        else {
            $this->_size = $this->_decodeSynchsafe32($this->_size);
            $this->_reader->skip(1);
            $this->_flags = $this->_reader->readInt8();
            if ($this->hasFlag(self::UPDATE)) {
                $this->_reader->skip(1);
            }
            if ($this->hasFlag(self::CRC32)) {
                $this->_reader->skip(1);
                $this->_crc =
                    $this->_reader->readInt8() * (0xfffffff + 1) +
                    $this->_decodeSynchsafe32($this->_reader->readUInt32BE());
            }
            if ($this->hasFlag(self::RESTRICTED)) {
                $this->_reader->skip(1);
                $this->_restrictions = $this->_reader->readInt8();
            }
        }
    }

    /**
     * Returns the extended header size in bytes.
     *
     * @return integer
     */
    public function getSize() 
    {
         return $this->_size; 
    }

    /**
     * Checks whether or not the flag is set. Returns <var>true</var> if the
     * flag is set, <var>false</var> otherwise.
     *
     * @param integer $flag The flag to query.
     * @return boolean
     */
    public function hasFlag($flag) 
    {
         return ($this->_flags & $flag) == $flag; 
    }

    /**
     * Returns the flags byte.
     *
     * @return integer
     */
    public function getFlags($flags) 
    {
         return $this->_flags; 
    }

    /**
     * Sets the flags byte.
     *
     * @param integer $flags The flags byte.
     */
    public function setFlags($flags) 
    {
         $this->_flags = $flags; 
    }

    /**
     * Returns the CRC-32 data.
     *
     * @return integer
     */
    public function getCrc()
    {
        if ($this->hasFlag(self::CRC32)) {
            return $this->_crc;
        }
        return false;
    }

    /**
     * Sets whether the CRC-32 should be generated upon tag write.
     *
     * @param boolean $useCrc Whether CRC-32 should be generated.
     */
    public function useCrc($useCrc)
    {
        if ($useCrc) {
            $this->setFlags($this->getFlags() | self::CRC32);
        } else {
            $this->setFlags($this->getFlags() & ~self::CRC32);
        }
    }

    /**
     * Sets the CRC-32. The CRC-32 value is calculated of all the frames in the
     * tag and includes padding.
     *
     * @param integer $crc The 32-bit CRC value.
     */
    public function setCrc($crc)
    {
        if (is_bool($crc)) {
            $this->useCrc($crc);
        } else {
            $this->_crc = $crc;
        }
    }

    /**
     * Returns the restrictions. For some applications it might be desired to
     * restrict a tag in more ways than imposed by the ID3v2 specification. Note
     * that the presence of these restrictions does not affect how the tag is
     * decoded, merely how it was restricted before encoding. If this flag is
     * set the tag is restricted as follows:
     *
     * <pre>
     * Restrictions %ppqrrstt
     *
     * p - Tag size restrictions
     *
     *   00   No more than 128 frames and 1 MB total tag size.
     *   01   No more than 64 frames and 128 KB total tag size.
     *   10   No more than 32 frames and 40 KB total tag size.
     *   11   No more than 32 frames and 4 KB total tag size.
     *
     * q - Text encoding restrictions
     *
     *   0    No restrictions
     *   1    Strings are only encoded with ISO-8859-1 or UTF-8.
     *
     * r - Text fields size restrictions
     *
     *   00   No restrictions
     *   01   No string is longer than 1024 characters.
     *   10   No string is longer than 128 characters.
     *   11   No string is longer than 30 characters.
     *
     *   Note that nothing is said about how many bytes is used to represent
     *   those characters, since it is encoding dependent. If a text frame
     *   consists of more than one string, the sum of the strungs is restricted
     *   as stated.
     *
     * s - Image encoding restrictions
     *
     *   0   No restrictions
     *   1   Images are encoded only with PNG [PNG] or JPEG [JFIF].
     *
     * t - Image size restrictions
     *
     *   00  No restrictions
     *   01  All images are 256x256 pixels or smaller.
     *   10  All images are 64x64 pixels or smaller.
     *   11  All images are exactly 64x64 pixels, unless required otherwise.
     * </pre>
     *
     * @return integer
     */
    public function getRestrictions() 
    {
         return $this->_restrictions; 
    }

    /**
     * Sets the restrictions byte. See {@link #getRestrictions} for more.
     *
     * @param integer $restrictions The restrictions byte.
     */
    public function setRestrictions($restrictions)
    {
        $this->_restrictions = $restrictions;
    }

    /**
     * Returns the total padding size, or simply the total tag size excluding
     * the frames and the headers.
     *
     * @return integer
     * @deprecated ID3v2.3.0
     */
    public function getPadding() 
    {
         return $this->_padding; 
    }

    /**
     * Sets the total padding size, or simply the total tag size excluding the
     * frames and the headers.
     *
     * @param integer $padding The padding size.
     * @deprecated ID3v2.3.0
     */
    public function setPadding($padding) 
    {
         return $this->_padding = $padding; 
    }

    /**
     * Writes the header data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        /* ID3v2.3.0 ExtendedHeader */
        if ($this->getOption('version', 4) < 4) {
            $writer->writeUInt32BE($this->_size)
                   ->writeUInt16BE($this->hasFlag(self::CRC32) ? 0x8000 : 0)
                   ->writeUInt32BE($this->_padding);
            if ($this->hasFlag(self::CRC32)) {
                $writer->writeUInt32BE($this->_crc);
            }
        }

        /* ID3v2.4.0 ExtendedHeader */
        else {
            $writer->writeUInt32BE($this->_encodeSynchsafe32($this->_size))
                   ->writeInt8(1)
                   ->writeInt8($this->_flags);
            if ($this->hasFlag(self::UPDATE)) {
                $writer->write("\0");
            }
            if ($this->hasFlag(self::CRC32)) {
                $writer->writeInt8(5)
                       ->writeInt8
                    ($this->_crc & 0xf0000000 >> 28 & 0xf /*eq >>> 28*/)
                       ->writeUInt32BE($this->_encodeSynchsafe32($this->_crc));
            }
            if ($this->hasFlag(self::RESTRICTED)) {
                $writer->writeInt8(1)
                       ->writeInt8($this->_restrictions);
            }
        }
    }
}
