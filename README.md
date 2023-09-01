# Flysystem Aruna

[![Tests](https://github.com/biigle/flysystem-aruna/actions/workflows/php.yml/badge.svg)](https://github.com/biigle/flysystem-aruna/actions/workflows/php.yml)

Flysystem adapter for the Aruna Object Storage.

This adapter performs most of the operations via S3. Only `listContents` requires an HTTP client and collection ID. Once the ListObjectV2 S3 operation is [implemented](https://github.com/ArunaStorage/DataProxy/issues/19) in Aruna, this adapter can be deprecated and the S3 adapter can be used directly.

## Installation

```bash
composer require biigle/flysystem-aruna
```
## Usage

```php
use Aws\S3\S3Client;
use Biigle\Flysystem\Aruna\ArunaAdapter;
use GuzzleHttp\Client;

# Scheme: <latest or semver>.<collection name>.<project name>
$bucket = 'latest.collection-name.project-name';
$collectionId = 'MYARUNACOLLECTIONULID';

$s3Client = new S3Client([
    'credentials' => [
        'key' => 'mykey',
        'secret' => 'mysecret',
    ],
    'endpoint' => "https://{$bucket}.data.gi.aruna-storage.org",
    // Keep as-is.
    'region' => '',
    'version' => 'latest',
    'bucket_endpoint' => true,
]);

$httpClient = new Client([
    'base_uri' => 'https://api.aruna-storage.org',
    'headers' => [
        'Authorization' => 'Bearer my-aruna-token-secret',
    ],
]);

$adapter = new ArunaAdapter($s3Client, $bucket, $httpClient, $collectionId);

$exists = $adapter->fileExists('path/to/file.jpg');
var_dump($exists);
// bool(true);
```

## Funding

This work was supported by the German Research Foundation (DFG) within the project “Establishment of the National Research Data Infrastructure (NFDI)” in the consortium NFDI4Biodiversity (project number 442032008).

![NFDI4Biodiversity Logo](NFDI_4_Biodiversity___Logo_Positiv.svg)
