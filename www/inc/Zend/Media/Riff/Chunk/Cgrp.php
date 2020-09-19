<?php
/**
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: Cgrp.php 257 2012-01-26 05:30:58Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Riff/Chunk.php';
/**#@-*/

/**
 * The <i>Compound File Element Group</i> chunk stores the actual elements of data referenced by the
 * {@link Zend_Media_Riff_Chunk_Ctoc CTOC} chunk. The CGRP chunk contains all the compound file elements, concatenated
 * together into one contiguous block of data. Some of the elements in the CGRP chunk might be unused, if the element
 * was marked for deletion or was altered and stored elsewhere within the CGRP chunk.
 *
 * Elements within the CGRP chunk are of arbitrary size and can appear in a specific or arbitrary order, depending upon
 * the file format definition. Each element is identified by a corresponding {@link Zend_Media_Riff_Chunk_Ctoc CTOC}
 * table entry.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: Cgrp.php 257 2012-01-26 05:30:58Z svollbehr $
 * @todo       Implementation
 */
final class Zend_Media_Riff_Chunk_Cgrp extends Zend_Media_Riff_Chunk
{
    /**
     * Constructs the class with given parameters and options.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     */
    public function __construct($reader)
    {
        parent::__construct($reader);
        require_once('Zend/Media/Riff/Exception.php');
        throw new Zend_Media_Riff_Exception('Not yet implemented');
    }
}
