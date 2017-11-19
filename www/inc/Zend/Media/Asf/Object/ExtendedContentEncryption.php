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
 * @version    $Id: ExtendedContentEncryption.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Extended Content Encryption Object</i> lets authors protect content by
 * using the Windows Media Rights Manager 7 Software Development Kit (SDK).
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: ExtendedContentEncryption.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_ExtendedContentEncryption
    extends Zend_Media_Asf_Object
{
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

        $dataSize = $this->_reader->readUInt32LE();
        $this->_data = $this->_reader->read($dataSize);
    }

    /**
     * Returns the array of bytes required by the DRM client to manipulate the
     * protected content.
     *
     * @return string
     */
    public function getData() 
    {
        return $this->_data; 
    }

    /**
     * Sets the array of bytes required by the DRM client to manipulate the
     * protected content.
     *
     * @param string $data The data.
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
        $this->setSize(24 /* for header */ + 4 + strlen($this->_data));

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeUInt32LE(strlen($this->_data))
               ->write($this->_data);
    }
}
