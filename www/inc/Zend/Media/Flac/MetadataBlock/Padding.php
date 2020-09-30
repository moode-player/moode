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
 * @version    $Id: Padding.php 241 2011-06-11 16:46:52Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Flac/MetadataBlock.php';
/**#@-*/

/**
 * This class represents the padding metadata block. This block allows for an arbitrary amount of padding. The contents
 * of a PADDING block have no meaning. This block is useful when it is known that metadata will be edited after
 * encoding; the user can instruct the encoder to reserve a PADDING block of sufficient size so that when metadata is
 * added, it will simply overwrite the padding (which is relatively quick) instead of having to insert it into the right
 * place in the existing file (which would normally require rewriting the entire file).
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage FLAC
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Padding.php 241 2011-06-11 16:46:52Z svollbehr $
 */
final class Zend_Media_Flac_MetadataBlock_Padding extends Zend_Media_Flac_MetadataBlock
{
    /**
     * Constructs the class with given parameters and parses object related data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     */
    public function __construct($reader)
    {
        parent::__construct($reader);
    }
}
