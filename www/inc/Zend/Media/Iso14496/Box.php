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
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS 'AS IS'
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Box.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**
 * A base class for all ISO 14496-12 boxes.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Box.php 177 2010-03-09 13:13:34Z svollbehr $
 */
class Zend_Media_Iso14496_Box
{
    /**
     * The reader object.
     *
     * @var Reader
     */
    protected $_reader;

    /** @var Array */
    private $_options;

    /** @var integer */
    private $_offset = false;

    /** @var integer */
    private $_size = false;

    /** @var string */
    private $_type;

    /** @var Zend_Media_Iso14496_Box */
    private $_parent = null;

    /** @var boolean */
    private $_container = false;

    /** @var Array */
    private $_boxes = array();

    /** @var Array */
    private static $_path = array();

    /**
     * Constructs the class with given parameters and options.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader, &$options = array())
    {
        if (($this->_reader = $reader) === null) {
            $this->_type = strtolower(substr(get_class($this), -4));
        } else {
            $this->_offset = $this->_reader->getOffset();
            $this->_size = $this->_reader->readUInt32BE();
            $this->_type = $this->_reader->read(4);

            if ($this->_size == 1) {
                $this->_size = $this->_reader->readInt64BE();
            }
            if ($this->_size == 0) {
                $this->_size = $this->_reader->getSize() - $this->_offset;
            }
            if ($this->_type == 'uuid') {
                $this->_type = $this->_reader->readGUID();
            }
        }
        $this->_options = &$options;
    }

    /**
     * Releases any references to contained boxes and the parent.
     */
    public function __destruct()
    {
        unset($this->_boxes);
        unset($this->_parent);
    }

    /**
     * Returns the options array.
     *
     * @return Array
     */
    public final function &getOptions()
    {
        return $this->_options;
    }

    /**
     * Returns the given option value, or the default value if the option is not
     * defined.
     *
     * @param string $option The name of the option.
     * @param mixed $defaultValue The default value to be returned.
     */
    public final function getOption($option, $defaultValue = null)
    {
        if (isset($this->_options[$option])) {
            return $this->_options[$option];
        }
        return $defaultValue;
    }

    /**
     * Sets the options array. See {@link Zend_Media_Id3v2} class for available
     * options.
     *
     * @param Array $options The options array.
     */
    public final function setOptions(&$options)
    {
        $this->_options = &$options;
    }

    /**
     * Sets the given option the given value.
     *
     * @param string $option The name of the option.
     * @param mixed $value The value to set for the option.
     */
    public final function setOption($option, $value)
    {
        $this->_options[$option] = $value;
    }

    /**
     * Clears the given option value.
     *
     * @param string $option The name of the option.
     */
    public final function clearOption($option)
    {
        unset($this->_options[$option]);
    }

    /**
     * Returns the file offset to box start, or <var>false</var> if the box was
     * created on heap.
     *
     * @return integer
     */
    public final function getOffset() 
    {
        return $this->_offset; 
    }

    /**
     * Sets the file offset where the box starts.
     *
     * @param integer $offset The file offset to box start.
     */
    public final function setOffset($offset) 
    {
        $this->_offset = $offset; 
    }

    /**
     * Returns the box size in bytes read from the file, including the size and
     * type header, fields, and all contained boxes, or <var>false</var> if the
     * box was created on heap.
     *
     * @return integer
     */
    public final function getSize()
    {
        return $this->_size; 
    }

    /**
     * Sets the box size. The size must include the size and type header,
     * fields, and all contained boxes.
     *
     * The method will propagate size change to box parents.
     *
     * @param integer $size The box size.
     */
    protected final function setSize($size)
    {
        if ($this->_parent !== null) {
            $this->_parent->setSize
                (($this->_parent->getSize() > 0 ?
                  $this->_parent->getSize() : 0) +
                 $size - ($this->_size > 0 ? $this->_size : 0));
        }
        $this->_size = $size;
    }

    /**
     * Returns the box type.
     *
     * @return string
     */
    public final function getType() 
    {
        return $this->_type; 
    }

    /**
     * Sets the box type.
     *
     * @param string $type The box type.
     */
    public final function setType($type) 
    {
        $this->_type = $type; 
    }

    /**
     * Returns the parent box containing this box.
     *
     * @return Zend_Media_Iso14496_Box
     */
    public final function getParent() 
    {
        return $this->_parent; 
    }

    /**
     * Sets the parent containing box.
     *
     * @param Zend_Media_Iso14496_Box $parent The parent box.
     */
    public function setParent(&$parent) 
    {
        $this->_parent = $parent;
    }

    /**
     * Returns a boolean value corresponding to whether the box is a container.
     *
     * @return boolean
     */
    public final function isContainer() 
    {
        return $this->_container;
    }

    /**
     * Returns a boolean value corresponding to whether the box is a container.
     *
     * @return boolean
     */
    public final function getContainer() 
    {
        return $this->_container;
    }

    /**
     * Sets whether the box is a container.
     *
     * @param boolean $container Whether the box is a container.
     */
    protected final function setContainer($container)
    {
        $this->_container = $container;
    }

    /**
     * Reads and constructs the boxes found within this box.
     *
     * @todo Does not parse iTunes internal ---- boxes.
     */
    protected final function constructBoxes
        ($defaultclassname = 'Zend_Media_Iso14496_Box')
    {
        $base = $this->getOption('base', '');
        if ($this->getType() != 'file') {
            self::$_path[] = $this->getType();
        }
        $path = implode(self::$_path, '.');

        while (true) {
            $offset = $this->_reader->getOffset();
            if ($offset >= $this->_offset + $this->_size) {
                break;
            }
            $size = $this->_reader->readUInt32BE();
            $type = rtrim($this->_reader->read(4), ' ');
            if ($size == 1) {
                $size = $this->_reader->readInt64BE();
            }
            if ($size == 0) {
                $size = $this->_reader->getSize() - $offset;
            }

            if (preg_match("/^\xa9?[a-z0-9]{3,4}$/i", $type) &&
                substr($base, 0, min(strlen($base), strlen
                       ($tmp = $path . ($path ? '.' : '') . $type))) ==
                substr($tmp,  0, min(strlen($base), strlen($tmp))))
            {
                $this->_reader->setOffset($offset);
                if (@fopen($filename = 'Zend/Media/Iso14496/Box/' .
                           ucfirst($type) . '.php', 'r', true) !== false) {
                    require_once($filename);
                }
                if (class_exists
                    ($classname = 'Zend_Media_Iso14496_Box_' .
                     ucfirst($type))) {
                    $box = new $classname($this->_reader, $this->_options);
                } else {
                    $box =
                        new $defaultclassname($this->_reader, $this->_options);
                }
                $box->setParent($this);
                if (!isset($this->_boxes[$box->getType()])) {
                    $this->_boxes[$box->getType()] = array();
                }
                $this->_boxes[$box->getType()][] = $box;
            }
            $this->_reader->setOffset($offset + $size);
        }

        array_pop(self::$_path);
    }

    /**
     * Checks whether the box given as an argument is present in the file. Returns
     * <var>true</var> if one or more boxes are present, <var>false</var>
     * otherwise.
     *
     * @param string $identifier The box identifier.
     * @return boolean
     * @throws Zend_Media_Iso14496_Exception if called on a non-container box
     */
    public final function hasBox($identifier)
    {
        if (!$this->isContainer()) {
            require_once 'Zend/Media/Iso14496/Exception.php';
            throw new Zend_Media_Iso14496_Exception('Box not a container');
        }
        return isset($this->_boxes[$identifier]);
    }

    /**
     * Returns all the boxes the file contains as an associate array. The box
     * identifiers work as keys having an array of boxes as associated value.
     *
     * @return Array
     * @throws Zend_Media_Iso14496_Exception if called on a non-container box
     */
    public final function getBoxes()
    {
        if (!$this->isContainer()) {
            require_once 'Zend/Media/Iso14496/Exception.php';
            throw new Zend_Media_Iso14496_Exception('Box not a container');
        }
        return $this->_boxes;
    }

    /**
     * Returns an array of boxes matching the given identifier or an empty array
     * if no boxes matched the identifier.
     *
     * The identifier may contain wildcard characters '*' and '?'. The asterisk
     * matches against zero or more characters, and the question mark matches
     * any single character.
     *
     * Please note that one may also use the shorthand $obj->identifier to
     * access the first box with the identifier given. Wildcards cannot be used
     * with the shorthand and they will not work with user defined uuid types.
     *
     * @param string $identifier The box identifier.
     * @return Array
     * @throws Zend_Media_Iso14496_Exception if called on a non-container box
     */
    public final function getBoxesByIdentifier($identifier)
    {
        if (!$this->isContainer()) {
            require_once 'Zend/Media/Iso14496/Exception.php';
            throw new Zend_Media_Iso14496_Exception('Box not a container');
        }
        $matches = array();
        $searchPattern = "/^" .
            str_replace(array("*", "?"), array(".*", "."), $identifier) . "$/i";
        foreach ($this->_boxes as $identifier => $boxes) {
            if (preg_match($searchPattern, $identifier)) {
                foreach ($boxes as $box) {
                    $matches[] = $box;
                }
            }
        }
        return $matches;
    }

    /**
     * Removes any boxes matching the given box identifier.
     *
     * The identifier may contain wildcard characters '*' and '?'. The asterisk
     * matches against zero or more characters, and the question mark matches any
     * single character.
     *
     * One may also use the shorthand unset($obj->identifier) to achieve the same
     * result. Wildcards cannot be used with the shorthand method.
     *
     * @param string $identifier The box identifier.
     * @throws Zend_Media_Iso14496_Exception if called on a non-container box
     */
    public final function removeBoxesByIdentifier($identifier)
    {
        if (!$this->isContainer()) {
            require_once 'Zend/Media/Iso14496/Exception.php';
            throw new Zend_Media_Iso14496_Exception("Box not a container");
        }
        $searchPattern = "/^" .
            str_replace(array("*", "?"), array(".*", "."), $identifier) . "$/i";
        foreach ($this->_objects as $identifier => $objects) {
            if (preg_match($searchPattern, $identifier)) {
                unset($this->_objects[$identifier]);
            }
        }
    }

    /**
     * Adds a new box into the current box and returns it.
     *
     * @param Zend_Media_Iso14496_Box $box The box to add
     * @return Zend_Media_Iso14496_Box
     * @throws Zend_Media_Iso14496_Exception if called on a non-container box
     */
    public final function addBox(&$box)
    {
        if (!$this->isContainer()) {
            require_once 'Zend/Media/Iso14496/Exception.php';
            throw new Zend_Media_Iso14496_Exception('Box not a container');
        }
        $box->setParent($this);
        $box->setOptions($this->_options);
        if (!$this->hasBox($box->getType())) {
            $this->_boxes[$box->getType()] = array();
        }
        return $this->_boxes[$box->getType()][] = $box;
    }

    /**
     * Removes the given box.
     *
     * @param Zend_Media_Iso14496_Box $box The box to remove
     * @throws Zend_Media_Iso14496_Exception if called on a non-container box
     */
    public final function removeBox($box)
    {
        if (!$this->isContainer()) {
            require_once 'Zend/Media/Iso14496/Exception.php';
            throw new Zend_Media_Iso14496_Exception('Box not a container');
        }
        if ($this->hasBox($box->getType())) {
            foreach ($this->_boxes[$box->getType()] as $key => $value) {
                if ($box === $value) {
                    unset($this->_boxes[$box->getType()][$key]);
                }
            }
        }
    }

    /**
     * Returns the number of boxes this box contains.
     *
     * @return integer
     */
    public final function getBoxCount()
    {
        if (!$this->isContainer()) {
            return 0;
        }
        return count($this->_boxes);
    }

    /**
     * Magic function so that $obj->value will work. If called on a container box,
     * the method will first attempt to return the first contained box that
     * matches the identifier, and if not found, invoke a getter method.
     *
     * If there are no boxes or getter methods with given name, the method
     * attempts to create a frame with given identifier.
     *
     * If none of these work, an exception is thrown.
     *
     * @param string $name The box or field name.
     * @return mixed
     */
    public function __get($name)
    {
        if ($this->isContainer() &&
                isset($this->_boxes[str_pad($name, 4, ' ')])) {
            return $this->_boxes[str_pad($name, 4, ' ')][0];
        }
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func(array($this, 'get' . ucfirst($name)));
        }
        if (@fopen($filename = 'Zend/Media/Iso14496/Box/' .
                   ucfirst($name) . '.php', 'r', true) !== false) {
            require_once($filename);
        }
        if (class_exists
            ($classname = 'Zend_Media_Iso14496_Box_' . ucfirst($name))) {
            return $this->addBox(new $classname());
        }
        require_once 'Zend/Media/Iso14496/Exception.php';
        throw new Zend_Media_Iso14496_Exception('Unknown box/field: ' . $name);
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
        if (method_exists($this, 'set' . ucfirst($name))) {
            call_user_func(array($this, 'set' . ucfirst($name)), $value);
        } else {
            require_once 'Zend/Media/Iso14496/Exception.php';
            throw new Zend_Media_Iso14496_Exception('Unknown field: ' . $name);
        }
    }

    /**
     * Magic function so that isset($obj->value) will work. This method checks
     * whether the box is a container and contains a box that matches the
     * identifier.
     *
     * @param string $name The box name.
     * @return boolean
     */
    public function __isset($name)
    {
        return ($this->isContainer() && isset($this->_boxes[$name]));
    }

    /**
     * Magic function so that unset($obj->value) will work. This method removes
     * all the boxes from this container that match the identifier.
     *
     * @param string $name The box name.
     */
    public function __unset($name)
    {
        if ($this->isContainer()) {
            unset($this->_boxes[$name]);
        }
    }

    /**
     * Returns the box heap size in bytes, including the size and
     * type header, fields, and all contained boxes. The box size is updated to
     * reflect that of the heap size upon write. Subclasses should overwrite
     * this method and call the parent method to get the calculated header and
     * subbox sizes and then add their own bytes to that.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        $size = 8;
        if ($this->isContainer()) {
            foreach ($this->getBoxes() as $name => $boxes) {
                foreach ($boxes as $box) {
                    $size += $box->getHeapSize();
                }
            }
        }
        if ($size > 0xffffffff) {
            $size += 8;
        }
        if (strlen($this->_type) > 4) {
            $size += 16;
        }
        return $size;
    }

    /**
     * Writes the box header. Subclasses should overwrite this method and call
     * the parent method first and then write the box related data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        if (get_class($this) == "Zend_Media_Iso14496_Box") {
            require_once 'Zend/Media/Iso14496/Exception.php';
            throw new Zend_Media_Iso14496_Exception
                ('Unknown box \'' . $this->getType() . '\' cannot be written.');
        }

        $this->_size = $this->getHeapSize();
        if ($this->_size > 0xffffffff) {
            $writer->writeUInt32BE(1);
        } else {
            $writer->writeUInt32BE($this->_size);
        }
        if (strlen($this->_type) > 4) {
            $writer->write('uuid');
        } else {
            $writer->write($this->_type);
        }
        if ($this->_size > 0xffffffff) {
            $writer->writeInt64BE($this->_size);
        }
        if (strlen($this->_type) > 4) {
            $writer->writeGuid($this->_type);
        }
    }
    
    /**
     * Writes the frame data with the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        if (get_class($this) == "Zend_Media_Iso14496_Box") {
            require_once 'Zend/Media/Iso14496/Exception.php';
            throw new Zend_Media_Iso14496_Exception
                ('Unknown box \'' . $this->getType() . '\' cannot be written.');
        }

        $this->_writeData($writer);
        if ($this->isContainer()) {
            foreach ($this->getBoxes() as $name => $boxes) {
                foreach ($boxes as $box) {
                    $box->write($writer);
                }
            }
        }
    }
}
