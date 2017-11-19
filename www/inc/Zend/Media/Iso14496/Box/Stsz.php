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
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS 'AS IS'
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Stsz.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Sample Size Box</i> contains the sample count and a table giving the
 * size in bytes of each sample. This allows the media data itself to be
 * unframed. The total number of samples in the media is always indicated in the
 * sample count.
 *
 * There are two variants of the sample size box. The first variant has a fixed
 * size 32-bit field for representing the sample sizes; it permits defining a
 * constant size for all samples in a track. The second variant permits smaller
 * size fields, to save space when the sizes are varying but small. One of these
 * boxes must be present; the first version is preferred for maximum
 * compatibility.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Stsz.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Stsz extends Zend_Media_Iso14496_FullBox
{
    /** @var integer */
    private $_sampleSize;

    /** @var Array */
    private $_sampleSizeTable = array();

    /**
     * Constructs the class with given parameters and reads box related data
     * from the ISO Base Media file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader, &$options = array())
    {
        parent::__construct($reader, $options);

        $this->_sampleSize = $this->_reader->readUInt32BE();
        $sampleCount = $this->_reader->readUInt32BE();
        if ($this->_sampleSize == 0) {
            for ($i = 1; $i <= $sampleCount; $i++) {
                $this->_sampleSizeTable[$i] = $this->_reader->readUInt32BE();
            }
        }
    }

    /**
     * Returns the default sample size. If all the samples are the same size,
     * this field contains that size value. If this field is set to 0, then the
     * samples have different sizes, and those sizes are stored in the sample
     * size table.
     *
     * @return integer
     */
    public function getSampleSize()
    {
        return $this->_sampleSize;
    }

    /**
     * Sets the default sample size. If all the samples are the same size,
     * this field contains that size value. If this field is set to 0, then the
     * samples have different sizes, and those sizes are stored in the sample
     * size table.
     *
     * @param integer $sampleSize The default sample size.
     */
    public function setSampleSize($sampleSize)
    {
        $this->_sampleSize = $sampleSize;
    }

    /**
     * Returns an array of sample sizes specifying the size of a sample, indexed
     * by its number.
     *
     * @return Array
     */
    public function getSampleSizeTable()
    {
        return $this->_sampleSizeTable;
    }

    /**
     * Sets an array of sample sizes specifying the size of a sample, indexed
     * by its number.
     *
     * @param Array $sampleSizeTable The array of sample sizes.
     */
    public function setSampleSizeTable($sampleSizeTable)
    {
        $this->_sampleSizeTable = $sampleSizeTable;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 8 +
            ($this->_sampleSize == 0 ? count($this->_sampleSizeTable) * 4 : 0);
    }

    /**
     * Writes the box data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        parent::_writeData($writer);
        $writer->writeUInt32BE($this->_sampleSize);
        $writer->writeUInt32BE($entryCount = count($this->_sampleSizeTable));
        if ($this->_sampleSize == 0) {
            for ($i = 1; $i <= $entryCount; $i++) {
                $writer->writeUInt32BE($this->_sampleSizeTable[$i]);
            }
        }
    }
}
