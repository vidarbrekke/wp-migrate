<?php
namespace WpMigrate\Tests\Unit\Files;

use WpMigrate\Files\ChunkStore;
use PHPUnit\Framework\TestCase;

class ChunkStoreTest extends TestCase
{
    private ChunkStore $chunkStore;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/chunk-store-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Mock wp_upload_dir to return our temp directory
        global $mock_upload_dir;
        $mock_upload_dir = [
            'basedir' => $this->tempDir,
            'baseurl' => 'http://example.com/uploads',
            'path' => $this->tempDir,
            'url' => 'http://example.com/uploads',
            'subdir' => '',
            'error' => false
        ];

        $this->chunkStore = new ChunkStore();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up temp directory
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testGetJobDirCreatesDirectory(): void
    {
        $jobId = 'test-job-123';

        $jobDir = $this->chunkStore->get_job_dir($jobId);

        $this->assertDirectoryExists($jobDir);
        $this->assertStringContainsString($jobId, $jobDir);
    }

    public function testGetChunkDirCreatesDirectory(): void
    {
        $jobId = 'test-job-456';

        $chunkDir = $this->chunkStore->get_chunk_dir($jobId);

        $this->assertDirectoryExists($chunkDir);
        $this->assertStringContainsString('chunks', $chunkDir);
    }

    public function testChunkPathGeneration(): void
    {
        $jobId = 'test-job-789';
        $artifact = 'database.sql';
        $index = 5;

        $chunkPath = $this->chunkStore->chunk_path($jobId, $artifact, $index);

        $this->assertStringContainsString($jobId, $chunkPath);
        $this->assertStringContainsString('database.sql.5', $chunkPath);
        $this->assertStringEndsWith('database.sql.5', $chunkPath);
    }

    public function testSaveChunkSuccess(): void
    {
        $jobId = 'save-test-job';
        $artifact = 'test-file.txt';
        $index = 0;
        $data = 'This is test chunk data';
        $hash = base64_encode(hash('sha256', $data, true));

        // Should not throw exception
        $this->chunkStore->save_chunk($jobId, $artifact, $index, $data, $hash);

        $chunkPath = $this->chunkStore->chunk_path($jobId, $artifact, $index);
        $this->assertFileExists($chunkPath);
        $this->assertEquals($data, file_get_contents($chunkPath));
    }

    public function testSaveChunkInvalidSize(): void
    {
        $jobId = 'size-test-job';
        $artifact = 'large-file.txt';
        $index = 0;

        // Create data larger than max chunk size
        $data = str_repeat('x', ChunkStore::MAX_CHUNK_SIZE + 1);
        $hash = base64_encode(hash('sha256', $data, true));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chunk exceeds maximum size limit');

        $this->chunkStore->save_chunk($jobId, $artifact, $index, $data, $hash);
    }

    public function testSaveChunkPathTraversal(): void
    {
        $jobId = 'path-test-job';
        $artifact = '../../../etc/passwd';
        $index = 0;
        $data = 'malicious data';
        $hash = base64_encode(hash('sha256', $data, true));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid path component detected');

        $this->chunkStore->save_chunk($jobId, $artifact, $index, $data, $hash);
    }

    public function testSaveChunkInvalidHash(): void
    {
        $jobId = 'hash-test-job';
        $artifact = 'test.txt';
        $index = 0;
        $data = 'test data';
        $invalidHash = base64_encode(hash('sha256', 'different data', true));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Chunk hash mismatch');

        $this->chunkStore->save_chunk($jobId, $artifact, $index, $data, $invalidHash);
    }

    public function testListPresentChunks(): void
    {
        $jobId = 'list-test-job';
        $artifact = 'multi-chunk-file.txt';

        // Create some test chunks
        $testChunks = [0, 2, 5, 10];
        foreach ($testChunks as $index) {
            $data = "Chunk data {$index}";
            $hash = base64_encode(hash('sha256', $data, true));
            $this->chunkStore->save_chunk($jobId, $artifact, $index, $data, $hash);
        }

        $presentChunks = $this->chunkStore->list_present($jobId, $artifact);

        $this->assertEquals($testChunks, $presentChunks);
    }

    public function testListPresentChunksEmpty(): void
    {
        $jobId = 'empty-list-job';
        $artifact = 'nonexistent.txt';

        $presentChunks = $this->chunkStore->list_present($jobId, $artifact);

        $this->assertEmpty($presentChunks);
    }

    public function testListPresentChunksWithSanitization(): void
    {
        $jobId = 'sanitize-test-job';
        $artifact = 'file/with/invalid<>chars.txt'; // Use characters that get sanitized but don't create path issues

        // Create a chunk with sanitized name
        $data = 'test data';
        $hash = base64_encode(hash('sha256', $data, true));
        $this->chunkStore->save_chunk($jobId, $artifact, 0, $data, $hash);

        $presentChunks = $this->chunkStore->list_present($jobId, $artifact);

        $this->assertContains(0, $presentChunks);
    }

    public function testDirectoryCreation(): void
    {
        $jobId = 'dir-creation-test';

        // Initially directories shouldn't exist
        $jobDir = $this->tempDir . '/mk-migrate-jobs/' . $jobId;
        $chunkDir = $jobDir . '/chunks';

        $this->assertDirectoryDoesNotExist($jobDir);
        $this->assertDirectoryDoesNotExist($chunkDir);

        // Call get_job_dir which should create the directory
        $this->chunkStore->get_job_dir($jobId);

        $this->assertDirectoryExists($jobDir);

        // Call get_chunk_dir which should create the chunks subdirectory
        $this->chunkStore->get_chunk_dir($jobId);

        $this->assertDirectoryExists($chunkDir);
    }

    public function testSanitizeFileNameFallback(): void
    {
        $jobId = 'sanitize-fallback-test';
        $artifact = ''; // Empty artifact name
        $index = 0;
        $data = 'test data';
        $hash = base64_encode(hash('sha256', $data, true));

        // Should not throw exception and use fallback name
        $this->chunkStore->save_chunk($jobId, $artifact, $index, $data, $hash);

        $presentChunks = $this->chunkStore->list_present($jobId, $artifact);

        $this->assertContains(0, $presentChunks);
    }
}
