<?php
/**
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: Icop.php 257 2012-01-26 05:30:58Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Riff/StringChunk.php';
/**#@-*/

/**
 * The <i>Copyright</i> chunk records the copyright information for the file. If there are multiple copyrights, separate
 * them by a semicolon followed by a space.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: Icop.php 257 2012-01-26 05:30:58Z svollbehr $
 */
final class Zend_Media_Riff_Chunk_Icop extends Zend_Media_Riff_StringChunk
{
}
