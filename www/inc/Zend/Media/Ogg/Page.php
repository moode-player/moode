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
 * @subpackage Ogg
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Page.php 231 2011-05-14 13:05:48Z svollbehr $
 */

/**
 * This class represents an Ogg page. A physical bitstream consists of a sequence of Ogg pages containing data of one
 * logical bitstream only. It usually contains a group of contiguous segments of one packet only, but sometimes packets
 * are too large and need to be split over several pages.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Ogg
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Page.php 231 2011-05-14 13:05:48Z svollbehr $
 */
final class Zend_Media_Ogg_Page
{
    /**
     * The reader object.
     *
     * @var Zend_Io_Reader
     */
    private $_reader;

    /** @var string */
    private $_capturePattern;

    /** @var integer */
    private $_streamStructureVersion;

    /** @var integer */
    private $_headerTypeFlag;

    /** @var integer */
    private $_granulePosition;

    /** @var integer */
    private $_bitstreamSerialNumber;

    /** @var integer */
    private $_pageSequenceNumber;

    /** @var integer */
    private $_crcChecksum;

    /** @var integer */
    private $_numberPageSegments;

    /** @var Array */
    private $_segmentTable = array();

    /** @var integer */
    private $_size;

    /** @var integer */
    private $_headerSize;

    /** @var integer */
    private $_pageSize;

    /**
     * Constructs the class with given parameters and reads object related data
     * from the Ogg bitstream.
     *
     * @param Zend_Io_Reader $reader The reader object.
     */
    public function __construct($reader)
    {
        $this->_reader = $reader;

        $this->_capturePattern = $this->_reader->read(4);
        if ($this->_capturePattern != 'OggS') {
            require_once 'Zend/Media/Ogg/Exception.php';
            throw new Zend_Media_Ogg_Exception('Not a valid Ogg bitstream');
        }
        $this->_streamStructureVersion = $this->_reader->readUInt8();
        if ($this->_streamStructureVersion != 0) {
            require_once 'Zend/Media/Ogg/Exception.php';
            throw new Zend_Media_Ogg_Exception('Unsupported Ogg stream structure version');
        }
        $this->_headerTypeFlag = $this->_reader->readUInt8();
        $this->_granulePosition = $this->_reader->readInt64LE();
        $this->_bitstreamSerialNumber = $this->_reader->readUInt32LE();
        $this->_pageSequenceNumber = $this->_reader->readUInt32LE();
        $this->_crcChecksum = $this->_reader->readUInt32LE();
        $this->_numberPageSegments = $this->_reader->readUInt8();
        $this->_segmentTable = array();
        for ($i = 0; $i < $this->_numberPageSegments; $i++) {
            $this->_segmentTable[] = $this->_reader->readUInt8();
        }
        $this->_headerSize = $this->_numberPageSegments + 27;
        $this->_pageSize = array_sum($this->_segmentTable);
        $this->_size = $this->_headerSize + $this->_pageSize;
    }

    /**
     * Returns this page's context identifier in the bitstream.
     *
     * @return integer
     */
    public final function getHeaderTypeFlag()
    {
        return $this->_headerTypeFlag;
    }

    /**
     * Returns total samples encoded after including all packets finished on this page (packets begun on this page but
     * continuing on to the next page do not count).
     *
     * The rationale here is that the position specified in the frame header of the last page tells how long the data
     * coded by the bitstream is. A truncated stream will still return the proper number of samples that can be decoded
     * fully.
     *
     * A special value of '-1' (in two's complement) indicates that no packets finish on this page.
     *
     * @return integer
     */
    public final function getGranulePosition()
    {
        return $this->_granulePosition;
    }

    /**
     * Returns the logical bitstream serial number.
     *
     * Ogg allows for separate logical bitstreams to be mixed at page granularity in a physical bitstream. The most
     * common case would be sequential arrangement, but it is possible to interleave pages for two separate bitstreams
     * to be decoded concurrently. The serial number is the means by which pages physical pages are associated with a
     * particular logical stream. Each logical stream must have a unique serial number within a physical stream.
     *
     * @return integer
     */
    public final function getBitstreamSerialNumber()
    {
        return $this->_bitstreamSerialNumber;
    }

    /**
     * Returns the page counter; lets us know if a page is lost (useful where packets span page boundaries).
     *
     * @return integer
     */
    public final function getPageSequenceNumber()
    {
        return $this->_pageSequenceNumber;
    }

    /**
     * Returns the 32 bit CRC value (direct algorithm, initial val and final XOR = 0, generator polynomial=0x04c11db7).
     * The value is computed over the entire header (with the CRC field in the header set to zero) and then continued
     * over the page. The CRC field is then filled with the computed value.
     *
     * @return integer
     */
    public final function getCrcChecksum()
    {
        return $this->_crcChecksum;
    }

    /**
     * Returns the number of segment entries to appear in the segment table. The maximum number of 255 segments (255
     * bytes each) sets the maximum possible physical page size at 65307 bytes or just under 64kB (thus we know that a
     * header corrupted so as destroy sizing/alignment information will not cause a runaway bitstream. We'll read in the
     * page according to the corrupted size information that's guaranteed to be a reasonable size regardless, notice the
     * checksum mismatch, drop sync and then look for recapture).
     *
     * @return integer
     */
    public final function getNumberPageSegments()
    {
        return $this->_numberPageSegments;
    }

    /**
     * Returns the lacing values for each packet segment physically appearing in this page are listed in contiguous
     * order.
     *
     * @return integer
     */
    public final function getSegmentTable()
    {
        return $this->_segmentTable;
    }

    /**
     * Returns the total page size with the header in bytes.
     *
     * @return integer
     */
    public final function getSize()
    {
        return $this->_size;
    }

    /**
     * Returns the total header size in bytes.
     *
     * @return integer
     */
    public final function getHeaderSize()
    {
        return $this->_headerSize;
    }

    /**
     * Returns the total page size without the header in bytes. The page size is calculated directly from the known
     * lacing values in the segment table.
     *
     * @return integer
     */
    public final function getPageSize()
    {
        return $this->_pageSize;
    }
}
