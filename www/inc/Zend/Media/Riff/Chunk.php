<?php
/**
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: Chunk.php 257 2012-01-26 05:30:58Z svollbehr $
 */

/**
 * This class represents the basic building block of a RIFF file, called a chunk.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: Chunk.php 257 2012-01-26 05:30:58Z svollbehr $
 */
abstract class Zend_Media_Riff_Chunk
{
    /**
     * The reader object.
     *
     * @var Reader
     */
    protected $_reader;

    /** @var integer */
    protected $_identifier;

    /** @var integer */
    protected $_size;

    /**
     * Constructs the class with given parameters and options.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     */
    public function __construct($reader)
    {
        $this->_reader = $reader;
        $this->_identifier = $this->_reader->read(4);
        $this->_size = $this->_reader->readUInt32LE();
    }

    /**
     * Returns a four-character code that identifies the representation of the chunk data. A program reading a RIFF file
     * can skip over any chunk whose chunk ID it doesn't recognize; it simply skips the number of bytes specified by
     * size plus the pad byte, if present.
     *
     * @return string
     */
    public final function getIdentifier()
    {
        return $this->_identifier;
    }

    /**
     * Sets the four-character code that identifies the representation of the chunk data.
     *
     * @param string $identifier The chunk identifier.
     */
    public final function setIdentifier($identifier)
    {
        $this->_identifier = $identifier;
    }

    /**
     * Returns the size of chunk data. This size value does not include the size of the identifier or size fields or the
     * pad byte at the end of chunk data.
     *
     * @return integer
     */
    public final function getSize()
    {
        return $this->_size; 
    }

    /**
     * Sets the size of chunk data. This size value must not include the size of the identifier or size fields or the
     * pad byte at the end of chunk data.
     *
     * @param integer $size The size of chunk data.
     */
    public final function setSize($size)
    {
        $this->_size = $size;
    }

    /**
     * Magic function so that $obj->value will work.
     *
     * @param string $name The field name.
     * @return mixed
     */
    public function __get($name)
    {
        if (method_exists($this, 'get' . ucfirst(strtolower($name)))) {
            return call_user_func(array($this, 'get' . ucfirst(strtolower($name))));
        } else {
            require_once('Zend/Media/Riff/Exception.php');
            throw new Zend_Media_Riff_Exception('Unknown field: ' . $name);
        }
    }

    /**
     * Magic function so that assignments with $obj->value will work.
     *
     * @param string $name  The field name.
     * @param string $value The field value.
     * @return mixed
     */
    public function __set($name, $value)
    {
        if (method_exists($this, 'set' . ucfirst(strtolower($name)))) {
            call_user_func(array($this, 'set' . ucfirst(strtolower($name))), $value);
        } else {
            require_once('Zend/Media/Riff/Exception.php');
            throw new Zend_Media_Riff_Exception('Unknown field: ' . $name);
        }
    }
}
