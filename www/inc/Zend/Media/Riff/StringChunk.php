<?php
/**
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: StringChunk.php 257 2012-01-26 05:30:58Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Riff/Chunk.php';
/**#@-*/

/**
 * This class represents a chunk that contains a text string.
 *
 * {{@internal The contained text string is a NULL-terminated string (ZSTR) that consists of a series of characters
 * followed by a terminating NULL character. The ZSTR is better than a simple character sequence (STR) because many
 * programs are easier to write if strings are NULL-terminated. ZSTR is preferred to a string with a size prefix (BSTR
 * or WSTR) because the size of the string is already available as the chunk size value, minus one for the terminating
 * NULL character.}}
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: StringChunk.php 257 2012-01-26 05:30:58Z svollbehr $
 */
abstract class Zend_Media_Riff_StringChunk extends Zend_Media_Riff_Chunk
{
    /** @var string */
    protected $_value;

    /**
     * Constructs the class with given parameters and options.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     */
    public function __construct($reader)
    {
        parent::__construct($reader);
        $this->_value = rtrim($this->_reader->read($this->_size), "\0");
    }

    /**
     * Returns the text string value.
     *
     * @return string
     */
    public final function getValue()
    {
        return $this->_value;
    }

    /**
     * Sets the text string value.
     *
     * @param string $type The text string value.
     */
    public final function setValue($value)
    {
        $this->_value = $value;
    }
}
