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
 * @version    $Id: Header.php 216 2011-05-02 14:59:16Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Object.php';
/**#@-*/

/**
 * The first part of the ID3v2 tag is the 10 byte tag header. The header
 * contains information about the tag version and options.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Header.php 216 2011-05-02 14:59:16Z svollbehr $
 */
final class Zend_Media_Id3_Header extends Zend_Media_Id3_Object
{
    /** A flag to denote whether or not unsynchronisation is applied on all
            frames */
    const UNSYNCHRONISATION = 128;

    /** A flag to denote whether or not the header is followed by an extended
            header */
    const EXTENDED_HEADER = 64;

    /** A flag used as an experimental indicator. This flag shall always be set
            when the tag is in an experimental stage. */
    const EXPERIMENTAL = 32;

    /**
     * A flag to denote whether a footer is present at the very end of the tag.
     *
     * @since ID3v2.4.0
     */
    const FOOTER = 16;

    /** @var integer */
    private $_version = 4.0;

    /** @var integer */
    private $_flags = 0;

    /** @var integer */
    private $_size;

    /**
     * Constructs the class with given parameters and reads object related data
     * from the ID3v2 tag.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        if ($reader === null)
            return;

        $this->_version = $options['version'] =
            $this->_reader->readUInt8() + $this->_reader->readUInt8() / 10;
        $this->_flags = $this->_reader->readUInt8();
        $this->_size = $this->_decodeSynchsafe32($this->_reader->readUInt32BE());
    }

    /**
     * Returns the tag version number. The version number is in the form of
     * major.revision.
     *
     * @return integer
     */
    public function getVersion() 
    {
         return $this->_version; 
    }

    /**
     * Sets the tag version number. Supported version numbers are 3.0 and 4.0
     * for ID3v2.3.0 and ID3v2.4.0 standards, respectively.
     *
     * @param integer $version The tag version number in the form of
     *                major.revision.
     */
    public function setVersion($version)
    {
        $this->setOption('version', $this->_version = $version);
    }

    /**
     * Checks whether or not the flag is set. Returns <var>true</var> if the
     * flag is set, <var>false</var> otherwise.
     *
     * @param integer $flag The flag to query.
     * @return boolean
     */
    public function hasFlag($flag) 
    {
         return ($this->_flags & $flag) == $flag; 
    }

    /**
     * Returns the flags byte.
     *
     * @return integer
     */
    public function getFlags() 
    {
         return $this->_flags; 
    }

    /**
     * Sets the flags byte.
     *
     * @param string $flags The flags byte.
     */
    public function setFlags($flags) 
    {
         $this->_flags = $flags; 
    }

    /**
     * Returns the tag size, excluding the header and the footer.
     *
     * @return integer
     */
    public function getSize() 
    {
         return $this->_size; 
    }

    /**
     * Sets the tag size, excluding the header and the footer. Called
     * automatically upon tag generation to adjust the tag size.
     *
     * @param integer $size The size of the tag, in bytes.
     */
    public function setSize($size) 
    {
         $this->_size = $size; 
    }

    /**
     * Writes the header/footer data without the identifier.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        $writer->writeUInt8(floor($this->_version))
               ->writeUInt8(($this->_version - floor($this->_version)) * 10)
               ->writeUInt8($this->_flags)
               ->writeUInt32BE($this->_encodeSynchsafe32($this->_size));
    }
}
