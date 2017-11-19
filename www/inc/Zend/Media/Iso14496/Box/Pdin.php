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
 * @version    $Id: Pdin.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Progressive Download Information Box</i> aids the progressive download
 * of an ISO file. The box contains pairs of numbers (to the end of the box)
 * specifying combinations of effective file download bitrate in units of
 * bytes/sec and a suggested initial playback delay in units of milliseconds.
 *
 * A receiving party can estimate the download rate it is experiencing, and from
 * that obtain an upper estimate for a suitable initial delay by linear
 * interpolation between pairs, or by extrapolation from the first or last
 * entry.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Pdin.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Pdin extends Zend_Media_Iso14496_FullBox
{
    /** @var Array */
    private $_progressiveDownloadInfo = array();

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

        while ($this->_reader->getOffset() <
               $this->getOffset() + $this->getSize()) {
            $this->_progressiveDownloadInfo[] = array
                ('rate' => $this->_reader->readUInt32BE(),
                 'initialDelay' => $this->_reader->readUInt32BE());
        }
    }

    /**
     * Returns the progressive download information array. The array consists of
     * items having two keys.
     *
     *   o rate  --  the download rate expressed in bytes/second
     *   o initialDelay  --  the suggested delay to use when playing the file,
     *     such that if download continues at the given rate, all data within
     *     the file will arrive in time for its use and playback should not need
     *     to stall.
     *
     * @return Array
     */
    public function getProgressiveDownloadInfo()
    {
        return $this->_progressiveDownloadInfo;
    }

    /**
     * Sets the progressive download information array. The array must consist
     * of items having two keys.
     *
     *   o rate  --  the download rate expressed in bytes/second
     *   o initialDelay  --  the suggested delay to use when playing the file,
     *     such that if download continues at the given rate, all data within
     *     the file will arrive in time for its use and playback should not need
     *     to stall.
     *
     * @param Array $progressiveDownloadInfo The array of values.
     */
    public function setProgressiveDownloadInfo($progressiveDownloadInfo)
    {
        $this->_progressiveDownloadInfo = $progressiveDownloadInfo;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() +
            count($this->_progressiveDownloadInfo) * 8;
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
        for ($i = 0; $i < count($this->_timeToSampleTable); $i++) {
            $writer->writeUInt32BE
                        ($this->_progressiveDownloadInfo[$i]['rate'])
                   ->writeUInt32BE
                        ($this->_progressiveDownloadInfo[$i]['initialDelay']);
        }
    }
}
