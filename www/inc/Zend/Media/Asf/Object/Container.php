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
 * @version    $Id: Container.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * An abstract base container class that contains other ASF objects.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Container.php 177 2010-03-09 13:13:34Z svollbehr $
 */
abstract class Zend_Media_Asf_Object_Container extends Zend_Media_Asf_Object
{
    /** @var Array */
    private $_objects = array();

    /**
     * Reads and constructs the objects found within this object.
     */
    protected final function constructObjects($defaultclassnames = array())
    {
        while (true) {
            $offset = $this->_reader->getOffset();
            if ($offset >= $this->getOffset() + $this->getSize()) {
                break;
            }
            $guid = $this->_reader->readGuid();
            $size = $this->_reader->readInt64LE();

            $this->_reader->setOffset($offset);
            if (isset($defaultclassnames[$guid])) {
                if (@fopen($filename = 'Zend/Media/Asf/Object/' .
                           $defaultclassnames[$guid] . '.php',
                           'r', true) !== false) {
                    require_once($filename);
                }
                if (class_exists
                    ($classname = 'Zend_Media_Asf_Object_' .
                     $defaultclassnames[$guid])) {
                    $object = new $classname($this->_reader, $this->_options);
                 } else {
                    require_once 'Zend/Media/Asf/Object/Unknown.php';
                    $object = new Zend_Media_Asf_Object_Unknown
                        ($this->_reader, $this->_options);
                 }
            } else {
                require_once 'Zend/Media/Asf/Object/Unknown.php';
                $object = new Zend_Media_Asf_Object_Unknown
                    ($this->_reader, $this->_options);
            }
            $object->setParent($this);
            if (!$this->hasObject($object->getIdentifier())) {
                $this->_objects[$object->getIdentifier()] = array();
            }
            $this->_objects[$object->getIdentifier()][] = $object;
            $this->_reader->setOffset($offset + $size);
        }
    }

    /**
     * Checks whether the object with given identifier is present in the file.
     * The identifier can either be the object GUID, or name of the constant
     * containing the GUID, or the name of the object class.
     *
     * Returns <var>true</var> if one or more objects are present,
     * <var>false</var> otherwise.
     *
     * @param string $identifier The object GUID, name of the GUID constant, or
     *        object class name.
     * @return boolean
     */
    public final function hasObject($identifier)
    {
        if (defined($constname = get_class($this) . '::' . strtoupper
                    (preg_replace('/[A-Z]/', '_$0', $identifier)))) {
            $objects = $this->getObjectsByIdentifier(constant($constname));
            return isset($objects[0]);
        } else {
            return isset($this->_objects[$identifier]);
        }
    }

    /**
     * Returns all the objects the file contains as an associate array. The
     * object identifiers work as keys having an array of ASF objects as
     * associated value.
     *
     * @return Array
     */
    public final function getObjects() 
    {
        return $this->_objects; 
    }

    /**
     * Returns an array of objects matching the given object GUID or an empty
     * array if no object matched the identifier.
     *
     * The identifier may contain wildcard characters '*' and '?'. The asterisk
     * matches against zero or more characters, and the question mark matches
     * any single character.
     *
     * @param string $identifier The object GUID.
     * @return Array
     */
    public final function getObjectsByIdentifier($identifier)
    {
        $matches = array();
        $searchPattern = '/^' .
            str_replace(array("*", "?"), array(".*", "."), $identifier) . "$/i";
        foreach ($this->_objects as $identifier => $objects) {
            if (preg_match($searchPattern, $identifier)) {
                foreach ($objects as $object) {
                    $matches[] = $object;
                }
            }
        }
        return $matches;
    }

    /**
     * Returns an array of objects matching the given object constant name or an
     * empty array if no object matched the name.
     *
     * The object constant name can be given in three forms; either using the
     * full name of the constant, the name of the class or the shorthand style
     * of the class name having its first letter in lower case.
     *
     * One may use the shorthand $obj->name to access the first box with the
     * name given directly. Shorthands will not work with user defined uuid
     * types.
     *
     * The name may not contain wildcard characters.
     *
     * @param string $name The object constant name or class name.
     * @return Array
     */
    public final function getObjectsByName($name)
    {
        if (defined($constname = get_class($this) . '::' . $name) ||
            defined($constname = get_class($this) . '::' . strtoupper
                    (preg_replace
                     ('/^_/', '', preg_replace('/[A-Z]/', '_$0', $name))))) {
            return $this->getObjectsByIdentifier(constant($constname));
        }
        return array();
    }

    /**
     * Removes any objects matching the given object GUID.
     *
     * The identifier may contain wildcard characters '*' and '?'. The asterisk
     * matches against zero or more characters, and the question mark matches
     * any single character.
     *
     * One may also use the shorthand unset($obj->name) to achieve the same
     * result. Wildcards cannot be used with the shorthand method.
     *
     * @param string $identifier The object GUID.
     */
    public final function removeObjectsByIdentifier($identifier)
    {
        $searchPattern = '/^' .
            str_replace(array("*", "?"), array(".*", "."), $identifier) . "$/i";
        foreach ($this->_objects as $identifier => $objects) {
            if (preg_match($searchPattern, $identifier)) {
                unset($this->_objects[$identifier]);
            }
        }
    }

    /**
     * Removes any objects matching the given object name.
     *
     * The name can be given in three forms; either using the full name of the
     * constant, the name of the class or the shorthand style of the class name
     * having its first letter in lower case.
     *
     * One may also use the shorthand unset($obj->name) to achieve the same
     * result.
     *
     * The name may not contain wildcard characters.
     *
     * @param string $name The object constant name or class name.
     */
    public final function removeObjectsByName($name)
    {
        if (defined($constname = get_class($this) . '::' . strtoupper
                    (preg_replace('/[A-Z]/', '_$0', $name)))) {
            unset($this->_objects[constant($constname)]);
        }
    }

    /**
     * Adds a new object into the current object and returns it.
     *
     * @param Zend_Media_Asf_Object $object The object to add
     * @return Zend_Media_Asf_Object
     */
    public final function addObject($object)
    {
        $object->setParent($this);
        $object->setOptions($this->_options);
        if (!$this->hasObject($object->getIdentifier())) {
            $this->_objects[$object->getIdentifier()] = array();
        }
        return $this->_objects[$object->getIdentifier()][] = $object;
    }

    /**
     * Removes the object.
     *
     * @param Zend_Media_Asf_Object $object The object to remove
     */
    public final function removeObject($object)
    {
        if ($this->hasObject($object->getIdentifier())) {
            foreach ($this->_objects
                        [$object->getIdentifier()] as $key => $value) {
                if ($object === $value) {
                    unset($this->_objects[$object->getIdentifier()][$key]);
                }
            }
        }
    }

    /**
     * Returns the number of objects this container has.
     *
     * @return integer
     */
    public final function getObjectCount()
    {
        return count($this->_objects);
    }

    /**
     * Override magic function so that $obj->value will work as expected.
     *
     * The method first attempts to call the appropriate getter method. If no
     * field with given name is found, the method attempts to return the right
     * object instead. In other words, calling $obj->value will attempt to
     * return the first object returned by
     * $this->getObjectsByIdentifier(self::value). If no object is found by the
     * given value, a respective class name is tried to instantiate and add to
     * the container.
     *
     * @param string $name The field or object name.
     * @return mixed
     */
    public function __get($name)
    {
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func(array($this, 'get' . ucfirst($name)));
        }
        if (method_exists($this, 'is' . ucfirst($name))) {
            return call_user_func(array($this, 'is' . ucfirst($name)));
        }
        if (defined($constname = get_class($this) . '::' . strtoupper
                    (preg_replace('/[A-Z]/', '_$0', $name)))) {
            $objects = $this->getObjectsByIdentifier(constant($constname));
            if (isset($objects[0])) {
                return $objects[0];
            } else {
                if (@fopen($filename = 'Zend/Media/Asf/Object/' .
                           ucfirst($name) . '.php', 'r', true) !== false) {
                    require_once($filename);
                }
                if (class_exists
                    ($classname = 'Zend_Media_Asf_Object_' . ucfirst($name))) {
                    $obj = new $classname();
                    $obj->setIdentifier(constant($constname));
                    return $this->addObject($obj);
                }
            }
        }
        require_once 'Zend/Media/Asf/Exception.php';
        throw new Zend_Media_Asf_Exception('Unknown field/object: ' . $name);
    }

    /**
     * Override magic function so that $obj->value will work as expected.
     *
     * The method first attempts to call the appropriate setter method. If no
     * field with given name is found, the method attempts to set the right
     * object instead. In other words, assigning to $obj->value will attempt to
     * set the object with given value's identifier.
     *
     * Please note that using this method will override any prior objects having
     * the same object identifier.
     *
     * @param string $name  The field or object name.
     * @param string $value The field value or object.
     * @return mixed
     */
    public function __set($name, $value)
    {
        if (method_exists($this, 'set' . ucfirst($name))) {
            call_user_func(array($this, 'set' . ucfirst($name)), $value);
        }
        if (defined($constname = get_class($this) . '::' . strtoupper
                    (preg_replace('/[A-Z]/', '_$0', $name)))) {
            $value->setOptions($this->_options);
            $this->_objects[constant($constname)] = array($value);
        } else {
            require_once 'Zend/Media/Asf/Exception.php';
            throw new Zend_Media_Asf_Exception
                ('Unknown field/object: ' . $name);
        }
    }

    /**
     * Magic function so that isset($obj->value) will work. This method checks
     * whether the object by given identifier or name is contained by this
     * container.
     *
     * @param string $name The object identifier or logical name.
     * @return boolean
     */
    public function __isset($name) 
    {
        return $this->hasObject($name); 
    }

    /**
     * Magic function so that unset($obj->value) will work. This method removes
     * all the objects with the given identifier or name.
     *
     * @param string $name The object identifier or logical name.
     */
    public function __unset($name) 
    {
        $this->removeObjectsByName($name); 
    }
}
