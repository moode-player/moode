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
 * @version    $Id: Sign.php 177 2010-03-09 13:13:34Z svollbehr $
 * @since      ID3v2.4.0
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * This frame enables a group of frames, grouped with the
 * <i>Group identification registration</i>, to be signed. Although signatures
 * can reside inside the registration frame, it might be desired to store the
 * signature elsewhere, e.g. in watermarks. There may be more than one signature
 * frame in a tag, but no two may be identical.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Sign.php 177 2010-03-09 13:13:34Z svollbehr $
 * @since      ID3v2.4.0
 */
final class Zend_Media_Id3_Frame_Sign extends Zend_Media_Id3_Frame
{
    /** @var integer */
    private $_group;

    /** @var string */
    private $_signature;

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

        $this->_group = $this->_reader->readUInt8();
        $this->_signature = $this->_reader->read($this->_reader->getSize());
    }

    /**
     * Returns the group symbol byte.
     *
     * @return integer
     */
    public function getGroup() 
    {
        return $this->_group; 
    }

    /**
     * Sets the group symbol byte.
     *
     * @param integer $group The group symbol byte.
     */
    public function setGroup($group) 
    {
        $this->_group = $group; 
    }

    /**
     * Returns the signature binary data.
     *
     * @return string
     */
    public function getSignature() 
    {
        return $this->_signature; 
    }

    /**
     * Sets the signature binary data.
     *
     * @param string $signature The signature binary data string.
     */
    public function setSignature($signature) 
    {
        $this->_signature = $signature; 
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeUInt8($this->_group)
               ->write($this->_signature);
    }
}
