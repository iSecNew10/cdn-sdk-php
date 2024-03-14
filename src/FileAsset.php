<?php

namespace iSN10\CDN;

class FileAsset
{

	protected string $fileToken;
	protected string $editKey;
	protected string $fileName;

	public function __construct(string $fileToken, string $editKey, string $fileName)
	{
		$this->fileToken = $fileToken;
		$this->editKey = $editKey;
		$this->fileName = $fileName;
	}

	/**
	 * @return string
	 */
	public function getFileToken(): string
	{
		return $this->fileToken;
	}

	/**
	 * @return string
	 */
	public function getEditKey(): string
	{
		return $this->editKey;
	}

	/**
	 * @return string
	 */
	public function getFileName(): string
	{
		return $this->fileName;
	}
}
