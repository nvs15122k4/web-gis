<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Mọi tệp gọi bằng @vite([...]) trong giao diện phải có trong `input` của vite.config.js,
 * nếu không bản build production không có mục trong manifest và trang trả 500 (ViteException).
 */
class ViteEntriesTest extends TestCase
{
    public function test_moi_asset_trong_blade_deu_la_diem_vao_cua_vite(): void
    {
        $root = dirname(__DIR__, 2);
        preg_match("/input:\s*\[(.*?)\]/s", file_get_contents("$root/vite.config.js"), $m);
        preg_match_all("/'([^']+)'/", $m[1] ?? '', $cfg);
        $entries = $cfg[1];
        $this->assertNotEmpty($entries, 'không đọc được input của vite.config.js');

        $used = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$root/resources/views", \FilesystemIterator::SKIP_DOTS)) as $f) {
            if (preg_match_all('/@vite\(\[(.*?)\]\)/s', file_get_contents($f->getPathname()), $blocks)) {
                preg_match_all("/'([^']+)'/", implode(',', $blocks[1]), $assets);
                foreach ($assets[1] as $a) {
                    $used[$a] = $f->getFilename();
                }
            }
        }

        $this->assertNotEmpty($used);
        foreach ($used as $asset => $view) {
            $this->assertContains($asset, $entries, "$view dùng $asset nhưng vite.config.js không khai báo trong input");
        }
    }
}
