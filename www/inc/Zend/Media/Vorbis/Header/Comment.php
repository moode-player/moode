<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled with this package in the file LICENSE.txt. It is
 * also available through the world-wide-web at this URL: http://framework.zend.com/license/new-bsd. If you did not
 * receive a copy of the license and are unable to obtain it through the world-wide-web, please send an email to
 * license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Vorbis
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Comment.php 251 2011-06-13 15:41:51Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Vorbis/Header.php';
/**#@-*/

/**
 * The Vorbis text comment header is the second (of three) header packets that begin a Vorbis bitstream. It is meant
 * for short text comments, not arbitrary metadata; arbitrary metadata belongs in a separate logical bitstream (usually
 * an XML stream type) that provides greater structure and machine parseability.
 *
 * The comment field is meant to be used much like someone jotting a quick note on the bottom of a CDR. It should be a
 * little information to remember the disc by and explain it to others; a short, to-the-point text note that need not
 * only be a couple words, but isn't going to be more than a short paragraph. The essentials, in other words, whatever
 * they turn out to be, eg:
 *
 *   Honest Bob and the Factory-to-Dealer-Incentives, \I'm Still Around", opening for Moxy Fruvous, 1997.
 *
 * The following web pages will guide you with applicaple field names and values:
 *
 * o Recommended set of 15 field names
 *   http://xiph.org/vorbis/doc/v-comment.html
 *
 * o Proposed update to the minimal list of 15 standard field names
 *   http://wiki.xiph.org/Field_names
 *
 * o Other proposals for additional field names
 *   http://age.hobba.nl/audio/mirroredpages/ogg-tagging.html
 *   http://reallylongword.org/vorbiscomment/
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Vorbis
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Comment.php 251 2011-06-13 15:41:51Z svollbehr $
 */
final class Zend_Media_Vorbis_Header_Comment extends Zend_Media_Vorbis_Header
{
    /** @var string */
    private $_vendor;

    /** @var Array */
    private $_comments;

    /** @var integer */
    private $_framingFlag = 1;

    /**
     * Constructs the class with given parameters and reads object related data from the bitstream.
     *
     * The following options are currently recognized:
     *  o vorbisContext -- Indicates whether to expect comments to be in the context of a vorbis bitstream or not. This
     *    option can be used to parse vorbis comments in another formats, eg FLAC, that do not use for example the
     *    framing flags. Defaults to true.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array          $options Array of options.
     */
    public function __construct($reader, $options = array())
    {
        if (!isset($options['vorbisContext']) || $options['vorbisContext']) {
            parent::__construct($reader);
        } else {
            $this->_reader = $reader;
        }
        $this->_vendor = $this->_reader->read($this->_reader->readUInt32LE());
        $userCommentListLength = $this->_reader->readUInt32LE();
        for ($i = 0; $i < $userCommentListLength; $i++) {
            list ($name, $value) = preg_split('/=/', $this->_reader->read($this->_reader->readUInt32LE()), 2);
            if (!isset($this->_comments[strtoupper($name)])) {
                $this->_comments[strtoupper($name)] = array();
            }
            $this->_comments[strtoupper($name)][] = $value;
        }
        if (!isset($options['vorbisContext']) || $options['vorbisContext']) {
            $this->_framingFlag = $this->_reader->readUInt8() & 0x1;
            if ($this->_framingFlag == 0) {
                require_once 'Zend/Media/Vorbis/Exception.php';
                throw new Zend_Media_Vorbis_Exception('Undecodable Vorbis stream');
            }
            $this->_reader->skip($this->_packetSize - $this->_reader->getOffset() + 30 /* header */);
        }
    }

    /**
     * Returns the vendor string.
     *
     * @return string
     */
    public function getVendor()
    {
        return $this->_vendor;
    }

    /**
     * Returns an array of comments having the field names as keys and an array of values as a value.
     *
     * @return Array
     */
    public function getComments()
    {
        return $this->_comments;
    }

    /**
     * Returns an array of comments having the field names as keys and an array of values as a value. The array is
     * restricted to field names that matches the given criteria. Unlike the getX() methods, which return the first
     * value, this method returns an array of field values.
     *
     * @return Array
     */
    public function getCommentsByName($name)
    {
        if (!empty($this->_comments[strtoupper($name)])) {
           return $this->_comments[strtoupper($name)];
        }
        return array();
    }

    /**
     * Magic function so that $obj->X() or $obj->getX() will work, where X is the name of the comment field. The method
     * will attempt to return the first field by the given name from the comment. If there is no field with given name,
     * an exception is thrown.
     *
     * @param string $name The field name.
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (preg_match('/^(?:get)([A-Z].*)$/', $name, $matches)) {
            $name = $matches[1];
        }
        if (!empty($this->_comments[strtoupper($name)])) {
           return $this->_comments[strtoupper($name)][0];
        }
        require_once 'Zend/Media/Vorbis/Exception.php';
        throw new Zend_Media_Vorbis_Exception('Unknown field: ' . strtoupper($name));
    }

    /**
     * Magic function so that $obj->value will work. The method will attempt to return the first field by the given
     * name from the comment. If there is no field with given name, functionality of the parent method is executed.
     *
     * @param string $name The field name.
     * @return mixed
     */
    public function __get($name)
    {
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func(array($this, 'get' . ucfirst($name)));
        }
        if (!empty($this->_comments[strtoupper($name)])) {
            return $this->_comments[strtoupper($name)][0];
        }
        parent::__get($name);
    }

    /**
     * Magic function so that isset($obj->value) will work. This method checks whether the comment contains a field by
     * the given name.
     *
     * @param string $name The field name.
     * @return boolean
     */
    public function __isset($name)
    {
        return count($this->_comments[strtoupper($name)]) > 0;
    }

    /**
     * Magic function so that unset($obj->value) will work. This method removes all the comments matching the field
     * name.
     *
     * @param string $name The field name.
     */
    public function __unset($name)
    {
        unset($this->_comments[strtoupper($name)]);
    }
}
