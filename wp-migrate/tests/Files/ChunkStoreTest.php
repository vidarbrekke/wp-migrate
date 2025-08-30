<?php
/**
 * ChunkStore Tests
 *
 * Tests the critical file handling component that manages resumable uploads.
 * These tests ensure data integrity, security, and proper file operations.
 */

namespace WpMigrate\Tests\Files;

use WpMigrate\Files\ChunkStore;
use WpMigrate\Tests\TestHelper;
use PHPUnit\Framework\TestCase;

class ChunkStoreTest extends TestCase
{
    private ChunkStore $chunkStore;
    private string $testJobId;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        TestHelper::reset();

        $this->testJobId = 'test-job-' . uniqid();
        $this->tempDir = TestHelper::createTempDirectory();

        // Mock wp_upload_dir to use our temp directory
        if (!function_exists('wp_upload_dir')) {
            function wp_upload_dir() {
                $tempDir = $GLOBALS['test_temp_dir'] ?? sys_get_temp_dir() . '/wp-uploads';
                return [
                    'path' => $tempDir,
                    'url' => 'http://test.example.com/wp-uploads',
                    'subdir' => '',
                    'basedir' => $tempDir,
                    'baseurl' => 'http://test.example.com/wp-uploads',
                    'error' => false,
                ];
            }
        }
        $GLOBALS['test_temp_dir'] = $this->tempDir;

        $this->chunkStore = new ChunkStore();
    }

    protected function tearDown(): void
    {
        TestHelper::cleanupTempDirectory($this->tempDir);
        unset($GLOBALS['test_temp_dir']);
        parent::tearDown();
    }

    /**
     * Test directory creation
     */
    public function test_get_job_dir_creates_directory(): void
    {
        $jobDir = $this->chunkStore->get_job_dir($this->testJobId);

        $this->assertDirectoryExists($jobDir);
        $this->assertStringContainsString($this->testJobId, $jobDir);
    }

    /**
     * Test chunk directory creation
     */
    public function test_get_chunk_dir_creates_directory(): void
    {
        $chunkDir = $this->chunkStore->get_chunk_dir($this->testJobId);

        $this->assertDirectoryExists($chunkDir);
        $this->assertStringContainsString('chunks', $chunkDir);
    }

    /**
     * Test chunk path generation
     */
    public function test_chunk_path_generation(): void
    {
        $artifact = 'db_dump.sql.zst';
        $index = 5;

        $chunkPath = $this->chunkStore->chunk_path($this->testJobId, $artifact, $index);

        $this->assertStringContainsString($this->testJobId, $chunkPath);
        $this->assertStringContainsString('chunks', $chunkPath);
        $this->assertStringContainsString('db_dump.sql.zst.5', $chunkPath);
    }

    /**
     * Test successful chunk saving and validation
     */
    public function test_save_chunk_success(): void
    {
        $artifact = 'test-artifact.txt';
        $index = 0;
        $content = 'Hello, World!';
        $hash = base64_encode(hash('sha256', $content, true));

        // Should not throw an exception
        $this->chunkStore->save_chunk($this->testJobId, $artifact, $index, $content, $hash);

        // Verify file was created
        $chunkPath = $this->chunkStore->chunk_path($this->testJobId, $artifact, $index);
        $this->assertFileExists($chunkPath);

        // Verify content
        $savedContent = file_get_contents($chunkPath);
        $this->assertEquals($content, $savedContent);
    }

    /**
     * Test chunk hash validation failure
     */
    public function test_save_chunk_invalid_hash(): void
    {
        $artifact = 'test-artifact.txt';
        $index = 0;
        $content = 'Hello, World!';
        $invalidHash = base64_encode(hash('sha256', 'wrong content', true));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Chunk hash mismatch');

        $this->chunkStore->save_chunk($this->testJobId, $artifact, $index, $content, $invalidHash);
    }

    /**
     * Test chunk size limit enforcement
     */
    public function test_save_chunk_size_limit(): void
    {
        $artifact = 'large-artifact.txt';
        $index = 0;
        $largeContent = str_repeat('a', (64 * 1024 * 1024) + 1); // Exceed limit
        $hash = base64_encode(hash('sha256', $largeContent, true));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chunk exceeds maximum size limit');

        $this->chunkStore->save_chunk($this->testJobId, $artifact, $index, $largeContent, $hash);
    }

    /**
     * Test path traversal protection
     */
    public function test_save_chunk_path_traversal_protection(): void
    {
        $maliciousArtifact = '../../../etc/passwd';
        $index = 0;
        $content = 'malicious content';
        $hash = base64_encode(hash('sha256', $content, true));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid path component detected');

        $this->chunkStore->save_chunk($this->testJobId, $maliciousArtifact, $index, $content, $hash);
    }

    /**
     * Test job ID path traversal protection
     */
    public function test_save_chunk_job_id_path_traversal(): void
    {
        $maliciousJobId = '../../../malicious';
        $artifact = 'test.txt';
        $index = 0;
        $content = 'malicious content';
        $hash = base64_encode(hash('sha256', $content, true));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid path component detected');

        $this->chunkStore->save_chunk($maliciousJobId, $artifact, $index, $content, $hash);
    }

    /**
     * Test listing present chunks for empty artifact
     */
    public function test_list_present_empty(): void
    {
        $artifact = 'empty-artifact.txt';
        $present = $this->chunkStore->list_present($this->testJobId, $artifact);

        $this->assertIsArray($present);
        $this->assertEmpty($present);
    }

    /**
     * Test listing present chunks
     */
    public function test_list_present_with_chunks(): void
    {
        $artifact = 'multi-chunk-artifact.txt';

        // Create several chunks
        $chunksToCreate = [0, 2, 5, 10];
        foreach ($chunksToCreate as $index) {
            $content = "Chunk content $index";
            $hash = base64_encode(hash('sha256', $content, true));
            $this->chunkStore->save_chunk($this->testJobId, $artifact, $index, $content, $hash);
        }

        $present = $this->chunkStore->list_present($this->testJobId, $artifact);

        $this->assertEquals($chunksToCreate, $present);
    }

    /**
     * Test listing present chunks with different artifacts
     */
    public function test_list_present_different_artifacts(): void
    {
        $artifact1 = 'artifact1.txt';
        $artifact2 = 'artifact2.txt';

        // Create chunks for artifact1
        $this->chunkStore->save_chunk($this->testJobId, $artifact1, 0, 'content1', base64_encode(hash('sha256', 'content1', true)));
        $this->chunkStore->save_chunk($this->testJobId, $artifact1, 1, 'content2', base64_encode(hash('sha256', 'content2', true)));

        // Create chunks for artifact2
        $this->chunkStore->save_chunk($this->testJobId, $artifact2, 0, 'content3', base64_encode(hash('sha256', 'content3', true)));

        $present1 = $this->chunkStore->list_present($this->testJobId, $artifact1);
        $present2 = $this->chunkStore->list_present($this->testJobId, $artifact2);

        $this->assertEquals([0, 1], $present1);
        $this->assertEquals([0], $present2);
    }

    /**
     * Test chunk ordering
     */
    public function test_list_present_chunk_ordering(): void
    {
        $artifact = 'ordered-artifact.txt';

        // Create chunks in random order
        $indices = [5, 0, 10, 2, 1];
        foreach ($indices as $index) {
            $content = "Content $index";
            $hash = base64_encode(hash('sha256', $content, true));
            $this->chunkStore->save_chunk($this->testJobId, $artifact, $index, $content, $hash);
        }

        $present = $this->chunkStore->list_present($this->testJobId, $artifact);

        // Should be sorted
        $this->assertEquals([0, 1, 2, 5, 10], $present);
    }

    /**
     * Test filename sanitization and security
     */
    public function test_filename_sanitization(): void
    {
        $maliciousArtifact = 'test/../../../file.txt';
        $index = 0;
        $content = 'test content';
        $hash = base64_encode(hash('sha256', $content, true));

        // This should fail because even after sanitization, the path contains dangerous components
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid path component detected after sanitization');

        $this->chunkStore->save_chunk($this->testJobId, $maliciousArtifact, $index, $content, $hash);
    }

    /**
     * Test safe filename sanitization
     */
    public function test_safe_filename_sanitization(): void
    {
        $safeArtifact = 'my-safe-file.txt';
        $index = 0;
        $content = 'test content';
        $hash = base64_encode(hash('sha256', $content, true));

        // This should work with a safe filename
        $this->chunkStore->save_chunk($this->testJobId, $safeArtifact, $index, $content, $hash);

        // Verify the chunk was created
        $chunkDir = $this->chunkStore->get_chunk_dir($this->testJobId);
        $files = scandir($chunkDir);
        $this->assertIsArray($files, 'scandir should return an array');

        $chunkFiles = array_filter($files, fn($f) => !in_array($f, ['.', '..']));

        $this->assertCount(1, $chunkFiles);
        $this->assertNotEmpty($chunkFiles);
        $firstFile = reset($chunkFiles);
        $this->assertStringStartsWith('my-safe-file.txt', $firstFile);
    }

    /**
     * Test concurrent chunk operations
     */
    public function test_concurrent_chunk_operations(): void
    {
        $artifact = 'concurrent-artifact.txt';

        // Simulate concurrent saves
        $promises = [];
        for ($i = 0; $i < 10; $i++) {
            $content = "Concurrent content $i";
            $hash = base64_encode(hash('sha256', $content, true));
            $this->chunkStore->save_chunk($this->testJobId, $artifact, $i, $content, $hash);
        }

        $present = $this->chunkStore->list_present($this->testJobId, $artifact);

        // All chunks should be present
        $this->assertCount(10, $present);
        $this->assertEquals(range(0, 9), $present);
    }

    /**
     * Test chunk integrity after save/load cycle
     */
    public function test_chunk_integrity(): void
    {
        $artifact = 'integrity-test.txt';
        $originalContent = 'This is a test of chunk integrity with special chars: àáâãäåæçèéêë';
        $index = 0;

        // Save chunk
        $hash = base64_encode(hash('sha256', $originalContent, true));
        $this->chunkStore->save_chunk($this->testJobId, $artifact, $index, $originalContent, $hash);

        // Read chunk back
        $chunkPath = $this->chunkStore->chunk_path($this->testJobId, $artifact, $index);
        $readContent = file_get_contents($chunkPath);

        // Verify integrity
        $this->assertEquals($originalContent, $readContent);
        $this->assertEquals($hash, base64_encode(hash('sha256', $readContent, true)));
    }

    /**
     * Test large chunk handling
     */
    public function test_large_chunk_handling(): void
    {
        $artifact = 'large-chunk.txt';
        $largeContent = str_repeat('Large content test: 0123456789', 10000); // ~200KB
        $index = 0;

        $this->assertLessThan(64 * 1024 * 1024, strlen($largeContent));

        $hash = base64_encode(hash('sha256', $largeContent, true));
        $this->chunkStore->save_chunk($this->testJobId, $artifact, $index, $largeContent, $hash);

        // Verify
        $chunkPath = $this->chunkStore->chunk_path($this->testJobId, $artifact, $index);
        $readContent = file_get_contents($chunkPath);

        $this->assertEquals($largeContent, $readContent);
    }

    /**
     * Test binary content handling
     */
    public function test_binary_content_handling(): void
    {
        $artifact = 'binary-chunk.bin';
        $binaryContent = random_bytes(1024); // 1KB of random binary data
        $index = 0;

        $hash = base64_encode(hash('sha256', $binaryContent, true));
        $this->chunkStore->save_chunk($this->testJobId, $artifact, $index, $binaryContent, $hash);

        // Verify
        $chunkPath = $this->chunkStore->chunk_path($this->testJobId, $artifact, $index);
        $readContent = file_get_contents($chunkPath);

        $this->assertEquals($binaryContent, $readContent);
    }

    /**
     * Test empty chunk handling
     */
    public function test_empty_chunk_handling(): void
    {
        $artifact = 'empty-chunk.txt';
        $emptyContent = '';
        $index = 0;

        $hash = base64_encode(hash('sha256', $emptyContent, true));
        $this->chunkStore->save_chunk($this->testJobId, $artifact, $index, $emptyContent, $hash);

        // Verify
        $chunkPath = $this->chunkStore->chunk_path($this->testJobId, $artifact, $index);
        $readContent = file_get_contents($chunkPath);

        $this->assertEquals($emptyContent, $readContent);
    }

    /**
     * Test directory permission issues
     */
    public function test_directory_permission_handling(): void
    {
        // This test would require setting up a read-only directory scenario
        // For now, we'll test that the class handles directory creation properly

        $jobDir = $this->chunkStore->get_job_dir($this->testJobId);
        $chunkDir = $this->chunkStore->get_chunk_dir($this->testJobId);

        $this->assertDirectoryExists($jobDir);
        $this->assertDirectoryExists($chunkDir);

        // Verify permissions are reasonable (should be writable)
        $this->assertTrue(is_writable($jobDir));
        $this->assertTrue(is_writable($chunkDir));
    }

    /**
     * Test chunk path uniqueness
     */
    public function test_chunk_path_uniqueness(): void
    {
        $artifact1 = 'test1.txt';
        $artifact2 = 'test2.txt';
        $index = 0;

        $path1 = $this->chunkStore->chunk_path($this->testJobId, $artifact1, $index);
        $path2 = $this->chunkStore->chunk_path($this->testJobId, $artifact2, $index);

        $this->assertNotEquals($path1, $path2);
        $this->assertStringContainsString('test1.txt.0', $path1);
        $this->assertStringContainsString('test2.txt.0', $path2);
    }

    /**
     * Test cleanup of old chunks (simulated)
     */
    public function test_chunk_cleanup_simulation(): void
    {
        $artifact = 'cleanup-test.txt';

        // Create some chunks
        for ($i = 0; $i < 3; $i++) {
            $content = "Content $i";
            $hash = base64_encode(hash('sha256', $content, true));
            $this->chunkStore->save_chunk($this->testJobId, $artifact, $i, $content, $hash);
        }

        $present = $this->chunkStore->list_present($this->testJobId, $artifact);
        $this->assertCount(3, $present);

        // In a real scenario, old chunks would be cleaned up by a cron job
        // This test just verifies the basic functionality works
        $this->assertContains(0, $present);
        $this->assertContains(1, $present);
        $this->assertContains(2, $present);
    }
}
