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
 * @version    $Id: Compatibility.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Compatibility Object</i> is reserved for future use.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Compatibility.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_Compatibility extends Zend_Media_Asf_Object
{
    /** @var integer */
    private $_profile;

    /** @var integer */
    private $_mode;

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

        $this->_profile = $this->_reader->readUInt8();
        $this->_mode = $this->_reader->readUInt8();
    }

    /**
     * Returns the profile field. This field is reserved and is set to 2.
     *
     * @return integer
     */
    public function getProfile() 
    {
        return $this->_profile; 
    }

    /**
     * Returns the profile field. This field is reserved and is set to 2.
     *
     * @param integer $profile The profile.
     */
    public function setProfile($profile) 
    {
        $this->_profile = $profile; 
    }

    /**
     * Returns the mode field. This field is reserved and is set to 1.
     *
     * @return integer
     */
    public function getMode() 
    {
        return $this->_mode; 
    }

    /**
     * Sets the mode field. This field is reserved and is set to 1.
     *
     * @param integer $mode The mode.
     */
    public function setMode($mode) 
    {
        $this->_mode = $mode; 
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        $this->setSize(24 /* for header */ + 2);

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeUInt8($this->_profile)
               ->writeUInt8($this->_mode);
    }
}
