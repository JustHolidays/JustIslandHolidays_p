<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * public/build is kept in git (20 Nov 2026) because the live server has no Node.js: the site's
 * styles and scripts must be built (npm run build) and complete before every commit.
 */
class BuiltAssetsTest extends TestCase
{
    private string $build;

    protected function setUp(): void
    {
        $this->build = dirname(__DIR__, 2).'/public/build';
    }

    public function test_the_build_is_there_with_every_file_it_lists(): void
    {
        $this->assertFileExists($this->build.'/manifest.json', 'Run npm run build: public/build is deployed from git.');

        $manifest = json_decode((string) file_get_contents($this->build.'/manifest.json'), true);
        foreach (['resources/css/app.css', 'resources/js/app.js', 'resources/js/map.js', 'resources/js/altcha.js'] as $entry) {
            $this->assertArrayHasKey($entry, $manifest, "{$entry} is missing from the build. Run npm run build.");
        }
        foreach ($manifest as $entry) {
            foreach (array_merge([$entry['file']], $entry['css'] ?? [], $entry['assets'] ?? []) as $file) {
                $this->assertFileExists($this->build.'/'.$file, "The build lists {$file} but it is not there. Run npm run build.");
            }
        }
    }

    public function test_no_dev_server_file_is_left_to_override_the_build(): void
    {
        $hot = dirname(__DIR__, 2).'/public/hot';
        $ignored = (string) file_get_contents(dirname(__DIR__, 2).'/.gitignore');

        $this->assertStringContainsString('/public/hot', $ignored, 'public/hot must never be committed.');
        $this->assertStringNotContainsString("\n/public/build\n", $ignored, 'public/build must be committed.');
        $this->assertTrue(! file_exists($hot) || str_contains($ignored, '/public/hot'));
    }
}
