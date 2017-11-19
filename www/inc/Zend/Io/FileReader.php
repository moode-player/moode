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
 * @package    Zend_Io
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: FileReader.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Io/Reader.php';
/**#@-*/

/**
 * The Zend_Io_FileReader represents a character stream whose source is
 * a file.
 *
 * @category   Zend
 * @package    Zend_Io
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: FileReader.php 177 2010-03-09 13:13:34Z svollbehr $
 */
class Zend_Io_FileReader extends Zend_Io_Reader
{
    /**
     * Constructs the Zend_Io_FileReader class with given path to the file. By
     * default the file is opened in read (rb) mode.
     *
     * @param string $filename The path to the file.
     * @throws Zend_Io_Exception if the file cannot be read
     */
    public function __construct($filename, $mode = null)
    {
        if ($mode === null)
            $mode = 'rb';
        if (!file_exists($filename) || !is_readable($filename) ||
            ($fd = fopen($filename, $mode)) === false) {
            require_once('Zend/Io/Exception.php');
            throw new Zend_Io_Exception
                ('Unable to open file for reading: ' . $filename);
        }
        parent::__construct($fd);
    }

    /**
     * Closes the file descriptor.
     */
    public function __destruct()
    {
        $this->close();
    }
}
