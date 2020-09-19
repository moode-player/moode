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
 * @subpackage FLAC
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: VorbisComment.php 251 2011-06-13 15:41:51Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Flac/MetadataBlock.php';
require_once 'Zend/Media/Vorbis/Header/Comment.php';
/**#@-*/

/**
 * This class represents the vorbis comments metadata block. This block is for storing a list of human-readable
 * name/value pairs. This is the only officially supported tagging mechanism in FLAC. There may be only one
 * VORBIS_COMMENT block in a stream. In some external documentation, Vorbis comments are called FLAC tags to lessen
 * confusion.
 *
 * This class parses the vorbis comments using the {@link Zend_Media_Vorbis_Header_Comment} class. Any of its method
 * or fields can be used in the context of this class.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage FLAC
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: VorbisComment.php 251 2011-06-13 15:41:51Z svollbehr $
 */
final class Zend_Media_Flac_MetadataBlock_VorbisComment extends Zend_Media_Flac_MetadataBlock
{
    /** @var Zend_Media_Vorbis_Header_Comment */
    private $_impl;

    /**
     * Constructs the class with given parameters and parses object related data using the vorbis comment implementation
     * class {@link Zend_Media_Vorbis_Header_Comment}.
     *
     * @param Zend_Io_Reader $reader The reader object.
     */
    public function __construct($reader)
    {
        parent::__construct($reader);
        $this->_impl = new Zend_Media_Vorbis_Header_Comment($this->_reader, array('vorbisContext' => false));
    }

    /**
     * Forward all calls to the vorbis comment implementation class {@link Zend_Media_Vorbis_Header_Comment}.
     *
     * @param string $name The method name.
     * @param Array $arguments The method arguments.
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this, $name)) {
            return call_user_func(array($this, $name), $arguments);
        }
        try {
            return $this->_impl->$name($arguments);
        } catch (Zend_Media_Vorbis_Exception $e) {
            require_once 'Zend/Media/Flac/Exception.php';
            throw new Zend_Media_Flac_Exception($e->getMessage());
        }
    }

    /**
     * Forward all calls to the vorbis comment implementation class {@link Zend_Media_Vorbis_Header_Comment}.
     *
     * @param string $name The field name.
     * @return mixed
     */
    public function __get($name)
    {
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func(array($this, 'get' . ucfirst($name)));
        }
        if (method_exists($this->_impl, 'get' . ucfirst($name))) {
            return call_user_func(array($this->_impl, 'get' . ucfirst($name)));
        }
        try {
            return $this->_impl->__get($name);
        } catch (Zend_Media_Vorbis_Exception $e) {
            require_once 'Zend/Media/Flac/Exception.php';
            throw new Zend_Media_Flac_Exception($e->getMessage());
        }
    }

    /**
     * Forward all calls to the vorbis comment implementation class {@link Zend_Media_Vorbis_Header_Comment}.
     *
     * @param string $name The field name.
     * @param string $name The field value.
     * @return mixed
     */
    public function __set($name, $value)
    {
        if (method_exists($this, 'set' . ucfirst($name))) {
            call_user_func(array($this, 'set' . ucfirst($name)), $value);
        } else {
            try {
                return $this->_impl->__set($name, $value);
            } catch (Zend_Media_Vorbis_Exception $e) {
                require_once 'Zend/Media/Flac/Exception.php';
                throw new Zend_Media_Flac_Exception($e->getMessage());
            }
        }
    }

    /**
     * Forward all calls to the vorbis comment implementation class {@link Zend_Media_Vorbis_Header_Comment}.
     *
     * @param string $name The field name.
     * @return boolean
     */
    public function __isset($name)
    {
        try {
            return $this->_impl->__isset($name);
        } catch (Zend_Media_Vorbis_Exception $e) {
            require_once 'Zend/Media/Flac/Exception.php';
            throw new Zend_Media_Flac_Exception($e->getMessage());
        }
    }

    /**
     * Forward all calls to the vorbis comment implementation class {@link Zend_Media_Vorbis_Header_Comment}.
     *
     * @param string $name The field name.
     */
    public function __unset($name)
    {
        try {
            $this->_impl->__unset($name);
        } catch (Zend_Media_Vorbis_Exception $e) {
            require_once 'Zend/Media/Flac/Exception.php';
            throw new Zend_Media_Flac_Exception($e->getMessage());
        }
    }
}
