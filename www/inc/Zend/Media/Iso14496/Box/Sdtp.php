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
 * @version    $Id: Sdtp.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Independent and Disposable Samples Box</i> optional table answers
 * three questions about sample dependency:
 *   1) does this sample depend on others (is it an I-picture)?
 *   2) do no other samples depend on this one?
 *   3) does this sample contain multiple (redundant) encodings of the data at
 *      this time-instant (possibly with different dependencies)?
 *
 * In the absence of this table:
 *   1) the sync sample table answers the first question; in most video codecs,
 *      I-pictures are also sync points,
 *   2) the dependency of other samples on this one is unknown.
 *   3) the existence of redundant coding is unknown.
 *
 * When performing trick modes, such as fast-forward, it is possible to use the
 * first piece of information to locate independently decodable samples.
 * Similarly, when performing random access, it may be necessary to locate the
 * previous sync point or random access recovery point, and roll-forward from
 * the sync point or the pre-roll starting point of the random access recovery
 * point to the desired point. While rolling forward, samples on which no others
 * depend need not be retrieved or decoded.
 *

 * The value of sampleIsDependedOn is independent of the existence of redundant
 * codings. However, a redundant coding may have different dependencies from the
 * primary coding; if redundant codings are available, the value of
 * sampleDependsOn documents only the primary coding.
 *
 * A sample dependency Box may also occur in the
 * {@link Zend_Media_Iso14496_Box_Traf Track Fragment Box}.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Sdtp.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Sdtp extends Zend_Media_Iso14496_FullBox
{
    /** @var Array */
    private $_sampleDependencyTypeTable = array();

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

        $data = $this->_reader->read
            ($this->getOffset() + $this->getSize() -
             $this->_reader->getOffset());
        $dataSize = strlen($data);
        for ($i = 1; $i <= $dataSize; $i++) {
            $this->_sampleDependencyTypeTable[$i] = array
                ('sampleDependsOn' => (($tmp = ord($data[$i - 1])) >> 4) & 0x3,
                 'sampleIsDependedOn' => ($tmp >> 2) & 0x3,
                 'sampleHasRedundancy' => $tmp & 0x3);
        }
    }

    /**
     * Returns an array of values. Each entry is an array containing the
     * following keys.
     *   o sampleDependsOn -- takes one of the following four values:
     *     0: the dependency of this sample is unknown;
     *     1: this sample does depend on others (not an I picture);
     *     2: this sample does not depend on others (I picture);
     *     3: reserved
     *   o sampleIsDependedOn -- takes one of the following four values:
     *     0: the dependency of other samples on this sample is unknown;
     *     1: other samples depend on this one (not disposable);
     *     2: no other sample depends on this one (disposable);
     *     3: reserved
     *   o sampleHasRedundancy -- takes one of the following four values:
     *     0: it is unknown whether there is redundant coding in this sample;
     *     1: there is redundant coding in this sample;
     *     2: there is no redundant coding in this sample;
     *     3: reserved
     *
     * @return Array
     */
    public function getSampleDependencyTypeTable()
    {
        return $this->_sampleDependencyTypeTable;
    }

    /**
     * Sets the array of values. Each entry must be an array containing the
     * following keys.
     *   o sampleDependsOn -- takes one of the following four values:
     *     0: the dependency of this sample is unknown;
     *     1: this sample does depend on others (not an I picture);
     *     2: this sample does not depend on others (I picture);
     *     3: reserved
     *   o sampleIsDependedOn -- takes one of the following four values:
     *     0: the dependency of other samples on this sample is unknown;
     *     1: other samples depend on this one (not disposable);
     *     2: no other sample depends on this one (disposable);
     *     3: reserved
     *   o sampleHasRedundancy -- takes one of the following four values:
     *     0: it is unknown whether there is redundant coding in this sample;
     *     1: there is redundant coding in this sample;
     *     2: there is no redundant coding in this sample;
     *     3: reserved
     *
     * @param Array $sampleDependencyTypeTable The array of values
     */
    public function setSampleDependencyTypeTable($sampleDependencyTypeTable)
    {
        $this->_sampleDependencyTypeTable = $sampleDependencyTypeTable;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + count($this->_sampleDependencyTypeTable);
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
        for ($i = 1; $i <= count($this->_sampleDependencyTypeTable); $i++) {
            $writer->write(chr(
                (($this->_sampleDependencyTypeTable[$i]
                    ['sampleDependsOn'] & 0x3) << 4) |
                (($this->_sampleDependencyTypeTable[$i]
                    ['sampleIsDependedOn'] & 0x3) << 2) |
                (($this->_sampleDependencyTypeTable[$i]
                    ['sampleHasRedundancy'] & 0x3))));
        }
    }
}
