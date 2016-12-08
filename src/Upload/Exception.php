<?php
namespace Upload;

class Exception extends \RuntimeException implements \JsonSerializable {
	
	/**
	 * @var FileInfoInterface
	 */
	protected $fileInfo;
	
	/**
	 * Constructor
	 *
	 * @param string            $message The Exception message
	 * @param FileInfoInterface $fileInfo The related file instance
	 */
	public function __construct( $message, FileInfoInterface $fileInfo = NULL )
	{
		$this->fileInfo = $fileInfo;
		
		parent::__construct( $message );
	}
	
	/**
	 * Get related file
	 *
	 * @return FileInfoInterface
	 */
	public function getFileInfo()
	{
		return $this->fileInfo;
	}
	
	/**
	 * Specify data which should be serialized to JSON
	 * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return mixed data which can be serialized by <b>json_encode</b>,
	 * which is a value of any type other than a resource.
	 * @since 5.4.0
	 */
	public function jsonSerialize()
	{
		return [
			'fileInfo' => $this->getFileInfo(),
			'file'     => $this->getFile(),
			'message'  => $this->getMessage(),
			'code'     => $this->getCode(),
			'line'     => $this->getLine(),
			'trace'    => $this->getTraceAsString(),
		];
	}
}
