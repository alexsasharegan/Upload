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

use Upload\Storage\FileSystem;

/**
 * File
 *
 * This class provides the implementation for an uploaded file. It exposes
 * common attributes for the uploaded file (e.g. name, extension, media type)
 * and allows you to attach validations to the file that must pass for the
 * upload to succeed.
 *
 * @method string getPathname()
 * @method string getName()
 * @method string getExtension()
 * @method string getNameWithExtension()
 * @method string getMimetype()
 * @method int getSize()
 * @method string getMd5()
 * @method array getDimensions()
 * @method bool isUploadedFile()
 * @method FileInfoInterface setName(string $name)
 * @method FileInfoInterface setExtension(string $extension)
 *
 * @author  Josh Lockhart <info@joshlockhart.com>
 * @since   1.0.0
 * @package Upload
 */
class File implements \ArrayAccess, \IteratorAggregate, \Countable {
	
	const BYTE     = 1;
	const KILOBYTE = 1024;
	const MEGABYTE = 1048576;
	const GIGABYTE = 1073741824;
	
	/**
	 * Upload error code messages
	 * @var array
	 */
	protected static $errorCodeMessages = [
		1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
		2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
		3 => 'The uploaded file was only partially uploaded',
		4 => 'No file was uploaded',
		6 => 'Missing a temporary folder',
		7 => 'Failed to write file to disk',
		8 => 'A PHP extension stopped the file upload',
	];
	
	/**
	 * Storage delegate
	 * @var StorageInterface
	 */
	protected $storage;
	
	/**
	 * File information
	 * @var FileInfoInterface[]
	 */
	protected $objects = [];
	
	/**
	 * Validations
	 * @var ValidationInterface[]
	 */
	protected $validations = [];
	
	/**
	 * Validation errors
	 * @var String[]
	 */
	protected $errors = [];
	
	/**
	 * Before validation callback
	 * @var callable
	 */
	protected $beforeValidation;
	
	/**
	 * After validation callback
	 * @var callable
	 */
	protected $afterValidation;
	
	/**
	 * Before upload callback
	 * @var callable
	 */
	protected $beforeUpload;
	
	/**
	 * After upload callback
	 * @var callable
	 */
	protected $afterUpload;
	
	/**
	 * Constructor
	 *
	 * @param  string           $key The $_FILES[] key
	 * @param  StorageInterface $storage The upload delegate instance
	 *
	 * @throws \RuntimeException                  If file uploads are disabled in the php.ini file
	 * @throws \InvalidArgumentException          If $_FILES[] does not contain key
	 */
	public function __construct( $key, StorageInterface $storage )
	{
		// Check if file uploads are allowed
		if ( ! ini_get( 'file_uploads' ) )
		{
			throw new \RuntimeException( 'File uploads are disabled in your PHP.ini file' );
		}
		
		// Check if key exists
		if ( ! isset( $_FILES[ $key ] ) )
		{
			throw new \InvalidArgumentException( "Cannot find uploaded file(s) identified by key: {$key}" );
		}
		
		// Collect file info
		if ( is_array( $_FILES[ $key ]['tmp_name'] ) )
		{
			foreach ( $_FILES[ $key ]['tmp_name'] as $index => $tmpName )
			{
				if ( $_FILES[ $key ]['error'][ $index ] !== UPLOAD_ERR_OK )
				{
					$this->errors[] = sprintf(
						'%s: %s',
						$_FILES[ $key ]['name'][ $index ],
						self::$errorCodeMessages[ $_FILES[ $key ]['error'][ $index ] ]
					);
					continue;
				}
				
				$this->objects[] = FileInfo::createFromFactory(
					$_FILES[ $key ]['tmp_name'][ $index ],
					$_FILES[ $key ]['name'][ $index ]
				);
			}
		}
		else
		{
			if ( $_FILES[ $key ]['error'] !== UPLOAD_ERR_OK )
			{
				$this->errors[] = sprintf(
					'%s: %s',
					$_FILES[ $key ]['name'],
					self::$errorCodeMessages[ $_FILES[ $key ]['error'] ]
				);
			}
			
			$this->objects[] = FileInfo::createFromFactory(
				$_FILES[ $key ]['tmp_name'],
				$_FILES[ $key ]['name']
			);
		}
		
		$this->storage = $storage;
	}
	
	/********************************************************************************
	 * Callbacks
	 *******************************************************************************/
	
	/**
	 * Convert human readable file size (e.g. "10K" or "3M") into bytes
	 *
	 * @param  string $input
	 *
	 * @return int
	 */
	public static function humanReadableToBytes( $input )
	{
		$number = (int) $input;
		$units  = [
			'b' => self::BYTE,
			'k' => self::KILOBYTE,
			'm' => self::MEGABYTE,
			'g' => self::GIGABYTE,
		];
		$unit   = strtolower( substr( $input, -1 ) );
		if ( isset( $units[ $unit ] ) )
		{
			$number = $number * $units[ $unit ];
		}
		
		return $number;
	}
	
	/**
	 * Set `beforeValidation` callable
	 *
	 * @param  callable $callable Should accept one `\Upload\FileInfoInterface` argument
	 *
	 * @return \Upload\File                        Self
	 * @throws \InvalidArgumentException           If argument is not a Closure or invokable object
	 */
	public function beforeValidate( $callable )
	{
		if ( is_callable( $callable ) )
		{
			throw new \InvalidArgumentException( 'Callback is not a Closure or invokable object.' );
		}
		
		$this->beforeValidation = $callable;
		
		return $this;
	}
	
	/**
	 * Set `afterValidation` callable
	 *
	 * @param  callable $callable Should accept one `\Upload\FileInfoInterface` argument
	 *
	 * @return \Upload\File                        Self
	 * @throws \InvalidArgumentException           If argument is not a Closure or invokable object
	 */
	public function afterValidate( $callable )
	{
		if ( is_callable( $callable ) )
		{
			throw new \InvalidArgumentException( 'Callback is not a Closure or invokable object.' );
		}
		
		$this->afterValidation = $callable;
		
		return $this;
	}
	
	/**
	 * Set `beforeUpload` callable
	 *
	 * @param  callable $callable Should accept one `\Upload\FileInfoInterface` argument
	 *
	 * @return \Upload\File                        Self
	 * @throws \InvalidArgumentException           If argument is not a Closure or invokable object
	 */
	public function beforeUpload( $callable )
	{
		if ( ! is_callable( $callable ) || ! method_exists( $callable, '__invoke' ) )
		{
			throw new \InvalidArgumentException( 'Callback is not a Closure or invokable object.' );
		}
		$this->beforeUpload = $callable;
		
		return $this;
	}
	
	/**
	 * Set `afterUpload` callable
	 *
	 * @param  callable $callable Should accept one `\Upload\FileInfoInterface` argument
	 *
	 * @return \Upload\File                        Self
	 * @throws \InvalidArgumentException           If argument is not a Closure or invokable object
	 */
	public function afterUpload( $callable )
	{
		if ( ! is_callable( $callable ) || ! method_exists( $callable, '__invoke' ) )
		{
			throw new \InvalidArgumentException( 'Callback is not a Closure or invokable object.' );
		}
		$this->afterUpload = $callable;
		
		return $this;
	}
	
	/********************************************************************************
	 * Validation and Error Handling
	 *******************************************************************************/
	
	/**
	 * Add file validations
	 *
	 * @param  array [\Upload\ValidationInterface] $validations
	 *
	 * @return \Upload\File                       Self
	 */
	public function addValidations( array $validations )
	{
		foreach ( $validations as $validation )
		{
			$this->addValidation( $validation );
		}
		
		return $this;
	}
	
	/**
	 * Add file validation
	 *
	 * @param  ValidationInterface $validation
	 *
	 * @return \Upload\File                Self
	 */
	public function addValidation( ValidationInterface $validation )
	{
		$this->validations[] = $validation;
		
		return $this;
	}
	
	/**
	 * Get file validations
	 *
	 * @return array[\Upload\ValidationInterface]
	 */
	public function getValidations()
	{
		return $this->validations;
	}
	
	/**
	 * Get file validation errors
	 *
	 * @return array[String]
	 */
	public function getErrors()
	{
		return $this->errors;
	}
	
	/********************************************************************************
	 * Helper Methods
	 ******************************************************************************
	 *
	 * @param $name
	 * @param $arguments
	 *
	 * @return array|mixed|null
	 */
	
	public function __call( $name, $arguments )
	{
		$count  = count( $this->objects );
		$result = NULL;
		
		if ( $count )
		{
			if ( $count > 1 )
			{
				$result = [];
				foreach ( $this->objects as $object )
				{
					$result[] = call_user_func_array( [ $object, $name ], $arguments );
				}
			}
			else
			{
				$result = call_user_func_array( [ $this->objects[0], $name ], $arguments );
			}
		}
		
		return $result;
	}
	
	/**
	 * Upload file (delegated to storage object)
	 *
	 * @return bool
	 * @throws Exception If validation fails
	 * @throws Exception If upload fails
	 */
	public function upload()
	{
		if ( ! $this->isValid() )
		{
			throw new Exception( "File validation failed. Errors: \n" . json_encode( $this->getErrors(),
					JSON_PRETTY_PRINT ) );
		}
		
		foreach ( $this->objects as $fileInfo )
		{
			$this->applyCallback( 'beforeUpload', $fileInfo );
			$this->storage->upload( $fileInfo );
			$this->applyCallback( 'afterUpload', $fileInfo );
		}
		
		return TRUE;
	}
	
	/********************************************************************************
	 * Upload
	 *******************************************************************************/
	
	/**
	 * Is this collection valid and without errors?
	 *
	 * @return bool
	 */
	public function isValid()
	{
		foreach ( $this->objects as $fileInfo )
		{
			// Before validation callback
			$this->applyCallback( 'beforeValidation', $fileInfo );
			
			// Check is uploaded file
			if ( ! $fileInfo->isUploadedFile() )
			{
				$this->errors[] = sprintf(
					'%s: %s',
					$fileInfo->getNameWithExtension(),
					'Is not an uploaded file'
				);
				continue;
			}
			
			// Apply user validations
			foreach ( $this->validations as $validation )
			{
				try
				{
					$validation->validate( $fileInfo );
				}
				catch ( Exception $e )
				{
					$this->errors[] = sprintf(
						'%s: %s',
						$fileInfo->getNameWithExtension(),
						$e->getMessage()
					);
				}
			}
			
			// After validation callback
			$this->applyCallback( 'afterValidation', $fileInfo );
		}
		
		return empty( $this->errors );
	}
	
	/**
	 * Safely applies callback
	 *
	 * @param                   $callbackName
	 * @param FileInfoInterface $file
	 *
	 * @return static
	 */
	protected function applyCallback( $callbackName, FileInfoInterface $file )
	{
		if ( in_array( $callbackName, [ 'beforeValidation', 'afterValidation', 'beforeUpload', 'afterUpload' ] ) )
		{
			if ( isset( $this->$callbackName ) )
			{
				call_user_func_array( $this->$callbackName, [ $file ] );
			}
		}
		
		return $this;
	}
	
	/********************************************************************************
	 * Array Access Interface
	 ******************************************************************************
	 *
	 * @param mixed $offset
	 *
	 * @return bool
	 */
	
	public function offsetExists( $offset )
	{
		return isset( $this->objects[ $offset ] );
	}
	
	public function offsetGet( $offset )
	{
		return isset( $this->objects[ $offset ] ) ? $this->objects[ $offset ] : NULL;
	}
	
	public function offsetSet( $offset, $value )
	{
		$this->objects[ $offset ] = $value;
	}
	
	public function offsetUnset( $offset )
	{
		unset( $this->objects[ $offset ] );
	}
	
	/********************************************************************************
	 * Iterator Aggregate Interface
	 *******************************************************************************/
	
	public function getIterator()
	{
		return new \ArrayIterator( $this->objects );
	}
	
	/********************************************************************************
	 * Helpers
	 *******************************************************************************/
	
	/********************************************************************************
	 * Countable Interface
	 *******************************************************************************/
	
	public function count()
	{
		return count( $this->objects );
	}
}
