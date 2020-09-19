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
 * @package    Zend_Io
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Reader.php 214 2011-04-30 08:19:28Z svollbehr $
 */

/**
 * The Zend_Io_Reader class represents a character stream providing means to
 * read primitive types (string, integers, ...) from it.
 *
 * @category   Zend
 * @package    Zend_Io
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @author     Marc Bennewitz <marc-bennewitz@arcor.de>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Reader.php 214 2011-04-30 08:19:28Z svollbehr $
 */
class Zend_Io_Reader
{
    const MACHINE_ENDIAN_ORDER = 0;
    const LITTLE_ENDIAN_ORDER  = 1;
    const BIG_ENDIAN_ORDER     = 2;

    /**
     * The endianess of the current machine.
     *
     * @var integer
     */
    private static $_endianess = 0;

    /**
     * The resource identifier of the stream.
     *
     * @var resource
     */
    protected $_fd = null;

    /**
     * Size of the underlying stream.
     *
     * @var integer
     */
    protected $_size = 0;

    /**
     * Constructs the Zend_Io_Reader class with given open file descriptor.
     *
     * @param resource $fd The file descriptor.
     * @throws Zend_Io_Exception if given file descriptor is not valid
     */
    public function __construct($fd)
    {
        if (!is_resource($fd) ||
            !in_array(get_resource_type($fd), array('stream'))) {
            require_once('Zend/Io/Exception.php');
            throw new Zend_Io_Exception
                ('Invalid resource type (only resources of type stream are supported)');
        }

        $this->_fd = $fd;

        $offset = $this->getOffset();
        fseek($this->_fd, 0, SEEK_END);
        $this->_size = ftell($this->_fd);
        fseek($this->_fd, $offset);
    }

    /**
     * Default destructor.
     */
    public function __destruct() {}

    /**
     * Checks whether there is more to be read from the stream. Returns
     * <var>true</var> if the end has not yet been reached; <var>false</var>
     * otherwise.
     *
     * @return boolean
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public function available()
    {
        return $this->getOffset() < $this->getSize();
    }

    /**
     * Returns the current point of operation.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public function getOffset()
    {
        if ($this->_fd === null) {
            require_once('Zend/Io/Exception.php');
            throw new Zend_Io_Exception('Cannot operate on a closed stream');
        }
        return ftell($this->_fd);
    }

    /**
     * Sets the point of operation, ie the cursor offset value. The offset may
     * also be set to a negative value when it is interpreted as an offset from
     * the end of the stream instead of the beginning.
     *
     * @param integer $offset The new point of operation.
     * @return void
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public function setOffset($offset)
    {
        if ($this->_fd === null) {
            require_once('Zend/Io/Exception.php');
            throw new Zend_Io_Exception('Cannot operate on a closed stream');
        }
        fseek($this->_fd, $offset < 0 ? $this->getSize() + $offset : $offset);
    }

    /**
     * Returns the stream size in bytes.
     *
     * @return integer
     */
    public function getSize()
    {
        return $this->_size;
    }

    /**
     * Returns the underlying stream file descriptor.
     *
     * @return resource
     */
    public function getFileDescriptor()
    {
        return $this->_fd;
    }

    /**
     * Jumps <var>size</var> amount of bytes in the stream.
     *
     * @param integer $size The amount of bytes.
     * @return void
     * @throws Zend_Io_Exception if <var>size</var> attribute is negative or if
     *  an I/O error occurs
     */
    public function skip($size)
    {
        if ($size < 0) {
            require_once('Zend/Io/Exception.php');
            throw new Zend_Io_Exception('Size cannot be negative');
        }
        if ($size == 0) {
            return;
        }
        if ($this->_fd === null) {
            require_once('Zend/Io/Exception.php');
            throw new Zend_Io_Exception('Cannot operate on a closed stream');
        }
        fseek($this->_fd, $size, SEEK_CUR);
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream.
     *
     * @param integer $length The amount of bytes.
     * @return string
     * @throws Zend_Io_Exception if <var>length</var> attribute is negative or
     *  if an I/O error occurs
     */
    public function read($length)
    {
        if ($length < 0) {
            require_once('Zend/Io/Exception.php');
            throw new Zend_Io_Exception('Length cannot be negative');
        }
        if ($length == 0) {
            return '';
        }
        if ($this->_fd === null) {
            require_once('Zend/Io/Exception.php');
            throw new Zend_Io_Exception('Cannot operate on a closed stream');
        }
        return fread($this->_fd, $length);
    }

    /**
     * Reads 1 byte from the stream and returns binary data as an 8-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readInt8()
    {
        $ord = ord($this->read(1));
        if ($ord > 127) {
            return -$ord - 2 * (128 - $ord);
        } else {
            return $ord;
        }
    }

    /**
     * Reads 1 byte from the stream and returns binary data as an unsigned 8-bit
     * integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readUInt8()
    {
        return ord($this->read(1));
    }

    /**
     * Returns machine endian ordered binary data as signed 16-bit integer.
     *
     * @param string $value The binary data string.
     * @return integer
     */
    private function _fromInt16($value)
    {
        list(, $int) = unpack('s*', $value);
        return $int;
    }

    /**
     * Reads 2 bytes from the stream and returns little-endian ordered binary
     * data as signed 16-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readInt16LE()
    {
        if ($this->_isBigEndian()) {
            return $this->_fromInt16(strrev($this->read(2)));
        } else {
            return $this->_fromInt16($this->read(2));
        }
    }

    /**
     * Reads 2 bytes from the stream and returns big-endian ordered binary data
     * as signed 16-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readInt16BE()
    {
        if ($this->_isLittleEndian()) {
            return $this->_fromInt16(strrev($this->read(2)));
        } else {
            return $this->_fromInt16($this->read(2));
        }
    }

    /**
     * Reads 2 bytes from the stream and returns machine ordered binary data
     * as signed 16-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readInt16()
    {
        return $this->_fromInt16($this->read(2));
    }

    /**
     * Returns machine endian ordered binary data as unsigned 16-bit integer.
     *
     * @param string  $value The binary data string.
     * @param integer $order The byte order of the binary data string.
     * @return integer
     */
    private function _fromUInt16($value, $order = 0)
    {
        list(, $int) = unpack
            (($order == self::BIG_ENDIAN_ORDER ? 'n' :
                ($order == self::LITTLE_ENDIAN_ORDER ? 'v' : 'S')) . '*',
             $value);
        return $int;
    }

    /**
     * Reads 2 bytes from the stream and returns little-endian ordered binary
     * data as unsigned 16-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readUInt16LE()
    {
        return $this->_fromUInt16($this->read(2), self::LITTLE_ENDIAN_ORDER);
    }

    /**
     * Reads 2 bytes from the stream and returns big-endian ordered binary data
     * as unsigned 16-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readUInt16BE()
    {
        return $this->_fromUInt16($this->read(2), self::BIG_ENDIAN_ORDER);
    }

    /**
     * Reads 2 bytes from the stream and returns machine ordered binary data
     * as unsigned 16-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readUInt16()
    {
        return $this->_fromUInt16($this->read(2), self::MACHINE_ENDIAN_ORDER);
    }

    /**
     * Returns machine endian ordered binary data as signed 24-bit integer.
     *
     * @param string $value The binary data string.
     * @return integer
     */
    private function _fromInt24($value)
    {
        list(, $int) = unpack('l*', $this->_isLittleEndian() ? ("\x00" . $value) : ($value . "\x00"));
        return $int;
    }

    /**
     * Reads 3 bytes from the stream and returns little-endian ordered binary
     * data as signed 24-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readInt24LE()
    {
        if ($this->_isBigEndian()) {
            return $this->_fromInt24(strrev($this->read(3)));
        } else {
            return $this->_fromInt24($this->read(3));
        }
    }

    /**
     * Reads 3 bytes from the stream and returns big-endian ordered binary data
     * as signed 24-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readInt24BE()
    {
        if ($this->_isLittleEndian()) {
            return $this->_fromInt24(strrev($this->read(3)));
        } else {
            return $this->_fromInt24($this->read(3));
        }
    }

    /**
     * Reads 3 bytes from the stream and returns machine ordered binary data
     * as signed 24-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readInt24()
    {
        return $this->_fromInt24($this->read(3));
    }

    /**
     * Returns machine endian ordered binary data as unsigned 24-bit integer.
     *
     * @param string  $value The binary data string.
     * @param integer $order The byte order of the binary data string.
     * @return integer
     */
    private function _fromUInt24($value, $order = 0)
    {
        list(, $int) = unpack
            (($order == self::BIG_ENDIAN_ORDER ? 'N' :
                ($order == self::LITTLE_ENDIAN_ORDER ? 'V' : 'L')) . '*',
             $this->_isLittleEndian() ? ("\x00" . $value) : ($value . "\x00"));
        return $int;
    }

    /**
     * Reads 3 bytes from the stream and returns little-endian ordered binary
     * data as unsigned 24-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readUInt24LE()
    {
        return $this->_fromUInt24($this->read(3), self::LITTLE_ENDIAN_ORDER);
    }

    /**
     * Reads 3 bytes from the stream and returns big-endian ordered binary data
     * as unsigned 24-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readUInt24BE()
    {
        return $this->_fromUInt24($this->read(3), self::BIG_ENDIAN_ORDER);
    }

    /**
     * Reads 3 bytes from the stream and returns machine ordered binary data
     * as unsigned 24-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readUInt24()
    {
        return $this->_fromUInt24($this->read(3), self::MACHINE_ENDIAN_ORDER);
    }

    /**
     * Returns machine-endian ordered binary data as signed 32-bit integer.
     *
     * @param string $value The binary data string.
     * @return integer
     */
    private final function _fromInt32($value)
    {
        list(, $int) = unpack('l*', $value);
        return $int;
    }

    /**
     * Reads 4 bytes from the stream and returns little-endian ordered binary
     * data as signed 32-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readInt32LE()
    {
        if ($this->_isBigEndian())
            return $this->_fromInt32(strrev($this->read(4)));
        else
            return $this->_fromInt32($this->read(4));
    }

    /**
     * Reads 4 bytes from the stream and returns big-endian ordered binary data
     * as signed 32-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readInt32BE()
    {
        if ($this->_isLittleEndian())
            return $this->_fromInt32(strrev($this->read(4)));
        else
            return $this->_fromInt32($this->read(4));
    }

    /**
     * Reads 4 bytes from the stream and returns machine ordered binary data
     * as signed 32-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readInt32()
    {
        return $this->_fromInt32($this->read(4));
    }

    /**
     * Reads 4 bytes from the stream and returns little-endian ordered binary
     * data as unsigned 32-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readUInt32LE()
    {
        if (PHP_INT_SIZE < 8) {
            list(, $lo, $hi) = unpack('v*', $this->read(4));
            return $hi * (0xffff+1) + $lo; // eq $hi << 16 | $lo
        } else {
            list(, $int) = unpack('V*', $this->read(4));
            return $int;
        }
    }

    /**
     * Reads 4 bytes from the stream and returns big-endian ordered binary data
     * as unsigned 32-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readUInt32BE()
    {
        if (PHP_INT_SIZE < 8) {
            list(, $hi, $lo) = unpack('n*', $this->read(4));
            return $hi * (0xffff+1) + $lo; // eq $hi << 16 | $lo
        } else {
            list(, $int) = unpack('N*', $this->read(4));
            return $int;
        }
    }

    /**
     * Reads 4 bytes from the stream and returns machine ordered binary data
     * as unsigned 32-bit integer.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readUInt32()
    {
        if (PHP_INT_SIZE < 8) {
            list(, $hi, $lo) = unpack('L*', $this->read(4));
            return $hi * (0xffff+1) + $lo; // eq $hi << 16 | $lo
        } else {
            list(, $int) = unpack('L*', $this->read(4));
            return $int;
        }
    }

    /**
     * Reads 8 bytes from the stream and returns little-endian ordered binary
     * data as 64-bit float.
     *
     * {@internal PHP does not support 64-bit integers as the long
     * integer is of 32-bits but using aritmetic operations it is implicitly
     * converted into floating point which is of 64-bits long.}}
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readInt64LE()
    {
        list(, $lolo, $lohi, $hilo, $hihi) = unpack('v*', $this->read(8));
        return ($hihi * (0xffff+1) + $hilo) * (0xffffffff+1) +
            ($lohi * (0xffff+1) + $lolo);
    }

    /**
     * Reads 8 bytes from the stream and returns big-endian ordered binary data
     * as 64-bit float.
     *
     * {@internal PHP does not support 64-bit integers as the long integer is of
     * 32-bits but using aritmetic operations it is implicitly converted into
     * floating point which is of 64-bits long.}}
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readInt64BE()
    {
        list(, $hihi, $hilo, $lohi, $lolo) = unpack('n*', $this->read(8));
        return ($hihi * (0xffff+1) + $hilo) * (0xffffffff+1) +
            ($lohi * (0xffff+1) + $lolo);
    }

    /**
     * Returns machine endian ordered binary data as a 32-bit floating point
     * number as defined by IEEE 754.
     *
     * @param string $value The binary data string.
     * @return float
     */
    private function _fromFloat($value)
    {
        list(, $float) = unpack('f', $value);
        return $float;
    }

    /**
     * Reads 4 bytes from the stream and returns little-endian ordered binary
     * data as a 32-bit float point number as defined by IEEE 754.
     *
     * @return float
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readFloatLE()
    {
        if ($this->_isBigEndian()) {
            return $this->_fromFloat(strrev($this->read(4)));
        } else {
            return $this->_fromFloat($this->read(4));
        }
    }

    /**
     * Reads 4 bytes from the stream and returns big-endian ordered binary data
     * as a 32-bit float point number as defined by IEEE 754.
     *
     * @return float
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readFloatBE()
    {
        if ($this->_isLittleEndian()) {
            return $this->_fromFloat(strrev($this->read(4)));
        } else {
            return $this->_fromFloat($this->read(4));
        }
    }

    /**
     * Returns machine endian ordered binary data as a 64-bit floating point
     * number as defined by IEEE754.
     *
     * @param string $value The binary data string.
     * @return float
     */
    private function _fromDouble($value)
    {
        list(, $double) = unpack('d', $value);
        return $double;
    }

    /**
     * Reads 8 bytes from the stream and returns little-endian ordered binary
     * data as a 64-bit floating point number as defined by IEEE 754.
     *
     * @return float
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readDoubleLE()
    {
        if ($this->_isBigEndian()) {
            return $this->_fromDouble(strrev($this->read(8)));
        } else {
            return $this->_fromDouble($this->read(8));
        }
    }

    /**
     * Reads 8 bytes from the stream and returns big-endian ordered binary data
     * as a 64-bit float point number as defined by IEEE 754.
     *
     * @return float
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readDoubleBE()
    {
        if ($this->_isLittleEndian()) {
            return $this->_fromDouble(strrev($this->read(8)));
        } else {
            return $this->_fromDouble($this->read(8));
        }
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream and returns
     * binary data as string. Removes terminating zero.
     *
     * @param integer $length   The amount of bytes.
     * @param string  $charList The list of characters you want to strip.
     * @return string
     * @throws Zend_Io_Exception if <var>length</var> attribute is negative or
     *  if an I/O error occurs
     */
    public final function readString8($length, $charList = "\0")
    {
        return rtrim($this->read($length), $charList);
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream and returns
     * binary data as multibyte Unicode string. Removes terminating zero.
     *
     * The byte order is possibly determined from the byte order mark included
     * in the binary data string. The order parameter is updated if the BOM is
     * found.
     *
     * @param integer $length    The amount of bytes.
     * @param integer $order     The endianess of the string.
     * @param integer $trimOrder Whether to remove the byte order mark read the
     *                string.
     * @return string
     * @throws Zend_Io_Exception if <var>length</var> attribute is negative or
     *  if an I/O error occurs
     */
    public final function readString16
        ($length, &$order = null, $trimOrder = false)
    {
        $value = $this->read($length);

        if (strlen($value) < 2) {
            return '';
        }

        if (ord($value[0]) == 0xfe && ord($value[1]) == 0xff) {
            $order = self::BIG_ENDIAN_ORDER;
            if ($trimOrder) {
                $value = substr($value, 2);
            }
        }
        if (ord($value[0]) == 0xff && ord($value[1]) == 0xfe) {
            $order = self::LITTLE_ENDIAN_ORDER;
            if ($trimOrder) {
                $value = substr($value, 2);
            }
        }

        while (substr($value, -2) == "\0\0") {
            $value = substr($value, 0, -2);
        }

        return $value;
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream and returns
     * binary data as hexadecimal string having high nibble first.
     *
     * @param integer $length The amount of bytes.
     * @return string
     * @throws Zend_Io_Exception if <var>length</var> attribute is negative or
     *  if an I/O error occurs
     */
    public final function readHHex($length)
    {
        list($hex) = unpack('H*0', $this->read($length));
        return $hex;
    }

    /**
     * Reads <var>length</var> amount of bytes from the stream and returns
     * binary data as hexadecimal string having low nibble first.
     *
     * @param integer $length The amount of bytes.
     * @return string
     * @throws Zend_Io_Exception if <var>length</var> attribute is negative or
     *  if an I/O error occurs
     */
    public final function readLHex($length)
    {
        list($hex) = unpack('h*0', $this->read($length));
        return $hex;
    }

    /**
     * Reads 16 bytes from the stream and returns the little-endian ordered
     * binary data as mixed-ordered hexadecimal GUID string.
     *
     * @return string
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public final function readGuid()
    {
        $C = @unpack('V1V/v2v/N2N', $this->read(16));
        list($hex) = @unpack('H*0', pack
            ('NnnNN', $C['V'], $C['v1'], $C['v2'], $C['N1'], $C['N2']));

        /* Fixes a bug in PHP versions earlier than Jan 25 2006 */
        if (implode('', unpack('H*', pack('H*', 'a'))) == 'a00') {
            $hex = substr($hex, 0, -1);
        }

        return preg_replace
            ('/^(.{8})(.{4})(.{4})(.{4})/', "\\1-\\2-\\3-\\4-", $hex);
    }

    /**
     * Resets the stream. Attempts to reset it in some way appropriate to the
     * particular stream, for example by repositioning it to its starting point.
     *
     * @return void
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public function reset()
    {
        if ($this->_fd === null) {
            require_once('Zend/Io/Exception.php');
            throw new Zend_Io_Exception('Cannot operate on a closed stream');
        }
        fseek($this->_fd, 0);
    }

    /**
     * Closes the stream. Once a stream has been closed, further calls to read
     * methods will throw an exception. Closing a previously-closed stream,
     * however, has no effect.
     *
     * @return void
     */
    public function close()
    {
        if ($this->_fd !== null) {
            @fclose($this->_fd);
            $this->_fd = null;
        }
    }

    /**
     * Returns the current machine endian order.
     *
     * @return integer
     */
    private function _getEndianess()
    {
        if (self::$_endianess === 0) {
            self::$_endianess = $this->_fromInt32("\x01\x00\x00\x00") == 1 ?
                self::LITTLE_ENDIAN_ORDER : self::BIG_ENDIAN_ORDER;
        }
        return self::$_endianess;
    }

    /**
     * Returns whether the current machine endian order is little endian.
     *
     * @return boolean
     */
    private function _isLittleEndian()
    {
        return $this->_getEndianess() == self::LITTLE_ENDIAN_ORDER;
    }

    /**
     * Returns whether the current machine endian order is big endian.
     *
     * @return boolean
     */
    private function _isBigEndian()
    {
        return $this->_getEndianess() == self::BIG_ENDIAN_ORDER;
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
            return call_user_func
                (array($this, 'get' . ucfirst(strtolower($name))));
        } else {
            require_once('Zend/Io/Exception.php');
            throw new Zend_Io_Exception('Unknown field: ' . $name);
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
            call_user_func
                (array($this, 'set' . ucfirst(strtolower($name))), $value);
        } else {
            require_once('Zend/Io/Exception.php');
            throw new Zend_Io_Exception('Unknown field: ' . $name);
        }
    }
}
