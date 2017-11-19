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
 * @version    $Id: BandwidthSharing.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Bandwidth Sharing Object</i> indicates streams that share bandwidth in
 * such a way that the maximum bandwidth of the set of streams is less than the
 * sum of the maximum bandwidths of the individual streams. There should be one
 * instance of this object for each set of objects that share bandwidth. Whether
 * or not this object can be used meaningfully is content-dependent.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: BandwidthSharing.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_BandwidthSharing extends Zend_Media_Asf_Object
{
    const SHARING_EXCLUSIVE = 'af6060aa-5197-11d2-b6af-00c04fd908e9';
    const SHARING_PARTIAL = 'af6060ab-5197-11d2-b6af-00c04fd908e9';

    /** @var string */
    private $_sharingType;

    /** @var integer */
    private $_dataBitrate;

    /** @var integer */
    private $_bufferSize;

    /** @var Array */
    private $_streamNumbers = array();

    /**
     * Constructs the class with given parameters and reads object related data
     * from the ASF file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader, &$options = array())
    {
        parent::__construct($reader = null, $options);

        if ($reader === null) {
            return;
        }

        $this->_sharingType = $this->_reader->readGuid();
        $this->_dataBitrate = $this->_reader->readUInt32LE();
        $this->_bufferSize  = $this->_reader->readUInt32LE();
        $streamNumbersCount = $this->_reader->readUInt16LE();
        for ($i = 0; $i < $streamNumbersCount; $i++) {
            $this->_streamNumbers[] = $this->_reader->readUInt16LE();
        }
    }

    /**
     * Returns the type of sharing relationship for this object. Two types are
     * predefined: SHARING_PARTIAL, in which any number of the streams in the
     * relationship may be streaming data at any given time; and
     * SHARING_EXCLUSIVE, in which only one of the streams in the relationship
     * may be streaming data at any given time.
     *
     * @return string
     */
    public function getSharingType() 
    {
        return $this->_sharingType; 
    }

    /**
     * Sets the type of sharing relationship for this object. Two types are
     * predefined: SHARING_PARTIAL, in which any number of the streams in the
     * relationship may be streaming data at any given time; and
     * SHARING_EXCLUSIVE, in which only one of the streams in the relationship
     * may be streaming data at any given time.
     *
     * @return string
     */
    public function setSharingType($sharingType)
    {
        $this->_sharingType = $sharingType;
    }

    /**
     * Returns the leak rate R, in bits per second, of a leaky bucket that
     * contains the data portion of all of the streams, excluding all ASF Data
     * Packet overhead, without overflowing. The size of the leaky bucket is
     * specified by the value of the Buffer Size field. This value can be less
     * than the sum of all of the data bit rates in the
     * {@link Zend_Media_Asf_Object_ExtendedStreamProperties Extended Stream
     * Properties} Objects for the streams contained in this bandwidth-sharing
     * relationship.
     *
     * @return integer
     */
    public function getDataBitrate() 
    {
        return $this->_dataBitrate; 
    }

    /**
     * Sets the leak rate R, in bits per second, of a leaky bucket that contains
     * the data portion of all of the streams, excluding all ASF Data Packet
     * overhead, without overflowing. The size of the leaky bucket is specified
     * by the value of the Buffer Size field. This value can be less than the
     * sum of all of the data bit rates in the
     * {@link Zend_Media_Asf_Object_ExtendedStreamProperties Extended Stream
     * Properties} Objects for the streams contained in this bandwidth-sharing
     * relationship.
     *
     * @param integer $dataBitrate The data bitrate.
     */
    public function setDataBitrate($dataBitrate)
    {
        $this->_dataBitrate = $dataBitrate;
    }

    /**
     * Specifies the size B, in bits, of the leaky bucket used in the Data
     * Bitrate definition. This value can be less than the sum of all of the
     * buffer sizes in the
     * {@link Zend_Media_Asf_Object_ExtendedStreamProperties Extended Stream
     * Properties} Objects for the streams contained in this bandwidth-sharing
     * relationship.
     *
     * @return integer
     */
    public function getBufferSize() 
    {
        return $this->_bufferSize; 
    }

    /**
     * Sets the size B, in bits, of the leaky bucket used in the Data Bitrate
     * definition. This value can be less than the sum of all of the buffer
     * sizes in the
     * {@link Zend_Media_Asf_Object_ExtendedStreamProperties Extended Stream
     * Properties} Objects for the streams contained in this bandwidth-sharing
     * relationship.
     *
     * @param integer $bufferSize The buffer size.
     */
    public function setBufferSize($bufferSize)
    {
        $this->_bufferSize = $bufferSize;
    }

    /**
     * Returns an array of stream numbers.
     *
     * @return Array
     */
    public function getStreamNumbers() 
    {
        return $this->_streamNumbers; 
    }

    /**
     * Sets the array of stream numbers.
     *
     * @param Array $streamNumbers The array of stream numbers.
     */
    public function setStreamNumbers($streamNumbers)
    {
        $this->_streamNumbers = $streamNumbers;
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        $streamNumbersCount = count($this->_streamNumber);

        $this->setSize(24 /* for header */ + 28 + $streamNumbersCount * 2);

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeGuid($this->_sharingType)
               ->writeUInt32LE($this->_dataBitrate)
               ->writeUInt32LE($this->_bufferSize)
               ->writeUInt16LE($streamNumbersCount);
        for ($i = 0; $i < $streamNumbersCount; $i++) {
            $writer->writeUInt16LE($this->_streamNumbers[$i]);
        }
    }
}
