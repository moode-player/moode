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
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Rvrb.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * The <i>Reverb</i> is yet another subjective frame, with which you can adjust
 * echoes of different kinds. Reverb left/right is the delay between every
 * bounce in milliseconds. Reverb bounces left/right is the number of bounces
 * that should be made. $FF equals an infinite number of bounces. Feedback is
 * the amount of volume that should be returned to the next echo bounce. $00 is
 * 0%, $FF is 100%. If this value were $7F, there would be 50% volume reduction
 * on the first bounce, 50% of that on the second and so on. Left to left means
 * the sound from the left bounce to be played in the left speaker, while left
 * to right means sound from the left bounce to be played in the right speaker.
 *
 * Premix left to right is the amount of left sound to be mixed in the right
 * before any reverb is applied, where $00 id 0% and $FF is 100%. Premix right
 * to left does the same thing, but right to left. Setting both premix to $FF
 * would result in a mono output (if the reverb is applied symmetric). There
 * may only be one RVRB frame in each tag.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Rvrb.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Rvrb extends Zend_Media_Id3_Frame
{
    /** @var integer */
    private $_reverbLeft;

    /** @var integer */
    private $_reverbRight;

    /** @var integer */
    private $_reverbBouncesLeft;

    /** @var integer */
    private $_reverbBouncesRight;

    /** @var integer */
    private $_reverbFeedbackLtoL;

    /** @var integer */
    private $_reverbFeedbackLtoR;

    /** @var integer */
    private $_reverbFeedbackRtoR;

    /** @var integer */
    private $_reverbFeedbackRtoL;

    /** @var integer */
    private $_premixLtoR;

    /** @var integer */
    private $_premixRtoL;

    /**
     * Constructs the class with given parameters and parses object related
     * data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        if ($this->_reader === null) {
            return;
        }

        $this->_reverbLeft  = $this->_reader->readUInt16BE();
        $this->_reverbRight = $this->_reader->readUInt16BE();
        $this->_reverbBouncesLeft  = $this->_reader->readUInt8();
        $this->_reverbBouncesRight = $this->_reader->readUInt8();
        $this->_reverbFeedbackLtoL = $this->_reader->readUInt8();
        $this->_reverbFeedbackLtoR = $this->_reader->readUInt8();
        $this->_reverbFeedbackRtoR = $this->_reader->readUInt8();
        $this->_reverbFeedbackRtoL = $this->_reader->readUInt8();
        $this->_premixLtoR  = $this->_reader->readUInt8();
        $this->_premixRtoL  = $this->_reader->readUInt8();
    }

    /**
     * Returns the left reverb.
     *
     * @return integer
     */
    public function getReverbLeft() 
    {
        return $this->_reverbLeft; 
    }

    /**
     * Sets the left reverb.
     *
     * @param integer $reverbLeft The left reverb.
     */
    public function setReverbLeft($reverbLeft)
    {
        return $this->_reverbLeft = $reverbLeft;
    }

    /**
     * Returns the right reverb.
     *
     * @return integer
     */
    public function getReverbRight() 
    {
        return $this->_reverbRight; 
    }

    /**
     * Sets the right reverb.
     *
     * @param integer $reverbRight The right reverb.
     */
    public function setReverbRight($reverbRight)
    {
        return $this->_reverbRight = $reverbRight;
    }

    /**
     * Returns the left reverb bounces.
     *
     * @return integer
     */
    public function getReverbBouncesLeft() 
    {
        return $this->_reverbBouncesLeft; 
    }

    /**
     * Sets the left reverb bounces.
     *
     * @param integer $reverbBouncesLeft The left reverb bounces.
     */
    public function setReverbBouncesLeft($reverbBouncesLeft)
    {
        $this->_reverbBouncesLeft = $reverbBouncesLeft;
    }

    /**
     * Returns the right reverb bounces.
     *
     * @return integer
     */
    public function getReverbBouncesRight() 
    {
        return $this->_reverbBouncesRight; 
    }

    /**
     * Sets the right reverb bounces.
     *
     * @param integer $reverbBouncesRight The right reverb bounces.
     */
    public function setReverbBouncesRight($reverbBouncesRight)
    {
        $this->_reverbBouncesRight = $reverbBouncesRight;
    }

    /**
     * Returns the left-to-left reverb feedback.
     *
     * @return integer
     */
    public function getReverbFeedbackLtoL() 
    {
        return $this->_reverbFeedbackLtoL; 
    }

    /**
     * Sets the left-to-left reverb feedback.
     *
     * @param integer $reverbFeedbackLtoL The left-to-left reverb feedback.
     */
    public function setReverbFeedbackLtoL($reverbFeedbackLtoL)
    {
        $this->_reverbFeedbackLtoL = $reverbFeedbackLtoL;
    }

    /**
     * Returns the left-to-right reverb feedback.
     *
     * @return integer
     */
    public function getReverbFeedbackLtoR() 
    {
        return $this->_reverbFeedbackLtoR; 
    }

    /**
     * Sets the left-to-right reverb feedback.
     *
     * @param integer $reverbFeedbackLtoR The left-to-right reverb feedback.
     */
    public function setReverbFeedbackLtoR($reverbFeedbackLtoR)
    {
        $this->_reverbFeedbackLtoR = $reverbFeedbackLtoR;
    }

    /**
     * Returns the right-to-right reverb feedback.
     *
     * @return integer
     */
    public function getReverbFeedbackRtoR() 
    {
        return $this->_reverbFeedbackRtoR; 
    }

    /**
     * Sets the right-to-right reverb feedback.
     *
     * @param integer $reverbFeedbackRtoR The right-to-right reverb feedback.
     */
    public function setReverbFeedbackRtoR($reverbFeedbackRtoR)
    {
        $this->_reverbFeedbackRtoR = $reverbFeedbackRtoR;
    }

    /**
     * Returns the right-to-left reverb feedback.
     *
     * @return integer
     */
    public function getReverbFeedbackRtoL() 
    {
        return $this->_reverbFeedbackRtoL; 
    }

    /**
     * Sets the right-to-left reverb feedback.
     *
     * @param integer $reverbFeedbackRtoL The right-to-left reverb feedback.
     */
    public function setReverbFeedbackRtoL($reverbFeedbackRtoL)
    {
        $this->_reverbFeedbackRtoL = $reverbFeedbackRtoL;
    }

    /**
     * Returns the left-to-right premix.
     *
     * @return integer
     */
    public function getPremixLtoR() 
    {
        return $this->_premixLtoR; 
    }

    /**
     * Sets the left-to-right premix.
     *
     * @param integer $premixLtoR The left-to-right premix.
     */
    public function setPremixLtoR($premixLtoR)
    {
        $this->_premixLtoR = $premixLtoR;
    }

    /**
     * Returns the right-to-left premix.
     *
     * @return integer
     */
    public function getPremixRtoL() 
    {
        return $this->_premixRtoL; 
    }

    /**
     * Sets the right-to-left premix.
     *
     * @param integer $premixRtoL The right-to-left premix.
     */
    public function setPremixRtoL($premixRtoL)
    {
        $this->_premixRtoL = $premixRtoL;
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeUInt16BE($this->_reverbLeft)
               ->writeUInt16BE($this->_reverbRight)
               ->writeUInt8($this->_reverbBouncesLeft)
               ->writeUInt8($this->_reverbBouncesRight)
               ->writeUInt8($this->_reverbFeedbackLtoL)
               ->writeUInt8($this->_reverbFeedbackLtoR)
               ->writeUInt8($this->_reverbFeedbackRtoR)
               ->writeUInt8($this->_reverbFeedbackRtoL)
               ->writeUInt8($this->_premixLtoR)
               ->writeUInt8($this->_premixRtoL);
    }
}
