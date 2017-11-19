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
 * @version    $Id: Application.php 241 2011-06-11 16:46:52Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Flac/MetadataBlock.php';
/**#@-*/

/**
 * This class represents the application metadata block. This block is for use by third-party applications. The only
 * mandatory field is a 32-bit identifier. This ID is granted upon request to an application by the FLAC maintainers.
 * The remainder is of the block is defined by the registered application. Visit the registration page if you would like
 * to register an ID for your application with FLAC.
 *
 * Applications can be registered at {@link http://flac.sourceforge.net/id.html}.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage FLAC
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Application.php 241 2011-06-11 16:46:52Z svollbehr $
 */
final class Zend_Media_Flac_MetadataBlock_Application extends Zend_Media_Flac_MetadataBlock
{
    /**
     * Constructs the class with given parameters and parses object related data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     */
    public function __construct($reader)
    {
        parent::__construct($reader);

        $this->_identifier = $this->_reader->readUInt32BE();
        $this->_data = $this->_reader->read($this->getSize() - 4);
    }
    
    /**
     * Returns the application identifier.
     *
     * @return integer
     */
    public function getIdentifier()
    {
        return $this->_identifier;
    }
    
    /**
     * Returns the application data.
     *
     * @return string
     */
    public function getData()
    {
        return $this->_data;
    }
}
