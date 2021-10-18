<?php

declare(strict_types=1);

namespace Baraja\PersonalDataExport;


use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Nette\Utils\Random;

final class PersonalDataSelection
{
	/** @var array<string, mixed[]> */
	private array $jsons = [];

	/** @var array<string, string> */
	private array $texts = [];

	/** @var array<string, string> */
	private array $files = [];

	private string $tempDir;


	public function __construct(?string $tempDir = null)
	{
		$this->tempDir = $tempDir ?? sys_get_temp_dir() . '/personal-export';
	}


	/**
	 * @param mixed[] $content
	 */
	public function addJson(string $filename, array $content): self
	{
		$this->jsons[$filename] = $content;

		return $this;
	}


	public function addText(string $filename, string $content): self
	{
		$this->texts[$filename] = $content;

		return $this;
	}


	public function addFile(string $path, ?string $name = null): self
	{
		if (!is_file($path)) {
			throw new \InvalidArgumentException('File "' . $path . '" does not exist.');
		}
		if ($name === null && preg_match('~[/\\\\]([^/\\\\]+)$~', $path, $nameParser) === 1) {
			$name = $nameParser[1];
		} else {
			throw new \InvalidArgumentException('File name for path "' . $path . '" is required.');
		}
		$this->files[$path] = $name;

		return $this;
	}


	public function export(?string $filename = null): void
	{
		$filename ??= 'personal-data-' . date('Y-m-d') . '.zip';
		$suffix = date('Y-m-d') . '-' . Random::generate(16);
		$tempPath = $this->tempDir . '/' . $suffix;
		$exportPath = $this->tempDir . '/' . $suffix . '.zip';
		FileSystem::createDir($tempPath);

		// 1. build a directory
		foreach ($this->jsons as $jsonFilename => $jsonContent) {
			FileSystem::write($tempPath . '/' . $jsonFilename, Json::encode($jsonContent, Json::PRETTY));
		}
		foreach ($this->texts as $textFilename => $textContent) {
			FileSystem::write($tempPath . '/' . $textFilename, $textContent);
		}
		foreach ($this->files as $fileRealPath => $fileAliasName) {
			FileSystem::copy($fileRealPath, $tempPath . '/' . $fileAliasName);
		}

		// 2. create zip
		$this->createZip($tempPath, $exportPath);

		// 3. clear temp
		FileSystem::delete($tempPath);

		// 4. Send file
		$this->sendFile($exportPath, $filename);

		// 5. Delete local copy
		FileSystem::delete($exportPath);
		die;
	}


	private function createZip(string $tempPath, string $exportPath): void
	{
		ignore_user_abort();
		$zip = new \ZipArchive;
		$zip->open($exportPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

		/** @var \SplFileInfo[] $files */
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($tempPath),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ($files as $name => $file) {
			if ($file->isDir()) {
				continue;
			}
			// Get real and relative path for current file
			$filePath = $file->getRealPath();
			assert(is_string($filePath));
			$relativePath = substr($filePath, strlen($tempPath) + 1);

			// Add current file to archive
			$zip->addFile($filePath, $relativePath);
		}

		// Zip archive will be created only after closing object
		$zip->close();
	}


	private function sendFile(string $diskPath, string $name): void
	{
		if (isset($_SERVER['SERVER_PROTOCOL'])) {
			header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
		}
		header('Cache-Control: public');
		header('Content-Type: application/zip');
		header('Content-Transfer-Encoding: Binary');
		header('Content-Length:' . filesize($diskPath));
		header('Content-Disposition: attachment; filename=' . $name);
		readfile($diskPath);
	}
}
