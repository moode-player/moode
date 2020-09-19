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
 * @version    $Id: DigitalSignature.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Digital Signature Object</i> lets authors sign the portion of their
 * header that lies between the end of the <i>File Properties Object</i> and the
 * beginning of the <i>Digital Signature Object</i>.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: DigitalSignature.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_DigitalSignature extends Zend_Media_Asf_Object
{
    /** @var integer */
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

        $this->_type = $this->_reader->readUInt32LE();
        $dataLength = $this->_reader->readUInt32LE();
        $this->_data = $this->_reader->read($dataLength);
    }

    /**
     * Returns the type of digital signature used. This field is set to 2.
     *
     * @return integer
     */
    public function getType() 
    {
        return $this->_type; 
    }

    /**
     * Sets the type of digital signature used. This field must be set to 2.
     *

     * @param integer $type The type of digital signature used.
     */
    public function setType($type) 
    {
        $this->_type = $type; 
    }

    /**
     * Returns the digital signature data.
     *
     * @return string
     */
    public function getData() 
    {
        return $this->_data; 
    }

    /**
     * Sets the digital signature data.
     *
     * @return string
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
        $this->setSize(24 /* for header */ + 8 + strlen($this->_data));

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeUInt32LE($this->_type)
               ->writeUInt32LE(strlen($this->_data))
               ->write($this->_data);
    }
}
