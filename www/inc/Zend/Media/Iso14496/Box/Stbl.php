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
 * @version    $Id: Stbl.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/Box.php';
/**#@-*/

/**
 * The <i>Sample Table Box</i> contains all the time and data indexing of the
 * media samples in a track. Using the tables here, it is possible to locate
 * samples in time, determine their type (e.g. I-frame or not), and determine
 * their size, container, and offset into that container.
 *
 * If the track that contains the Sample Table Box references no data, then the
 * Sample Table Box does not need to contain any sub-boxes (this is not a very
 * useful media track).
 *
 * If the track that the Sample Table Box is contained in does reference data,
 * then the following sub-boxes are required:
 * {@link Zend_Media_Iso14496_Box_Stsd Sample Description},
 * {@link Zend_Media_Iso14496_Box_Stsz Sample Size},
 * {@link Zend_Media_Iso14496_Box_Stsc Sample To Chunk}, and
 * {@link Zend_Media_Iso14496_Box_Stco Chunk Offset}. Further, the
 * {@link Zend_Media_Iso14496_Box_Stsd Sample Description Box} shall contain at
 * least one entry. A Sample Description Box is required because it contains
 * the data reference index field which indicates which
 * {@link Zend_Media_Iso14496_Box_Dref Data Reference Box} to use to retrieve
 * the media samples. Without the Sample Description, it is not possible to
 * determine where the media samples are stored. The
 * {@link Zend_Media_Iso14496_Box_Stss Sync Sample Box} is optional. If the
 * Sync Sample Box is not present, all samples are sync samples.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Stbl.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Stbl extends Zend_Media_Iso14496_Box
{
    /**
     * Constructs the class with given parameters and reads box related data
     * from the ISO Base Media file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);
        $this->setContainer(true);

        if ($reader === null) {
            return;
        }

        $this->constructBoxes();
    }
}
