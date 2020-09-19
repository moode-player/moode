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
 * @subpackage MPEG
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Object.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Mpeg/Object.php';
/**#@-*/

/**
 * The base class for all MPEG Audio Bit Stream objects.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage MPEG
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Object.php 177 2010-03-09 13:13:34Z svollbehr $
 */
abstract class Zend_Media_Mpeg_Abs_Object extends Zend_Media_Mpeg_Object
{
    /** @var integer */
    const VERSION_ONE = 3;

    /** @var integer */
    const VERSION_TWO = 2;

    /** @var integer */
    const VERSION_TWO_FIVE = 0;

    /** @var integer */
    const SAMPLING_FREQUENCY_LOW = 0;

    /** @var integer */
    const SAMPLING_FREQUENCY_HIGH = 1;

    /** @var integer */
    const LAYER_ONE = 3;

    /** @var integer */
    const LAYER_TWO = 2;

    /** @var integer */
    const LAYER_THREE = 1;

    /** @var integer */
    const CHANNEL_STEREO = 0;

    /** @var integer */
    const CHANNEL_JOINT_STEREO = 1;

    /** @var integer */
    const CHANNEL_DUAL_CHANNEL = 2;

    /** @var integer */
    const CHANNEL_SINGLE_CHANNEL = 3;

    /** @var integer */
    const MODE_SUBBAND_4_TO_31 = 0;

    /** @var integer */
    const MODE_SUBBAND_8_TO_31 = 1;

    /** @var integer */
    const MODE_SUBBAND_12_TO_31 = 2;

    /** @var integer */
    const MODE_SUBBAND_16_TO_31 = 3;

    /** @var integer */
    const MODE_ISOFF_MSSOFF = 0;

    /** @var integer */
    const MODE_ISON_MSSOFF = 1;

    /** @var integer */
    const MODE_ISOFF_MSSON = 2;

    /** @var integer */
    const MODE_ISON_MSSON = 3;

    /** @var integer */
    const EMPHASIS_NONE = 0;

    /** @var integer */
    const EMPHASIS_50_15 = 1;

    /** @var integer */
    const EMPHASIS_CCIT_J17 = 3;

    /**
     * Layer III side information size lookup table.  The table has the
     * following format.
     *
     * <code>
     * array (
     *   SAMPLING_FREQUENCY_HIGH | SAMPLING_FREQUENCY_LOW => array (
     *     CHANNEL_STEREO | CHANNEL_JOINT_STEREO | CHANNEL_DUAL_CHANNEL |
     *       CHANNEL_SINGLE_CHANNEL => <size>
     *   )
     * )
     * </code>
     *
     * @var Array
     */
    protected static $sidesizes = array(
        self::SAMPLING_FREQUENCY_HIGH => array(
            self::CHANNEL_STEREO => 32,
            self::CHANNEL_JOINT_STEREO => 32,
            self::CHANNEL_DUAL_CHANNEL => 32,
            self::CHANNEL_SINGLE_CHANNEL => 17
        ),
        self::SAMPLING_FREQUENCY_LOW => array(
            self::CHANNEL_STEREO => 17,
            self::CHANNEL_JOINT_STEREO => 17,
            self::CHANNEL_DUAL_CHANNEL => 17,
            self::CHANNEL_SINGLE_CHANNEL => 9
        )
    );

    /**
     * Constructs the class with given parameters.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader, &$options = array())
    {
        parent::__construct($reader, $options);
    }
}
