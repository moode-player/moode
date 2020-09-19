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
 * @version    $Id: MediaObjectIndexParameters.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Media Object Index Parameters Object</i> supplies information about
 * those streams that actually indexed (there must be at least one stream in an
 * index) by media objects. This object shall be present in the
 * {@link Zend_Media_Asf_Object_Header Header Object} if there is a
 * {@link Zend_Media_Asf_Object_MediaObjectIndex Media Object Index Object}
 * present in the file.
 *
 * An Index Specifier is required for each stream that will be indexed by the
 * {@link Zend_Media_Asf_Object_MediaObjectIndex Media Object Index Object}.
 * These specifiers must exactly match those in the
 * {@link Zend_Media_Asf_Object_MediaObjectIndex Media Object Index Object}.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: MediaObjectIndexParameters.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_MediaObjectIndexParameters
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
     *       1 = Nearest Past Data Packet,
     *       2 = Nearest Past Media Object,
     *       3 = Nearest Past Cleanpoint,
     *       0xff = Frame Number Offset.
     *     For a video stream, the Nearest Past Media Object and Nearest Past
     *     Data Packet indexes point to the closest data packet containing an
     *     entire video frame or first fragment of a video frame; Nearest Past
     *     Cleanpoint indexes point to the closest data packet containing an
     *     entire video frame (or first fragment of a video frame) that is a key
     *     frame; and Frame Number Offset indicates how many more frames need to
     *     be read for the given stream, starting with the first frame in the
     *     packet pointed to by the index entry, in order to get to the
     *     requested frame. Nearest Past Media Object is the most common value.
     *     Because ASF payloads do not contain the full frame number, there is
     *     often a Frame Number Offset index alongside one of the other types of
     *     indexes to allow the user to identify the exact frame being seeked
     *     to.
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
