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
 * @version    $Id: Index.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * This top-level ASF object supplies the necessary indexing information for an
 * ASF file that contains more than just a plain script-audio-video combination.
 * It includes stream-specific indexing information based on an adjustable index
 * entry time interval. The index is designed to be broken into blocks to
 * facilitate storage that is more space-efficient by using 32-bit offsets
 * relative to a 64-bit base. That is, each index block has a full 64-bit offset
 * in the block header that is added to the 32-bit offsets found in each index
 * entry. If a file is larger than 2^32 bytes, then multiple index blocks can be
 * used to fully index the entire large file while still keeping index entry
 * offsets at 32 bits.
 *
 * Indices into the <i>Index Object</i> are in terms of presentation times. The
 * corresponding <i>Offset</i> field values of the <i>Index Entry</i> byte
 * offsets that, when combined with the <i>Block Position</i> value of the
 * <i>Index Block</i>, indicate the starting location in bytes of an ASF Data
 * Packet relative to the start of the first ASF Data Packet in the file.
 *
 * An offset value of 0xFFFFFFFF is used to indicate an invalid offset value.
 * Invalid offsets signify that this particular index entry does not identify a
 * valid indexible point. Invalid offsets may occur for the initial index
 * entries of a digital media stream whose first ASF Data Packet has a non-zero
 * send time. Invalid offsets may also occur in the case where a digital media
 * stream has a large gap in the presentation time of successive objects.
 *
 * The <i>Index Object</i> is not recommended for use with files where the
 * <i>Send Time</i> of the first <i>Data Packet</i> within the <i>Data
 * Object</i> has a <i>Send Time</i> value significantly greater than zero
 * (otherwise the index itself will be sparse and inefficient).
 *
 * Any ASF file containing an <i>Index Object</i> does also contain an <i>Index
 * Parameters Object</i> in its {@link Zend_Media_Asf_Object_Header ASF Header}.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Index.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_Index extends Zend_Media_Asf_Object
{
    /**
     * Indicates that the index type is Nearest Past Data Packet. The Nearest
     * Past Data Packet indexes point to the data packet whose presentation time
     * is closest to the index entry time.
     */
    const NEAREST_PAST_DATA_PACKET = 1;

    /**
     * Indicates that the index type is Nearest Past Media. The Nearest Past
     * Object indexes point to the closest data packet containing an entire
     * object or first fragment of an object.
     */
    const NEAREST_PAST_MEDIA = 2;

    /**
     * Indicates that the index type is Nearest Past Cleanpoint. The Nearest
     * Past Cleanpoint indexes point to the closest data packet containing an
     * entire object (or first fragment of an object) that has the Cleanpoint
     * Flag set.
     *
     * Nearest Past Cleanpoint is the most common type of index.
     */
    const NEAREST_PAST_CLEANPOINT = 3;

    /** @var integer */
    private $_indexEntryTimeInterval;

    /** @var Array */
    private $_indexSpecifiers = array();

    /** @var Array */
    private $_indexBlocks = array();

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

        $this->_indexEntryTimeInterval = $this->_reader->readUInt32LE();
        $indexSpecifiersCount = $this->_reader->readUInt16LE();
        $indexBlocksCount = $this->_reader->readUInt32LE();
        for ($i = 0; $i < $indexSpecifiersCount; $i++) {
            $this->_indexSpecifiers[] = array
                ('streamNumber' => $this->_reader->readUInt16LE(),
                 'indexType' => $this->_reader->readUInt16LE());
        }
        for ($i = 0; $i < $indexBlocksCount; $i++) {
            $indexEntryCount = $this->_reader->readUInt32LE();
            $blockPositions = array();
            for ($i = 0; $i < $indexSpecifiersCount; $i++) {
                $blockPositions[] = $this->_reader->readInt64LE();
            }
            $offsets = array();
            for ($i = 0; $i < $indexSpecifiersCount; $i++) {
                $offsets[] = $this->_reader->readUInt32LE();
            }
            $this->_indexBlocks[] = array
                ('blockPositions' => $blockPositions,
                 'indexEntryOffsets' => $offsets);
        }
    }

    /**
     * Returns the time interval between each index entry in ms.
     *
     * @return integer
     */
    public function getIndexEntryTimeInterval()
    {
        return $this->_indexEntryTimeInterval;
    }

    /**
     * Returns an array of index specifiers. Each entry consists of the
     * following keys.
     *
     *   o streamNumber -- Specifies the stream number that the <i>Index
     *     Specifiers</i> refer to. Valid values are between 1 and 127.
     *
     *   o indexType -- Specifies the type of index.
     *
     * @return Array
     */
    public function getIndexSpecifiers() 
    {
        return $this->_indexSpecifiers; 
    }

    /**
     * Returns an array of index entries. Each entry consists of the following
     * keys.
     *
     *   o blockPositions -- Specifies a list of byte offsets of the beginnings
     *     of the blocks relative to the beginning of the first Data Packet (for
     *     example, the beginning of the Data Object + 50 bytes).
     *
     *   o indexEntryOffsets -- Specifies the offset. An offset value of
     *     0xffffffff indicates an invalid offset value.
     *
     * @return Array
     */
    public function getIndexBlocks() 
    {
        return $this->_indexBlocks; 
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
