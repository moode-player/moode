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
 * @version    $Id: Data.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Data Object</i> contains all of the <i>Data Packet</i>s for a file.
 * These Data Packets are organized in terms of increasing send times. A <i>Data
 * Packet</i> can contain interleaved data from several digital media streams.
 * This data can consist of entire objects from one or more streams.
 * Alternatively, it can consist of partial objects (fragmentation).
 *
 * Capabilities provided within the interleave packet definition include:
 *   o Single or multiple payload types per Data Packet
 *   o Fixed-size Data Packets
 *   o Error correction information (optional)
 *   o Clock information (optional)
 *   o Redundant sample information, such as presentation time stamp (optional)
 *
 * Please note that the data packets are not parsed.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Data.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_Data extends Zend_Media_Asf_Object
{
    /** @var string */
    private $_fileId;

    /** @var integer */
    private $_totalDataPackets;

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
        $this->_totalDataPackets = $this->_reader->readInt64LE();
        $this->_reader->skip(2);

//      No support for Data Packets as of yet (if ever)
//      for ($i = 0; $i < $this->_totalDataPackets; $i++)
//        $this->_dataPackets[] =
//            new Zend_Media_Asf_Object_Data_Packet($reader);
    }

    /**
     * Returns the unique identifier for this ASF file. The value of this field
     * is changed every time the file is modified in any way. The value of this
     * field is identical to the value of the <i>File ID</i> field of the
     * <i>Header Object</i>.
     *
     * @return string
     */
    public function getFileId() 
    {
        return $this->_fileId; 
    }

    /**
     * Returns the number of ASF Data Packet entries that exist within the
     * <i>Data Object</i>. It must be equal to the <i>Data Packet Count</i>
     * field in the <i>File Properties Object</i>. The value of this field is
     * invalid if the broadcast flag field of the <i>File Properties Object</i>
     * is set to 1.
     *
     * @return integer
     */
    public function getTotalDataPackets() 
    {
        return $this->_totalDataPackets; 
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
