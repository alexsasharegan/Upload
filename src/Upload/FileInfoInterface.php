<?php
/**
 * Upload
 *
 * @author      Josh Lockhart <info@joshlockhart.com>
 * @copyright   2012 Josh Lockhart
 * @link        http://www.joshlockhart.com
 * @version     2.0.0
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
namespace Upload;

/**
 * FileInfo Interface
 *
 * @author  Josh Lockhart <info@joshlockhart.com>
 * @since   2.0.0
 * @package Upload
 */
/**
 * Interface FileInfoInterface
 * @package Upload
 */
interface FileInfoInterface {
	
	/**
	 * @return string
	 */
	public function getPathname();
	
	/**
	 * @return string
	 */
	public function getName();
	
	/**
	 * @param $name
	 *
	 * @return FileInfoInterface
	 */
	public function setName( $name );
	
	/**
	 * Get file extension (without dot prefix)
	 *
	 * @return string
	 */
	public function getExtension();
	
	/**
	 * Set file extension (without dot prefix)
	 *
	 * @param $extension
	 *
	 * @return FileInfoInterface
	 */
	public function setExtension( $extension );
	
	/**
	 * @return string
	 */
	public function getNameWithExtension();
	
	/**
	 * @return string
	 */
	public function getMimetype();
	
	/**
	 * Gets file size
	 * @link http://php.net/manual/en/splfileinfo.getsize.php
	 * @return int The file size in bytes.
	 * @since 5.1.2
	 */
	public function getSize();
	
	/**
	 * @return string
	 */
	public function getMd5();
	
	/**
	 * @return array
	 */
	public function getDimensions();
	
	/**
	 * @return bool
	 */
	public function isUploadedFile();
}
