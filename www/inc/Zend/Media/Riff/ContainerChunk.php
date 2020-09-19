<?php
/**
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: ContainerChunk.php 257 2012-01-26 05:30:58Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Riff/Chunk.php';
/**#@-*/

/**
 * This class represents a container chunk, ie a chunk that contains multiple other chunks.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: ContainerChunk.php 257 2012-01-26 05:30:58Z svollbehr $
 */
abstract class Zend_Media_Riff_ContainerChunk extends Zend_Media_Riff_Chunk
{
    /** @var string */
    protected $_type;

    /** @var Array */
    private $_chunks = array();

    /**
     * Constructs the class with given parameters and options.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     */
    public function __construct($reader)
    {
        parent::__construct($reader);
        $startOffset = $this->_reader->getOffset();
        $this->_type = $this->_reader->read(4);
        while (($this->_reader->getOffset() - $startOffset) < $this->_size) {
            $offset = $this->_reader->getOffset();

            $identifier = $this->_reader->read(4);
            $size = $this->_reader->readUInt32LE();

            $this->_reader->setOffset($offset);
            if (@fopen
                    ($file = 'Zend/Media/Riff/Chunk/' .
                     ucfirst(strtolower(rtrim($identifier, ' '))) . '.php', 'r', true) !== false) {
                require_once($file);
            }
            if (class_exists($classname = 'Zend_Media_Riff_Chunk_' . ucfirst(strtolower(rtrim($identifier, ' '))))) {
                $this->_chunks[] = new $classname($this->_reader);

                $this->_reader->setOffset($offset + 8 + $size);
            } else {
                trigger_error('Unknown RIFF chunk: \'' . $identifier . '\' skipped', E_USER_WARNING);
                $this->_reader->skip(8 + $size);
            }
        }
    }

    /**
     * Returns a four-character code that identifies the contents of the container chunk.
     *
     * @return string
     */
    public final function getType()
    {
        return $this->_type;
    }

    /**
     * Sets the four-character code that identifies the contents of the container chunk.
     *
     * @param string $type The chunk container type.
     */
    public final function setType($type)
    {
        $this->_type = $type;
    }

    /**
     * Returns all the chunks this chunk contains as an array.
     *
     * @return Array
     */
    public final function getChunks()
    {
        return $this->_chunks;
    }

    /**
     * Returns an array of chunks matching the given identifier or an empty array if no chunks matched the identifier.
     *
     * The identifier may contain wildcard characters '*' and '?'. The asterisk matches against zero or more characters,
     * and the question mark matches any single character.
     *
     * Please note that one may also use the shorthand $obj->identifier to access the first chunk with the identifier
     * given. Wildcards cannot be used with the shorthand.
     *
     * @param string $identifier The chunk identifier.
     * @return Array
     */
    public final function getChunksByIdentifier($identifier)
    {
        $matches = array();
        $searchPattern = "/^" . str_replace(array("*", "?"), array(".*", "."), $identifier) . "$/i";
        foreach ($this->_chunks as $chunk) {
            if (preg_match($searchPattern, rtrim($chunk->getIdentifier(), ' '))) {
                $matches[] = $chunk;
            }
        }
        return $matches;
    }

    /**
     * Magic function so that $obj->value will work. The method will first attempt to return the first contained chunk
     * whose identifier matches the given name, and if not found, invoke a getter method.
     *
     * If there are no chunks or getter methods with the given name, an exception is thrown.
     *
     * @param string $name The chunk or field name.
     * @return mixed
     */
    public function __get($name)
    {
        $chunks = $this->getChunksByIdentifier($name);
        if (count($chunks) > 0) {
            return $chunks[0];
        }
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func(array($this, 'get' . ucfirst($name)));
        }
        require_once 'Zend/Media/Riff/Exception.php';
        throw new Zend_Media_Riff_Exception('Unknown chunk/field: ' . $name);
    }
}
