<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ThresholdConfigController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

/** Rule validate của ThresholdConfigController::update và AuthController::login (trước khi chạm DB). */
class ThresholdAndAuthValidationExtraTest extends BaseUnitTest
{
    private function putReq(array $body): Request
    {
        return Request::create('/api/admin/config/thresholds/x', 'PUT', $body);
    }

    private function thresholdErrors(array $body): ?array
    {
        try {
            (new ThresholdConfigController)->update($this->putReq($body), 'x');
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return null; // đã qua validate (sau đó sẽ chạm DB -> không xảy ra ở các ca dùng hàm này)
    }

    public static function badThresholds(): array
    {
        return [
            'thieu value' => [[]],
            'value null' => [['value' => null]],
            'chuoi rong' => [['value' => '']],
            'chuoi chu' => [['value' => 'abc']],
            'am nguyen' => [['value' => -1]],
            'am thap phan' => [['value' => -0.01]],
            'am chuoi' => [['value' => '-5']],
            'am rat nho' => [['value' => '-0.0000001']],
            'mang' => [['value' => [1]]],
            'boolean chuoi' => [['value' => 'true']],
            'hex' => [['value' => '0x1A']],
            'nhieu dau cham' => [['value' => '1.2.3']],
            'khoang trang' => [['value' => ' ']],
            'infinity' => [['value' => 'INF']],
            'dau phay thap phan' => [['value' => '1,5']],
        ];
    }

    #[DataProvider('badThresholds')]
    public function test_update_nem_validation_exception_voi_gia_tri_sai(array $body): void
    {
        $errors = $this->thresholdErrors($body);
        $this->assertNotNull($errors);
        $this->assertArrayHasKey('value', $errors);
    }

    public function test_thong_diep_loi_dung_rule(): void
    {
        $this->assertStringContainsString('required', strtolower(implode(' ', $this->thresholdErrors([])['value'])));
        $this->assertStringContainsString('at least 0', implode(' ', $this->thresholdErrors(['value' => -1])['value']));
        $this->assertStringContainsString('number', implode(' ', $this->thresholdErrors(['value' => 'x'])['value']));
    }

    public static function goodThresholds(): array
    {
        return [
            'zero nguyen' => [0], 'zero chuoi' => ['0'], 'zero thap phan' => ['0.0'], 'am zero' => ['-0'],
            'duong nho' => [0.0001], 'duong nguyen' => [5], 'chuoi so' => ['12.5'], 'lon' => [1e9],
            'khoa hoc' => ['1e3'], 'chuoi nguyen' => ['100'], 'ban thap phan' => ['.5'],
        ];
    }

    #[DataProvider('goodThresholds')]
    public function test_rule_value_chap_nhan_so_khong_am(mixed $v): void
    {
        $v = Validator::make(['value' => $v], ['value' => ['required', 'numeric', 'min:0']]);
        $this->assertTrue($v->passes(), json_encode($v->errors()->all()));
    }

    #[DataProvider('goodThresholds')]
    public function test_controller_khong_nem_validation_khi_value_hop_le(mixed $v): void
    {
        // Qua validate rồi mới tới DB: kỳ vọng KHÔNG phải ValidationException (lỗi kết nối/Model là chấp nhận được).
        try {
            (new ThresholdConfigController)->update($this->putReq(['value' => $v]), 'x');
        } catch (ValidationException $e) {
            $this->fail('Giá trị hợp lệ bị từ chối: '.json_encode($e->errors()));
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }
        $this->addToAssertionCount(1);
    }

    public function test_chi_value_duoc_validate_cac_truong_khac_bi_loai_khoi_validated(): void
    {
        $v = Validator::make(['value' => 3, 'key' => 'hack', 'updated_by' => 99], ['value' => ['required', 'numeric', 'min:0']]);
        $this->assertSame(['value' => 3], $v->validated());
    }

    // ---- AuthController::login ----

    public static function badLogins(): array
    {
        return [
            'rong' => [[], ['email', 'password']],
            'thieu password' => [['email' => 'a@b.co'], ['password']],
            'thieu email' => [['password' => 'x'], ['email']],
            'email sai dinh dang' => [['email' => 'abc', 'password' => 'x'], ['email']],
            'email thieu ten mien' => [['email' => 'a@', 'password' => 'x'], ['email']],
            'email la mang' => [['email' => ['a@b.co'], 'password' => 'x'], ['email']],
            'password la so' => [['email' => 'a@b.co', 'password' => 123], ['password']],
            'password la mang' => [['email' => 'a@b.co', 'password' => ['x']], ['password']],
            'password rong' => [['email' => 'a@b.co', 'password' => ''], ['password']],
            'email rong' => [['email' => '', 'password' => 'x'], ['email']],
        ];
    }

    #[DataProvider('badLogins')]
    public function test_login_validate_that_bai_dung_truong(array $body, array $fields): void
    {
        try {
            (new AuthController)->login(Request::create('/api/auth/login', 'POST', $body));
            $this->fail('Phải ném ValidationException');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing($fields, array_keys($e->errors()));
        }
    }

    public function test_login_validation_exception_status_422(): void
    {
        try {
            (new AuthController)->login(Request::create('/api/auth/login', 'POST', []));
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
        }
    }
}
