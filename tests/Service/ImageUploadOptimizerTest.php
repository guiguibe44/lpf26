<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\UploadImageCategory;
use App\Service\ImageUploadOptimizer;
use PHPUnit\Framework\TestCase;

final class ImageUploadOptimizerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('Extension GD requise.');
        }

        $this->tempDir = sys_get_temp_dir().'/lpf26-img-opt-'.uniqid('', true);
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        foreach (scandir($this->tempDir) ?: [] as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }
            @unlink($this->tempDir.'/'.$file);
        }
        @rmdir($this->tempDir);
    }

    public function testOptimizeResizesAndConvertsToWebp(): void
    {
        $source = $this->tempDir.'/large.jpg';
        $image = imagecreatetruecolor(1200, 800);
        imagejpeg($image, $source, 90);

        $beforeSize = filesize($source);
        self::assertGreaterThan(0, $beforeSize);

        $optimizer = new ImageUploadOptimizer($this->tempDir);
        $basename = $optimizer->optimizeAbsolutePath($source, UploadImageCategory::Avatar);

        self::assertNotNull($basename);
        self::assertStringEndsWith('.webp', $basename);

        $optimizedPath = $this->tempDir.'/'.$basename;
        self::assertFileExists($optimizedPath);
        self::assertFileDoesNotExist($source);

        [$width, $height] = getimagesize($optimizedPath);
        self::assertLessThanOrEqual(512, $width);
        self::assertLessThanOrEqual(512, $height);
        self::assertLessThan($beforeSize, filesize($optimizedPath));
    }

    public function testSkipsSvg(): void
    {
        $source = $this->tempDir.'/flag.svg';
        file_put_contents($source, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>');

        $optimizer = new ImageUploadOptimizer($this->tempDir);
        $basename = $optimizer->optimizeAbsolutePath($source, UploadImageCategory::Flag);

        self::assertSame('flag.svg', $basename);
        self::assertFileExists($source);
    }
}
