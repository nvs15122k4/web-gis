<?php

namespace Tests\Unit;

use App\Console\Commands\CompareReferenceEvents;
use App\Console\Commands\ImportPipelineOutput;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

class ConsoleCommandsTest extends BaseUnitTest
{
    public function test_lenh_da_dang_ky(): void
    {
        $all = Artisan::all();
        $this->assertInstanceOf(ImportPipelineOutput::class, $all['webgis:import']);
        $this->assertInstanceOf(CompareReferenceEvents::class, $all['webgis:compare-reference']);
    }

    public function test_import_dinh_nghia_tham_so_va_tuy_chon(): void
    {
        $def = Artisan::all()['webgis:import']->getDefinition();
        $this->assertTrue($def->getArgument('dir')->isRequired());
        $this->assertTrue($def->hasOption('dry-run'));
        $this->assertFalse($def->getOption('dry-run')->acceptValue());
    }

    public function test_compare_buffer_mac_dinh_500(): void
    {
        $opt = Artisan::all()['webgis:compare-reference']->getDefinition()->getOption('buffer');
        $this->assertSame('500', $opt->getDefault());
        $this->assertTrue($opt->acceptValue());
    }

    public static function descriptions(): array
    {
        return [['webgis:import'], ['webgis:compare-reference']];
    }

    #[DataProvider('descriptions')]
    public function test_lenh_co_mo_ta(string $name): void
    {
        $this->assertNotSame('', Artisan::all()[$name]->getDescription());
    }

    public function test_import_thu_muc_khong_ton_tai_tra_failure_va_khong_cham_db(): void
    {
        $code = Artisan::call('webgis:import', ['dir' => '/khong/ton/tai/'.uniqid()]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Không tìm thấy thư mục', Artisan::output());
    }

    public function test_import_cat_dau_gach_cheo_cuoi_trong_thong_bao(): void
    {
        $d = '/khong/ton/tai/'.uniqid();
        Artisan::call('webgis:import', ['dir' => $d.'///']);
        $this->assertStringContainsString("thư mục: {$d}\n", Artisan::output());
    }

    public static function featureFiles(): array
    {
        return [
            'khong co file' => [null, []],
            'file hop le' => ['{"type":"FeatureCollection","features":[{"a":1},{"a":2}]}', [['a' => 1], ['a' => 2]]],
            'khong co features' => ['{"type":"FeatureCollection"}', []],
            'features rong' => ['{"features":[]}', []],
        ];
    }

    #[DataProvider('featureFiles')]
    public function test_import_features_doc_file_json(?string $content, array $expected): void
    {
        $dir = sys_get_temp_dir().'/wg_unit_'.uniqid();
        mkdir($dir);
        if ($content !== null) {
            file_put_contents("$dir/x.json", $content);
        }
        try {
            $m = (new ReflectionClass(ImportPipelineOutput::class))->getMethod('features');
            $this->assertSame($expected, $m->invoke(new ImportPipelineOutput, $dir, 'x'));
        } finally {
            @unlink("$dir/x.json");
            rmdir($dir);
        }
    }

    public function test_import_features_nem_loi_khi_json_hong(): void
    {
        $dir = sys_get_temp_dir().'/wg_unit_'.uniqid();
        mkdir($dir);
        file_put_contents("$dir/x.json", '{hong');
        try {
            $m = (new ReflectionClass(ImportPipelineOutput::class))->getMethod('features');
            $this->expectException(\JsonException::class);
            $m->invoke(new ImportPipelineOutput, $dir, 'x');
        } finally {
            unlink("$dir/x.json");
            rmdir($dir);
        }
    }
}
