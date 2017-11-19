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
 * @version    $Id: TimecodeIndexParameters.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Timecode Index Parameters Object</i> supplies information about those
 * streams that are actually indexed (there must be at least one stream in an
 * index) by timecodes. All streams referred to in the
 * {@link Zend_Media_Asf_Object_TimecodeIndexParameters Timecode Index
 *  Parameters Object} must have timecode Payload Extension Systems associated
 * with them in the
 * {@link Zend_Media_Asf_Object_ExtendedStreamProperties Extended Stream
 *  Properties Object}. This object shall be present in the
 * {@link Zend_Media_Asf_Object_Header Header Object} if there is a
 * {@link Zend_Media_Asf_Object_TimecodeIndex Timecode Index Object} present in
 * the file.
 *
 * An Index Specifier is required for each stream that will be indexed by the
 * {@link Zend_Media_Asf_Object_TimecodeIndex Timecode Index Object}. These
 * specifiers must exactly match those in the
 * {@link Zend_Media_Asf_Object_TimecodeIndex Timecode Index Object}.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: TimecodeIndexParameters.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_TimecodeIndexParameters
    extends Zend_Media_Asf_Object
{
    /** @var string */
    private $_indexEntryCountInterval;

    /** @var Array */
    private $_indexSpecifiers = array();

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

        $this->_indexEntryCountInterval = $this->_reader->readUInt32LE();
        $indexSpecifiersCount = $this->_reader->readUInt16LE();
        for ($i = 0; $i < $indexSpecifiersCount; $i++) {
            $this->_indexSpecifiers[] = array
                ('streamNumber' => $this->_reader->readUInt16LE(),
                 'indexType' => $this->_reader->readUInt16LE());
        }
    }

    /**
     * Returns the interval between each index entry by the number of media
     * objects. This value cannot be 0.
     *
     * @return integer
     */
    public function getIndexEntryCountInterval()
    {
        return $this->_indexEntryCountInterval;
    }

    /**
     * Returns an array of index entries. Each entry consists of the following
     * keys.
     *
     *   o streamNumber -- Specifies the stream number that the Index Specifiers
     *     refer to. Valid values are between 1 and 127.
     *
     *   o indexType -- Specifies the type of index. Values are defined as
     *     follows:
     *       2 = Nearest Past Media Object,
     *       3 = Nearest Past Cleanpoint (1 is not a valid value).
     *     For a video stream, The Nearest Past Media Object indexes point to
     *     the closest data packet containing an entire video frame or the first
     *     fragment of a video frame, and the Nearest Past Cleanpoint indexes
     *     point to the closest data packet containing an entire video frame (or
     *     first fragment of a video frame) that is a key frame. Nearest Past
     *     Media Object is the most common value.
     *
     * @return Array
     */
    public function getIndexSpecifiers() 
    {
        return $this->_indexSpecifiers; 
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
