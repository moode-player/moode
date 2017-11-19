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
 * @version    $Id: ErrorCorrection.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Error Correction Object</i> defines the error correction method. This
 * enables different error correction schemes to be used during content
 * creation. The <i>Error Correction Object</i> contains provisions for opaque
 * information needed by the error correction engine for recovery. For example,
 * if the error correction scheme were a simple N+1 parity scheme, then the
 * value of N would have to be available in this object.
 *

 * Note that this does not refer to the same thing as the <i>Error Correction
 * Type</i> field in the <i>{@link Zend_Media_Asf_Object_StreamProperties Stream
 * Properties Object}</i>.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: ErrorCorrection.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_ErrorCorrection extends Zend_Media_Asf_Object
{
    /** @var string */
    private $_type;

    /** @var string */
    private $_data;

    /**
     * Constructs the class with given parameters and reads object related data
     * from the ASF file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        if ($reader === null) {
            return;
        }

        $this->_type = $this->_reader->readGuid();
        $dataLength = $this->_reader->readUInt32LE();
        $this->_data = $this->_reader->read($dataLength);
    }

    /**
     * Returns the type of error correction.
     *
     * @return string
     */
    public function getType() 
    {
        return $this->_type; 
    }

    /**
     * Sets the type of error correction.
     *
     * @param string $type The type of error correction.
     */
    public function setType($type) 
    {
        $this->_type = $type; 
    }

    /**
     * Returns the data specific to the error correction scheme. The structure
     * for the <i>Error Correction Data</i> field is determined by the value
     * stored in the <i>Error Correction Type</i> field.
     *
     * @return Array
     */
    public function getData() 
    {
        return $this->_data; 
    }

    /**
     * Sets the data specific to the error correction scheme. The structure for
     * the <i>Error Correction Data</i> field is determined by the value stored
     * in the <i>Error Correction Type</i> field.
     *
     * @param Array $data The error correction specific data.
     */
    public function setData($data) 
    {
        $this->_data = $data; 
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        $this->setSize(24 /* for header */ + 20 + strlen($this->_data));
        
        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeGuid($this->_type)
               ->writeUInt32LE(strlen($this->_data))
               ->write($this->_data);
    }
}
