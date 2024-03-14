<?php

namespace iSN10\CDN;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class APIClient
{
	protected Client $client;

	protected string $endpointUrl;

	protected string $apiToken;

	public function __construct(string $endpointUrl, string $apiToken)
	{
		$this->client = new Client();
		$this->endpointUrl = $endpointUrl;
		$this->apiToken = $apiToken;
	}

	/**
	 * @throws CDNException
	 * @throws GuzzleException
	 */
	private function requestApi(string $method, string $action, ?array $parameters = []): ResponseInterface
	{
		switch ($method) {
			case 'get':
			{
				return $this->client->get(
					$this->endpointUrl . '/' . $action . '?token='
					. $this->apiToken . (
					empty($parameters)
						? ''
						: '&' . http_build_query($parameters)
					)
				);
			}
			case 'post':
			{
				return $this->client->post(
					$this->endpointUrl . '/' . $action . '?token=' . $this->apiToken,
					$parameters
				);
			}
			default:
			{
				throw new CDNException('Invalid request method in ' . __CLASS__);
			}
		}
	}

	/**
	 * @throws CDNException
	 */
	public function uploadPDF(string $filePath, ?string $name = ''): ?FileAsset
	{
		return $this->uploadFile($filePath, $name, 'pdf');
	}

	/**
	 * @throws CDNException
	 */
	public function uploadImage(string $filePath, ?string $name = ''): ?FileAsset
	{
		return $this->uploadFile($filePath, $name, 'image');
	}

	/**
	 * @throws CDNException
	 */
	public function uploadVideo(string $filePath, ?string $name = ''): ?FileAsset
	{
		return $this->uploadFile($filePath, $name, 'video');
	}

	/**
	 * @throws CDNException
	 */
	public function uploadFile(string $filePath, ?string $name = '', ?string $fieldName = 'file'): ?FileAsset
	{
		try {
			$response = $this->requestApi('post', 'push-' . $fieldName, [
				'multipart' => [
					[
						'name' => $fieldName,
						'contents' => file_get_contents($filePath),
						'filename' => $name ?? basename($filePath),
					],
				],
			]);
		} catch (\Throwable $e) {
			throw new CDNException('Exception from CDN while uploading ' . $fieldName . ': ' . $e->getMessage());
		}

		return $this->parseFileAssetFromResponse(
			$response,
			$fieldName,
			$filePath
		);
	}

	/**
	 * @throws CDNException
	 */
	public function uploadRemoteFile(string $fileUrl, ?string $fileName = null, ?bool $secure = true): FileAsset
	{
		$fieldName = 'remote file';

		try {
			$response = $this->requestApi('post', 'push-remote', [
				'form_params' => [
					'remote' => $fileUrl,
					'name' => $fileName,
					'insecure' => (empty($secure) ? 1 : 0),
				],
			]);
		} catch (\Throwable $e) {
			throw new CDNException('Exception from CDN while uploading ' . $fieldName . ': ' . $fileUrl . ' - ' . $e->getMessage());
		}

		return $this->parseFileAssetFromResponse(
			$response,
			$fieldName,
			$fileUrl
		);
	}

	/**
	 * @throws CDNException
	 */
	public function deleteImage(string $fileToken, string $editKey): bool
	{
		return $this->deleteFile($fileToken, $editKey);
	}

	/**
	 * @throws CDNException
	 */
	public function deleteFile(string $fileToken, string $editKey): bool
	{
		try {
			$response = $this->requestApi('get', 'delete-file', [
				'file_token' => $fileToken,
				'edit_key' => $editKey,
			]);
		} catch (\Throwable $e) {
			throw new CDNException('Exception from CDN while deleting file (' . $fileToken . '): ' . $e->getMessage());
		}

		$jsonResponse = $this->parseResponse($response);

		if (empty($jsonResponse['data']) || empty($data = $jsonResponse['data']) || empty($message = $data['message'])) {
			if (empty($error = $jsonResponse['error'])) {
				throw new CDNException('Unknown error from CDN while deleting image');
			}
			throw new CDNException('An error occurred on CDN while uploading image: ' . $error);
		}

		return $message === 'The file was deleted successfully';
	}

	/**
	 * @throws CDNException
	 */
	private function parseFileAssetFromResponse(
		ResponseInterface $response,
		string $fieldName,
		string $fileUrlOrPath
	): FileAsset
	{
		$jsonResponse = $this->parseResponse(
			$response,
			'Invalid response from CDN while uploading ' . $fieldName . ': ' . $fileUrlOrPath
		);

		if (
			empty($jsonResponse['data'])
			|| empty($data = $jsonResponse['data'])
			|| empty($data['token'])
			|| empty($data['edit_key'])
			|| empty($data['name'])
		) {
			if (empty($jsonResponse['error'])) {
				throw new CDNException('Unknown error from CDN while uploading ' . $fieldName);
			}
			throw new CDNException(
				'An error occurred on CDN while uploading ' . $fieldName . ': ' . $jsonResponse['error']
			);
		}

		return new FileAsset($data['token'], $data['edit_key'], $data['name']);
	}

	/**
	 * @throws CDNException
	 */
	private function parseResponse(
		ResponseInterface $response,
		string $exceptionMessage = 'Invalid response from CDN while deleting image'
	): array
	{
		if (
			empty($response)
			|| empty($response->getBody())
			|| empty($plainResponse = $response->getBody()->getContents())
			|| empty($jsonResponse = json_decode($plainResponse, true))) {
			throw new CDNException(
				$exceptionMessage . (empty($plainResponse) ? '' : ' - ' . PHP_EOL . PHP_EOL . $plainResponse)
			);
		}
		return $jsonResponse;
	}
}
