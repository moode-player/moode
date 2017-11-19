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
 * @subpackage ID3
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Encr.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * To identify with which method a frame has been encrypted the encryption
 * method must be registered in the tag with the <i>Encryption method
 * registration</i> frame.
 *
 * The owner identifier a URL containing an email address, or a link to a
 * location where an email address can be found, that belongs to the
 * organisation responsible for this specific encryption method. Questions
 * regarding the encryption method should be sent to the indicated email
 * address.
 *
 * The method symbol contains a value that is associated with this method
 * throughout the whole tag, in the range 0x80-0xF0. All other values are
 * reserved. The method symbol may optionally be followed by encryption
 * specific data.
 *
 * There may be several ENCR frames in a tag but only one containing the same
 * symbol and only one containing the same owner identifier. The method must be
 * used somewhere in the tag. See {@link Zend_Media_Id3_Frame#ENCRYPTION} for
 * more information.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Encr.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Encr extends Zend_Media_Id3_Frame
{
    /** @var string */
    private $_owner;

    /** @var integer */
    private $_method;

    /** @var string */
    private $_encryptionData;

    /**
     * Constructs the class with given parameters and parses object related
     * data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        if ($this->_reader === null) {
            return;
        }

        list($this->_owner, ) =
            $this->_explodeString8
                ($this->_reader->read($this->_reader->getSize()), 2);
        $this->_reader->setOffset(strlen($this->_owner) + 1);
        $this->_method = $this->_reader->readInt8();
        $this->_encryptionData =
            $this->_reader->read($this->_reader->getSize());
    }

    /**
     * Returns the owner identifier string.
     *
     * @return string
     */
    public function getOwner() 
    {
        return $this->_owner; 
    }

    /**
     * Sets the owner identifier string.
     *

     * @param string $owner The owner identifier string.
     */
    public function setOwner($owner) 
    {
        $this->_owner = $owner; 
    }

    /**
     * Returns the method symbol.
     *
     * @return integer
     */
    public function getMethod() 
    {
        return $this->_method; 
    }

    /**
     * Sets the method symbol.
     *
     * @param integer $method The method symbol byte.
     */
    public function setMethod($method) 
    {
        $this->_method = $method; 
    }

    /**
     * Returns the encryption data.
     *
     * @return string
     */
    public function getEncryptionData() 
    {
        return $this->_encryptionData; 
    }

    /**
     * Sets the encryption data.
     *
     * @param string $encryptionData The encryption data string.
     */
    public function setEncryptionData($encryptionData)
    {
        $this->_encryptionData = $encryptionData;
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeString8($this->_owner, 1)
               ->writeInt8($this->_method)
               ->write($this->_encryptionData);
    }
}
