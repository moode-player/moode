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
 * @version    $Id: Encoding.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**
 * The <var>Encoding</var> interface implies that the implementing ID3v2 frame
 * supports content encoding.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Encoding.php 177 2010-03-09 13:13:34Z svollbehr $
 */
interface Zend_Media_Id3_Encoding
{
    /** The ISO-8859-1 encoding. */
    const ISO88591 = 0;

    /** The UTF-16 Unicode encoding with BOM. */
    const UTF16    = 1;

    /** The UTF-16LE Unicode encoding without BOM. */
    const UTF16LE  = 4;

    /** The UTF-16BE Unicode encoding without BOM. */
    const UTF16BE  = 2;

    /** The UTF-8 Unicode encoding. */
    const UTF8     = 3;

    /**
     * Returns the text encoding.
     *
     * All the strings read from a file are automatically converted to the
     * character encoding specified with the <var>encoding</var> option. See
     * {@link Zend_Media_Id3v2} for details. This method returns that character
     * encoding, or any value set after read, translated into a string form
     * regarless if it was set using a {@link Zend_Media_Id3_Encoding} constant
     * or a string.
     *
     * @return integer
     */
    public function getEncoding();

    /**
     * Sets the text encoding.
     *
     * All the string written to the frame are done so using given character
     * encoding. No conversions of existing data take place upon the call to
     * this method thus all texts must be given in given character encoding.
     *
     * The character encoding parameter takes either a
     * {@link Zend_Media_Id3_Encoding} constant or a character set name string
     * in the form accepted by iconv. The default character encoding used to
     * write the frame is 'utf-8'.
     *
     * @see Zend_Media_Id3_Encoding
     * @param integer $encoding The text encoding.
     */
    public function setEncoding($encoding);
}
