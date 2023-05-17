<?php

namespace Biigle\Flysystem\Aruna;

use Aws\S3\S3ClientInterface;
use Exception;
use GuzzleHttp\Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToListContents;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;

class ArunaAdapter extends AwsS3V3Adapter
{
    /**
     * @var string[]
     */
    private const EXTRA_METADATA_FIELDS = [
        'Metadata',
        'StorageClass',
        'ETag',
        'VersionId',
    ];

    private MimeTypeDetector $mimeTypeDetector;
    private PathPrefixer $prefixer;

    public function __construct(
        private S3ClientInterface $client,
        private string $bucket,
        protected Client $httpClient,
        protected string $collectionId,
        string $prefix = '',
        VisibilityConverter $visibility = null,
        MimeTypeDetector $mimeTypeDetector = null,
        private array $options = [],
        private bool $streamReads = true,
        private array $forwardedOptions = self::AVAILABLE_OPTIONS,
        private array $metadataFields = self::EXTRA_METADATA_FIELDS,
        private array $multipartUploadOptions = self::MUP_AVAILABLE_OPTIONS,
    )
    {
        parent::__construct(
            $client,
            $bucket,
            $prefix,
            $visibility,
            $mimeTypeDetector,
            $options,
            $streamReads,
            $forwardedOptions,
            $metadataFields,
            $multipartUploadOptions,
        );

        $this->mimeTypeDetector = $mimeTypeDetector ?: new ExtensionMimeTypeDetector();
        $this->prefixer = new PathPrefixer($prefix);
    }

    public function mimeType(string $path): FileAttributes
    {
        $attributes = parent::mimeType($path);

        $mimeType = $attributes->mimeType() ?: $this->mimeTypeDetector->detectMimeTypeFromPath($attributes->path());

        $attributes = new FileAttributes(
            $attributes->path(),
            $attributes->fileSize(),
            null,
            $attributes->lastModified(),
            $mimeType,
            $attributes->extraMetadata(),
        );

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $response = $this->httpClient->get("v1/collection/{$this->collectionId}/paths");
            $paths = json_decode($response->getBody()->getContents())->objectPaths;
        } catch (Exception $e) {
            throw UnableToListContents::atLocation($path, $deep, $e);
        }

        $dirCache = [];
        $shallowDepth = substr_count($path, '/');
        if ($path !== '') {
            $shallowDepth += 1;
        }

        foreach ($paths as $p) {
            $filePath = ltrim(explode($this->bucket, $p->path)[1], '/');
            $filePath = $this->prefixer->stripPrefix($filePath);

            if (strpos($filePath, $path) !== 0) {
                continue;
            }

            $dirPath = '';
            $dirs = explode('/', $filePath);
            array_pop($dirs);
            foreach ($dirs as $depth => $dir) {
                $dir = "/{$dir}";
                if (!array_key_exists($dir, $dirCache)) {
                    $dirPath .= $dir;
                    $dirCache[$dirPath] = true;

                    if ("/{$path}" === $dirPath) {
                        continue;
                    }

                    if (!$deep && $depth !== $shallowDepth) {
                        continue;
                    }

                    yield new DirectoryAttributes($dirPath);
                }
            }

            $fileDepth = substr_count($filePath, '/');
            if (!$deep && $fileDepth !== $shallowDepth) {
                continue;
            }

            yield new FileAttributes($filePath);
        }
    }
}
