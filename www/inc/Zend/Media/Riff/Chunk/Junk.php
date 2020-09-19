<?php
/**
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: Junk.php 257 2012-01-26 05:30:58Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Riff/StringChunk.php';
/**#@-*/

/**
 * The <i>Filler</i> chunk represents padding, filler or outdated information. It contains no relevant data; it is a
 * space filler of arbitrary size.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: Junk.php 257 2012-01-26 05:30:58Z svollbehr $
 */
final class Zend_Media_Riff_Chunk_Junk extends Zend_Media_Riff_Chunk
{
}
