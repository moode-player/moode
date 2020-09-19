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
 * @subpackage ASF
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: FileProperties.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>File Properties Object</i> defines the global characteristics of the
 * combined digital media streams found within the Data Object.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: FileProperties.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_FileProperties extends Zend_Media_Asf_Object
{
    /**
     * Indicates, if set, that a file is in the process of being created (for
     * example, for recording applications), and thus that various values stored
     * in the header objects are invalid. It is highly recommended that
     * post-processing be performed to remove this condition at the earliest
     * opportunity.
     */
    const BROADCAST = 1;

    /**
     * Indicates, if set, that a file is seekable. Note that for files
     * containing a single audio stream and a <i>Minimum Data Packet Size</i>
     * field equal to the <i>Maximum Data Packet Size</i> field, this flag shall
     * always be set to 1. For files containing a single audio stream and a
     * video stream or mutually exclusive video streams, this flag is only set
     * to 1 if the file contains a matching <i>Simple Index Object</i> for each
     * regular video stream.
     */
    const SEEKABLE = 2;

    /** @var string */
    private $_fileId;

    /** @var integer */
    private $_fileSize;

    /** @var integer */
    private $_creationDate;

    /** @var integer */
    private $_dataPacketsCount;

    /** @var integer */
    private $_playDuration;

    /** @var integer */
    private $_sendDuration;

    /** @var integer */
    private $_preroll;

    /** @var integer */
    private $_flags;

    /** @var integer */
    private $_minimumDataPacketSize;

    /** @var integer */
    private $_maximumDataPacketSize;

    /** @var integer */
    private $_maximumBitrate;

    /**
     * Constructs the class with given parameters and reads object related data
     * from the ASF file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader, &$options = array())
    {
        parent::__construct($reader, $options);

        $this->_fileId = $this->_reader->readGuid();
        $this->_fileSize = $this->_reader->readInt64LE();
        $this->_creationDate = $this->_reader->readInt64LE();
        $this->_dataPacketsCount = $this->_reader->readInt64LE();
        $this->_playDuration = $this->_reader->readInt64LE();
        $this->_sendDuration = $this->_reader->readInt64LE();
        $this->_preroll = $this->_reader->readInt64LE();
        $this->_flags = $this->_reader->readUInt32LE();
        $this->_minimumDataPacketSize = $this->_reader->readUInt32LE();
        $this->_maximumDataPacketSize = $this->_reader->readUInt32LE();
        $this->_maximumBitrate = $this->_reader->readUInt32LE();
    }

    /**
     * Returns the file id field.
     *
     * @return integer
     */
    public function getFileId() 
    {
        return $this->_fileId; 
    }

    /**
     * Sets the file id field.
     *
     * @param GUID $fileId The new file id.
     */
    public function setFileId($fileId) 
    {
        $this->_fileId = $fileId; 
    }

    /**
     * Returns the size, in bytes, of the entire file. The value of this field
     * is invalid if the broadcast flag bit in the flags field is set to 1.
     *
     * @return integer
     */
    public function getFileSize() 
    {
        return $this->_fileSize; 
    }

    /**
     * Sets the size, in bytes, of the entire file. The value of this field is
     * invalid if the broadcast flag bit in the flags field is set to 1.
     *
     * @param integer $fileSize The size of the entire file.
     */
    public function setFileSize($fileSize) 
    {
        $this->_fileSize = $fileSize; 
    }

    /**
     * Returns the date and time of the initial creation of the file. The value
     * is given as the number of 100-nanosecond intervals since January 1, 1601,
     * according to Coordinated Universal Time (Greenwich Mean Time). The value
     * of this field may be invalid if the broadcast flag bit in the flags field
     * is set to 1.
     *
     * @return integer
     */
    public function getCreationDate() 
    {
        return $this->_creationDate; 
    }

    /**
     * Sets the date and time of the initial creation of the file. The value is
     * given as the number of 100-nanosecond intervals since January 1, 1601,
     * according to Coordinated Universal Time (Greenwich Mean Time). The value
     * of this field may be invalid if the broadcast flag bit in the flags field
     * is set to 1.
     *
     * @param integer $creationDate The date and time of the initial creation of
     *        the file.
     */
    public function setCreationDate($creationDate)
    {
        $this->_creationDate = $creationDate;
    }

    /**
     * Returns the number of Data Packet entries that exist within the
     * {@link Zend_Media_Asf_Object_Data Data Object}. The value of this field
     * is invalid if the broadcast flag bit in the flags field is set to 1.
     *
     * @return integer
     */
    public function getDataPacketsCount() 
    {
        return $this->_dataPacketsCount; 
    }

    /**
     * Sets the number of Data Packet entries that exist within the
     * {@link Zend_Media_Asf_Object_Data Data Object}. The value of this field
     * is invalid if the broadcast flag bit in the flags field is set to 1.
     *
     * @param integer $dataPacketsCount The number of Data Packet entries.
     */
    public function setDataPacketsCount($dataPacketsCount)
    {
        $this->_dataPacketsCount = $dataPacketsCount;
    }

    /**
     * Returns the time needed to play the file in 100-nanosecond units. This
     * value should include the duration (estimated, if an exact value is
     * unavailable) of the the last media object in the presentation. The value
     * of this field is invalid if the broadcast flag bit in the flags field is
     * set to 1.
     *
     * @return integer
     */
    public function getPlayDuration() 
    {
        return $this->_playDuration; 
    }

    /**
     * Sets the time needed to play the file in 100-nanosecond units. This
     * value should include the duration (estimated, if an exact value is
     * unavailable) of the the last media object in the presentation. The value
     * of this field is invalid if the broadcast flag bit in the flags field is
     * set to 1.
     *
     * @param integer $playDuration The time needed to play the file.
     */
    public function setPlayDuration($playDuration)
    {
        $this->_playDuration = $playDuration;
    }

    /**
     * Returns the time needed to send the file in 100-nanosecond units. This
     * value should include the duration of the last packet in the content. The
     * value of this field is invalid if the broadcast flag bit in the flags
     * field is set to 1.
     *
     * @return integer
     */
    public function getSendDuration() 
    {
        return $this->_sendDuration; 
    }

    /**
     * Sets the time needed to send the file in 100-nanosecond units. This
     * value should include the duration of the last packet in the content. The
     * value of this field is invalid if the broadcast flag bit in the flags
     * field is set to 1.
     *
     * @param integer $sendDuration The time needed to send the file.
     */
    public function setSendDuration($sendDuration)
    {
        $this->_sendDuration = $sendDuration;
    }

    /**
     * Returns the amount of time to buffer data before starting to play the
     * file, in millisecond units. If this value is nonzero, the <i>Play
     * Duration</i> field and all of the payload <i>Presentation Time</i> fields
     * have been offset by this amount. Therefore, player software must subtract
     * the value in the preroll field from the play duration and presentation
     * times to calculate their actual values.
     *
     * @return integer
     */
    public function getPreroll() 
    {
        return $this->_preroll; 
    }

    /**
     * Sets the amount of time to buffer data before starting to play the file,
     * in millisecond units. If this value is nonzero, the <i>Play Duration</i>
     * field and all of the payload <i>Presentation Time</i> fields have been
     * offset by this amount. Therefore, player software must subtract the value
     * in the preroll field from the play duration and presentation times to
     * calculate their actual values.
     *
     * @param integer $preroll The amount of time to buffer data.
     */
    public function setPreroll($preroll) 
    {
        $this->_preroll = $preroll; 
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
     * Returns the flags field.
     *
     * @return integer
     */
    public function getFlags() 
    {
        return $this->_flags; 
    }

    /**
     * Sets the flags field.
     *
     * @param integer $flags The flags field.
     */
    public function setFlags($flags) 
    {
        $this->_flags = $flags; 
    }

    /**
     * Returns the minimum <i>Data Packet</i> size in bytes. In general, the
     * value of this field is invalid if the broadcast flag bit in the flags
     * field is set to 1. However, the values for the <i>Minimum Data Packet
     * Size</i> and <i>Maximum Data Packet Size</i> fields shall be set to the
     * same value, and this value should be set to the packet size, even when
     * the broadcast flag in the flags field is set to 1.
     *
     * @return integer
     */
    public function getMinimumDataPacketSize()
    {
        return $this->_minimumDataPacketSize;
    }

    /**
     * Sets the minimum <i>Data Packet</i> size in bytes. In general, the value
     * of this field is invalid if the broadcast flag bit in the flags field is
     * set to 1. However, the values for the <i>Minimum Data Packet Size</i> and
     * <i>Maximum Data Packet Size</i> fields shall be set to the same value,
     * and this value should be set to the packet size, even when the broadcast
     * flag in the flags field is set to 1.
     *
     * @param integer $minimumDataPacketSize The minimum <i>Data Packet</i> size
     *        in bytes.
     */
    public function setMinimumDataPacketSize($minimumDataPacketSize)
    {
        $this->_minimumDataPacketSize = $minimumDataPacketSize;
    }

    /**
     * Returns the maximum <i>Data Packet</i> size in bytes. In general, the
     * value of this field is invalid if the broadcast flag bit in the flags
     * field is set to 1. However, the values for the <i>Minimum Data Packet
     * Size</i> and <i>Maximum Data Packet Size</i> fields shall be set to the
     * same value, and this value should be set to the packet size, even when
     * the broadcast flag in the flags field is set to 1.
     *
     * @return integer
     */
    public function getMaximumDataPacketSize()
    {
        return $this->_maximumDataPacketSize;
    }

    /**
     * Sets the maximum <i>Data Packet</i> size in bytes. In general, the value
     * of this field is invalid if the broadcast flag bit in the flags field is
     * set to 1. However, the values for the <i>Minimum Data Packet Size</i> and
     * <i>Maximum Data Packet Size</i> fields shall be set to the same value,
     * and this value should be set to the packet size, even when the broadcast
     * flag in the flags field is set to 1.
     *
     * @param integer $maximumDataPacketSize The maximum <i>Data Packet</i> size
     *        in bytes
     */
    public function setMaximumDataPacketSize($maximumDataPacketSize)
    {
        $this->_maximumDataPacketSize = $maximumDataPacketSize;
    }

    /**
     * Returns the maximum instantaneous bit rate in bits per second for the
     * entire file. This is equal the sum of the bit rates of the individual
     * digital media streams.
     *
     * @return integer
     */
    public function getMaximumBitrate() 
    {
        return $this->_maximumBitrate; 
    }

    /**
     * Sets the maximum instantaneous bit rate in bits per second for the
     * entire file. This is equal the sum of the bit rates of the individual
     * digital media streams.
     *
     * @param integer $maximumBitrate The maximum instantaneous bit rate in bits
     *        per second.
     */
    public function setMaximumBitrate($maximumBitrate)
    {
        $this->_maximumBitrate = $maximumBitrate;
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        $this->setSize(24 /* for header */ + 80);

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeGuid($this->_fileId)
               ->writeInt64LE($this->_fileSize)
               ->writeInt64LE($this->_creationDate)
               ->writeInt64LE($this->_dataPacketsCount)
               ->writeInt64LE($this->_playDuration)
               ->writeInt64LE($this->_sendDuration)
               ->writeInt64LE($this->_preroll)
               ->writeUInt32LE($this->_flags)
               ->writeUInt32LE($this->_minimumDataPacketSize)
               ->writeUInt32LE($this->_maximumDataPacketSize)
               ->writeUInt32LE($this->_maximumBitrate);
    }
}
