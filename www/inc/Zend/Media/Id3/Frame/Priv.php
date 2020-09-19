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
 * @version    $Id: Priv.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * The <i>Private frame</i> is used to contain information from a software
 * producer that its program uses and does not fit into the other frames. The
 * frame consists of an owner identifier string and the binary data. The owner
 * identifier is URL containing an email address, or a link to a location where
 * an email address can be found, that belongs to the organisation responsible
 * for the frame. Questions regarding the frame should be sent to the indicated
 * email address. The tag may contain more than one PRIV frame but only with
 * different contents.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Priv.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Priv extends Zend_Media_Id3_Frame
{
    /** @var string */
    private $_owner;

    /** @var string */
    private $_data;

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

        list($this->_owner, $this->_data) =
            $this->_explodeString8
                ($this->_reader->read($this->_reader->getSize()), 2);
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
     * Returns the private binary data associated with the frame.
     *
     * @return string
     */
    public function getData()
    {
         return $this->_data;
    }

    /**
     * Sets the private binary data associated with the frame.
     *
     * @param string $data The private binary data string.
     */
    public function setData($data)
    {
        $this->_data = $data;
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
               ->write($this->_data);
    }
}
