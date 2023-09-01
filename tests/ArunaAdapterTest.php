<?php

use Aws\S3\S3Client;
use Biigle\Flysystem\Aruna\ArunaAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use PHPUnit\Framework\TestCase;


class ArunaAdapterTest extends TestCase
{
    public ArunaAdapter $adapter;

    public function setUp(): void
    {
        parent::setUp();
        $s3Client = new S3Client([
            'credentials' => [
                'key' => 'mykey',
                'secret' => 'mysecret',
            ],
            'endpoint' => 'https://latest.collection.project.data.gi.aruna-storage.org',
            'region' => 'us-west-2',
            'version' => 'latest',
            'bucket_endpoint' => true,
        ]);

        $mock = new MockHandler([
            new Response(200, [], '{"objectPaths":[{"path":"s3://latest.collection.project/subdir/subdir2/test.txt", "visibility":true},{"path":"s3://latest.collection.project/subdir/test.txt", "visibility":true},{"path":"s3://latest.collection.project/test.txt", "visibility":true}]}'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $this->adapter = new ArunaAdapter(
            $s3Client,
            'latest.collection.project',
            $httpClient,
            'MYCOLLECTIONULID',
        );
    }

    public function testListContents()
    {
        $contents = iterator_to_array($this->adapter->listContents('', true));
        $classes = array_map(fn($f) => get_class($f), $contents);
        $paths = array_map(fn($f) => $f->path(), $contents);

        $this->assertEquals([
            DirectoryAttributes::class,
            DirectoryAttributes::class,
            FileAttributes::class,
            FileAttributes::class,
            FileAttributes::class,
        ], $classes);

        $this->assertEquals([
            'subdir',
            'subdir/subdir2',
            'subdir/subdir2/test.txt',
            'subdir/test.txt',
            'test.txt',
        ], $paths);
    }

    public function testListContentsShallow()
    {
        $contents = iterator_to_array($this->adapter->listContents('', false));
        $classes = array_map(fn($f) => get_class($f), $contents);
        $paths = array_map(fn($f) => $f->path(), $contents);

        $this->assertEquals([
            DirectoryAttributes::class,
            FileAttributes::class,
        ], $classes);

        $this->assertEquals([
            'subdir',
            'test.txt',
        ], $paths);
    }

    public function testListContentsSubdir()
    {
        $contents = iterator_to_array($this->adapter->listContents('subdir/subdir2', false));
        $classes = array_map(fn($f) => get_class($f), $contents);
        $paths = array_map(fn($f) => $f->path(), $contents);

        $this->assertCount(1, $contents);
        $this->assertInstanceOf(FileAttributes::class, $contents[0]);
        $this->assertEquals('subdir/subdir2/test.txt', $contents[0]->path());
    }

    public function testListContentsSubdirShallow()
    {
        $contents = iterator_to_array($this->adapter->listContents('subdir', false));
        $classes = array_map(fn($f) => get_class($f), $contents);
        $paths = array_map(fn($f) => $f->path(), $contents);

        $this->assertEquals([
            DirectoryAttributes::class,
            FileAttributes::class,
        ], $classes);

        $this->assertEquals([
            'subdir/subdir2',
            'subdir/test.txt',
        ], $paths);
    }
}
