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
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Tso2.php 204 2010-10-14 05:58:51Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/TextFrame.php';
/**#@-*/

/**
 * This non-standard frame is used by iTunes in ID3v2.3.0 for sorting the names of the
 * Album Artist(s) of a track, that specified in the "TPE1" frame.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Darren Burnhill <darrenburnhill@gmail.com>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Tso2.php 204 2010-10-14 05:58:51Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Tso2 extends Zend_Media_Id3_TextFrame
{}
