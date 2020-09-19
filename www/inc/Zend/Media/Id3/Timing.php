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
 * @version    $Id: Timing.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**
 * The <var>Zend_Media_Id3_Timing</var> interface implies that the implementing
 * ID3v2 frame contains one or more 32-bit timestamps.
 *
 * The timestamps are absolute times, meaning that every stamp contains the time
 * from the beginning of the file.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Timing.php 177 2010-03-09 13:13:34Z svollbehr $
 */
interface Zend_Media_Id3_Timing
{
    /** The timestamp is an absolute time, using MPEG frames as unit. */
    const MPEG_FRAMES   = 1;

    /** The timestamp is an absolute time, using milliseconds as unit. */
    const MILLISECONDS  = 2;

    /**
     * Returns the timing format.
     *
     * @return integer
     */
    public function getFormat();

    /**
     * Sets the timing format.
     *

     * @param integer $format The timing format.
     */
    public function setFormat($format);
}
