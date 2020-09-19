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
 * @subpackage ASF
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Padding.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Padding Object</i> is a dummy object that is used to pad the size of
 * the <i>Header Object</i>. This object enables the size of any object stored
 * in the <i>Header Object</i> to grow or shrink without having to rewrite the
 * entire <i>Data Object</i> and <i>Index Object</i> sections of the ASF file.
 * For instance, if entries in the <i>Content Description Object</i> or
 * <i>Extended Content Description Object</i> need to be removed or shortened,
 * the size of the <i>Padding Object</i> can be increased to compensate for the
 * reduction in size of the <i>Content Description Object</i>. The ASF file can
 * then be updated by overwriting the previous <i>Header Object</i> with the
 * edited <i>Header Object</i> of identical size, without having to move or
 * rewrite the data contained in the <i>Data Object</i>.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Padding.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_Padding extends Zend_Media_Asf_Object
{
    /**
     * Constructs the class with given parameters and reads object related data
     * from the ASF file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        if ($this->getSize() == 0) {
            $this->setSize(24);
        }
        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->write(str_pad('', $this->getSize() - 24 /* header */, "\0"));
    }
}
