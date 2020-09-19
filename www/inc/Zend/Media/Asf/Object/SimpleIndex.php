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
 * @version    $Id: SimpleIndex.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * For each video stream in an ASF file, there should be one instance of the
 * <i>Simple Index Object</i>. Additionally, the instances of the <i>Simple
 * Index Object</i> shall be ordered by stream number.
 *
 * Index entries in the <i>Simple Index Object</i> are in terms of
 * <i>Presentation Times</i>. The corresponding <i>Packet Number</i> field
 * values (of the <i>Index Entry</i>, see below) indicate the packet number of
 * the ASF <i>Data Packet</i> with the closest past key frame. Note that for
 * video streams that contain both key frames and non-key frames, the <i>Packet
 * Number</i> field will always point to the closest past key frame.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: SimpleIndex.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_SimpleIndex extends Zend_Media_Asf_Object
{
    /** @var string */
    private $_fileId;

    /** @var integer */
    private $_indexEntryTimeInterval;

    /** @var integer */
    private $_maximumPacketCount;

    /** @var Array */
    private $_indexEntries = array();

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
        $this->_indexEntryTimeInterval = $this->_reader->readInt64LE();
        $this->_maximumPacketCount = $this->_reader->readUInt32LE();
        $indexEntriesCount = $this->_reader->readUInt32LE();
        for ($i = 0; $i < $indexEntriesCount; $i++) {
            $this->_indexEntries[] = array
                ('packetNumber' => $this->_reader->readUInt32LE(),
                 'packetCount' => $this->_reader->readUInt16LE());
        }
    }

    /**
     * Returns the unique identifier for this ASF file. The value of this field
     * should be changed every time the file is modified in any way. The value
     * of this field may be set to 0 or set to be identical to the value of the
     * <i>File ID</i> field of the <i>Data Object</i> and the <i>Header
     * Object</i>.
     *
     * @return string
     */
    public function getFileId() 
    {
        return $this->_fileId; 
    }

    /**
     * Returns the time interval between each index entry in 100-nanosecond units.
     * The most common value is 10000000, to indicate that the index entries are
     * in 1-second intervals, though other values can be used as well.
     *
     * @return integer
     */
    public function getIndexEntryTimeInterval()
    {
        return $this->_indexEntryTimeInterval;
    }

    /**
     * Returns the maximum <i>Packet Count</i> value of all <i>Index Entries</i>.
     *
     * @return integer
     */
    public function getMaximumPacketCount() 
    {
        return $this->_maximumPacketCount; 
    }

    /**
     * Returns an array of index entries. Each entry consists of the following
     * keys.
     *
     *   o packetNumber -- Specifies the number of the Data Packet associated
     *     with this index entry. Note that for video streams that contain both
     *     key frames and non-key frames, this field will always point to the
     *     closest key frame prior to the time interval.
     *
     *   o packetCount -- Specifies the number of <i>Data Packets</i> to send at
     *     this index entry. If a video key frame has been fragmented into two
     *     Data Packets, the value of this field will be equal to 2.
     *
     * @return Array
     */
    public function getIndexEntries() 
    {
        return $this->_indexEntries; 
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        require_once 'Zend/Media/Asf/Exception.php';
        throw new Zend_Media_Asf_Exception('Operation not supported');
    }
}
