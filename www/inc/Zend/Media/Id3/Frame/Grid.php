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
 * @version    $Id: Grid.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * The <i>Group identification registration</i> frame enables grouping of
 * otherwise unrelated frames. This can be used when some frames are to be
 * signed. To identify which frames belongs to a set of frames a group
 * identifier must be registered in the tag with this frame.
 *
 * The owner identifier is a URL containing an email address, or a link to a
 * location where an email address can be found, that belongs to the
 * organisation responsible for this grouping. Questions regarding the grouping
 * should be sent to the indicated email address.
 *
 * The group symbol contains a value that associates the frame with this group
 * throughout the whole tag, in the range 0x80-0xf0. All other values are
 * reserved. The group symbol may optionally be followed by some group specific
 * data, e.g. a digital signature. There may be several GRID frames in a tag
 * but only one containing the same symbol and only one containing the same
 * owner identifier. The group symbol must be used somewhere in the tag. See
 * {@link Zend_Media_Id3_Frame#GROUPING_IDENTITY} for more information.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Grid.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Grid extends Zend_Media_Id3_Frame
{
    /** @var string */
    private $_owner;

    /** @var integer */
    private $_group;

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

        list($this->_owner) = $this->_explodeString8
            ($this->_reader->read($this->_reader->getSize()), 2);
        $this->_reader->setOffset(strlen($this->_owner) + 1);
        $this->_group = $this->_reader->readUInt8();
        $this->_data = $this->_reader->read($this->_reader->getSize());
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
     * Returns the group symbol.
     *
     * @return integer
     */
    public function getGroup() 
    {
        return $this->_group; 
    }

    /**
     * Sets the group symbol.
     *
     * @param integer $group The group symbol.
     */
    public function setGroup($group) 
    {
        $this->_group = $group; 
    }

    /**
     * Returns the group dependent data.
     *
     * @return string
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * Sets the group dependent data.
     *
     * @param string $data The data.
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
               ->writeUInt8($this->_group)
               ->write($this->_data);
    }
}
