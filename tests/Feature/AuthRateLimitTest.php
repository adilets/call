<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_is_throttled_per_ip(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => "user{$i}@example.com"])
                ->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'user-next@example.com'])
            ->assertStatus(429);
    }

    public function test_forgot_password_is_throttled_per_email(): void
    {
        Notification::fake();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => 'target@example.com'])
                ->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'target@example.com'])
            ->assertStatus(429);
    }

    public function test_register_is_throttled_per_ip(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/register', ['email' => 'not-an-email'])
                ->assertStatus(302);
        }

        $this->post('/register', ['email' => 'not-an-email'])
            ->assertStatus(429);
    }

    public function test_login_page_is_not_throttled_by_auth_limiters(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get('/forgot-password')->assertStatus(200);
        }
    }

    public function test_livewire_temporary_upload_rejects_php_files(): void
    {
        $rules = ['file' => config('livewire.temporary_file_upload.rules')];

        $php = UploadedFile::fake()->createWithContent('shell.php', "<?php echo 'x'; ?>");

        // Real file (not fake) so the type is detected from content, as on a real upload.
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, "<?php echo 'x'; ?>");
        $disguised = new UploadedFile($path, 'photo.jpg', null, null, true);
        $image = UploadedFile::fake()->image('photo.png');
        $csv = UploadedFile::fake()->createWithContent('products.csv', "name,price\nTest,10\n");

        $this->assertTrue(Validator::make(['file' => $php], $rules)->fails());
        $this->assertTrue(Validator::make(['file' => $disguised], $rules)->fails());
        $this->assertTrue(Validator::make(['file' => $image], $rules)->passes());
        $this->assertTrue(Validator::make(['file' => $csv], $rules)->passes());
    }
}
