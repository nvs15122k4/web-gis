<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;

class UserModelTest extends BaseUnitTest
{
    public static function roles(): array
    {
        return [
            'admin' => ['admin', true],
            'viewer' => ['viewer', false],
            'user' => ['user', false],
            'hoa thuong' => ['Admin', false],
            'viet hoa' => ['ADMIN', false],
            'co khoang trang' => [' admin', false],
            'chuoi rong' => ['', false],
            'null' => [null, false],
        ];
    }

    #[DataProvider('roles')]
    public function test_is_admin_chi_dung_khi_role_la_admin(?string $role, bool $expected): void
    {
        $this->assertSame($expected, (new User(['role' => $role]))->isAdmin());
    }

    public function test_fillable_gom_cac_cot_cho_phep(): void
    {
        $this->assertEqualsCanonicalizing(['name', 'email', 'password', 'role'], (new User)->getFillable());
    }

    public function test_hidden_an_password_va_remember_token(): void
    {
        $this->assertEqualsCanonicalizing(['password', 'remember_token'], (new User)->getHidden());
    }

    public function test_to_array_khong_lo_password(): void
    {
        $arr = (new User(['name' => 'A', 'email' => 'a@x.vn', 'password' => 'bimat123']))->toArray();
        $this->assertArrayNotHasKey('password', $arr);
        $this->assertSame('a@x.vn', $arr['email']);
    }

    public function test_password_duoc_hash_khi_gan(): void
    {
        $u = new User(['password' => 'bimat123']);
        $this->assertNotSame('bimat123', $u->password);
        $this->assertTrue(Hash::check('bimat123', $u->password));
    }

    public function test_mass_assignment_bo_qua_cot_la(): void
    {
        $u = new User(['name' => 'A', 'id' => 99, 'khong_ton_tai' => 1]);
        $this->assertNull($u->id);
        $this->assertNull($u->getAttribute('khong_ton_tai'));
    }

    public function test_email_verified_at_cast_datetime(): void
    {
        $u = (new User)->forceFill(['email_verified_at' => '2026-01-02 03:04:05']);
        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $u->email_verified_at);
        $this->assertSame('2026-01-02', $u->email_verified_at->toDateString());
    }

    public function test_user_dung_ket_noi_mac_dinh_va_co_timestamps(): void
    {
        $u = new User;
        $this->assertNull($u->getConnectionName());
        $this->assertTrue($u->usesTimestamps());
    }
}
