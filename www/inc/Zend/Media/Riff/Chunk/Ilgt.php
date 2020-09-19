<?php
/**
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: Ilgt.php 257 2012-01-26 05:30:58Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Riff/StringChunk.php';
/**#@-*/

/**
 * The <i>Lightness</i> chunk describes the changes in lightness settings on the digitizer required to produce the file.
 * Note that the format of this information depends on hardware used.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: Ilgt.php 257 2012-01-26 05:30:58Z svollbehr $
 */
final class Zend_Media_Riff_Chunk_Ilgt extends Zend_Media_Riff_StringChunk
{
}
