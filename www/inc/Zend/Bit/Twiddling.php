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
 * @package    Zend_Bit
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Twiddling.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**
 * A utility class to perform bit twiddling on integers.
 *
 * @category   Zend
 * @package    Zend_Bit
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Twiddling.php 177 2010-03-09 13:13:34Z svollbehr $
 * @static
 */
final class Zend_Bit_Twiddling
{
    /**
     * Default private constructor for a static class.
     */
    private function __construct() 
    {
    }
        
    /**
     * Sets a bit at a given position in an integer.
     *
     * @param integer $integer  The value to manipulate.
     * @param integer $position The position of the bit to set.
     * @param boolean $on       Whether to enable or clear the bit.
     * @return integer
     */
    public static function setBit($integer, $position, $on)
    {
        return $on ? self::enableBit($integer, $position) :
            self::clearBit($integer, $position);
    }

    /**
     * Enables a bit at a given position in an integer.
     *
     * @param integer $integer  The value to manipulate.
     * @param integer $position The position of the bit to enable.
     * @return integer
     */
    public static function enableBit($integer, $position)
    {
        return $integer | (1 << $position);
    }

    /**
     * Clears a bit at a given position in an integer.
     *
     * @param integer $integer  The value to manipulate.
     * @param integer $position The position of the bit to clear.
     * @return integer
     */
    public static function clearBit($integer, $position)
    {
        return $integer & ~(1 << $position);
    }

    /**
     * Toggles a bit at a given position in an integer.
     *
     * @param integer $integer  The value to manipulate.
     * @param integer $position The position of the bit to toggle.
     * @return integer
     */
    public static function toggleBit($integer, $position)
    {
        return $integer ^ (1 << $position);
    }

    /**
     * Tests a bit at a given position in an integer.
     *
     * @param integer $integer  The value to test.
     * @param integer $position The position of the bit to test.
     * @return boolean
     */
    public static function testBit($integer, $position)
    {
        return ($integer & (1 << $position)) != 0;
    }

    /**
     * Sets a given set of bits in an integer.
     *
     * @param integer $integer The value to manipulate.
     * @param integer $bits    The bits to set.
     * @param boolean $on      Whether to enable or clear the bits.
     * @return integer
     */
    public static function setBits($integer, $bits, $on)
    {
        return $on ? self::enableBits($integer, $bits) :
            self::clearBits($integer, $bits);
    }

    /**
     * Enables a given set of bits in an integer.
     *
     * @param integer $integer The value to manipulate.
     * @param integer $bits    The bits to enable.
     * @return integer
     */
    public static function enableBits($integer, $bits)
    {
        return $integer | $bits;
    }

    /**
     * Clears a given set of bits in an integer.
     *
     * @param integer $integer The value to manipulate.
     * @param integer $bits    The bits to clear.
     * @return integer
     */
    public static function clearBits($integer, $bits)
    {
        return $integer & ~$bits;
    }

    /**
     * Toggles a given set of bits in an integer.
     *
     * @param integer $integer The value to manipulate.
     * @param integer $bits    The bits to toggle.
     * @return integer
     */
    public static function toggleBits($integer, $bits)
    {
        return $integer ^ $bits;
    }

    /**
     * Tests a given set of bits in an integer
     * returning whether all bits are set.
     *
     * @param integer $integer The value to test.
     * @param integer $bits    The bits to test.
     * @return boolean
     */
    public static function testAllBits($integer, $bits)
    {
        return ($integer & $bits) == $bits;
    }

    /**
     * Tests a given set of bits in an integer
     * returning whether any bits are set.
     *
     * @param integer $integer The value to test.
     * @param integer $bits    The bits to test.
     * @return boolean
     */
    public static function testAnyBits($integer, $bits)
    {
        return ($integer & $bits) != 0;
    }

    /**
     * Stores a value in a given range in an integer.
     *
     * @param integer $integer The value to store into.
     * @param integer $start   The position to store from. Must be <= $end.
     * @param integer $end     The position to store to. Must be >= $start.
     * @param integer $value   The value to store.
     * @return integer
     */
    public static function setValue($integer, $start, $end, $value)
    {
        return self::clearBits
            ($integer, self::getMask
             ($start, $end) << $start) | ($value << $start);
    }

    /**
     * Retrieves a value from a given range in an integer, inclusive.
     *
     * @param integer $integer The value to read from.
     * @param integer $start   The position to read from. Must be <= $end.
     * @param integer $end     The position to read to. Must be >= $start.
     * @return integer
     */
    public static function getValue($integer, $start, $end)
    {
        return ($integer & self::getMask($start, $end)) >> $start;
    }

    /**
     * Returns an integer with all bits set from start to end.
     *
     * @param integer $start The position to start setting bits from. Must
     *                       be <= $end.
     * @param integer $end   The position to stop setting bits. Must
     *                       be >= $start.
     * @return integer
     */
    public static function getMask($start, $end)
    {
        return ($tmp = (1 << $end)) + $tmp - (1 << $start);
    }
}
