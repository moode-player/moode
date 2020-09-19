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
 * @version    $Id: AdvancedMutualExclusion.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Advanced Mutual Exclusion Object</i> identifies streams that have a
 * mutual exclusion relationship to each other (in other words, only one of the
 * streams within such a relationship can be streamed—the rest are ignored).
 * There should be one instance of this object for each set of objects that
 * contain a mutual exclusion relationship. The exclusion type is used so that
 * implementations can allow user selection of common choices, such as language.
 * This object must be used if any of the streams in the mutual exclusion
 * relationship are hidden.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: AdvancedMutualExclusion.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_AdvancedMutualExclusion
    extends Zend_Media_Asf_Object
{
    const MUTEX_LANGUAGE = 'd6e22a00-35da-11d1-9034-00a0c90349be';
    const MUTEX_BITRATE = 'd6e22a01-35da-11d1-9034-00a0c90349be';
    const MUTEX_UNKNOWN = 'd6e22a02-35da-11d1-9034-00a0c90349be';

    /** @var string */
    private $_exclusionType;

    /** @var Array */
    private $_streamNumbers = array();

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

        if ($reader === null) {
            return;
        }

        $this->_exclusionType = $this->_reader->readGuid();
        $streamNumbersCount = $this->_reader->readUInt16LE();
        for ($i = 0; $i < $streamNumbersCount; $i++) {
            $this->_streamNumbers[] = $this->_reader->readUInt16LE();
        }
    }

    /**
     * Returns the nature of the mutual exclusion relationship.
     *
     * @return string
     */
    public function getExclusionType() 
    {
        return $this->_exclusionType; 
    }

    /**
     * Returns the nature of the mutual exclusion relationship.
     *
     * @return string
     */
    public function setExclusionType($exclusionType)
    {
        $this->_exclusionType = $exclusionType;
    }

    /**
     * Returns an array of stream numbers.
     *
     * @return Array
     */
    public function getStreamNumbers() 
    {
        return $this->_streamNumbers; 
    }

    /**
     * Sets the array of stream numbers.
     *
     * @return Array
     */
    public function setStreamNumbers($streamNumbers)
    {
        $this->_streamNumbers = $streamNumbers;
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        $streamNumbersCount = count($this->_streamNumbers);

        $this->setSize(24 /* for header */ + 18 + $streamNumbersCount * 2);

        $writer->writeGuid($this->getIdentifier())
            ->writeInt64LE($this->getSize())
            ->writeGuid($this->_exclusionType)
            ->writeUInt16LE($streamNumbersCount);
        for ($i = 0; $i < $streamNumbersCount; $i++) {
            $writer->writeUInt16LE($this->_streamNumbers[$i]);
        }
    }
}
