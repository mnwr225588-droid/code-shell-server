<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmailVerificationRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('BREVO_API_KEY=test-brevo-api-key');
    }

    protected function tearDown(): void
    {
        putenv('BREVO_API_KEY');
        parent::tearDown();
    }

    public function test_registration_reports_that_the_automatic_verification_email_was_sent(): void
    {
        Http::fake([
            'api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-message-id'], 201),
        ]);

        $response = $this->postJson('/api/register', [
            'first_name' => 'Test',
            'middle_name' => 'Student',
            'last_name' => 'Account',
            'birth_date' => '2000-01-01',
            'email' => 'verification-test@example.com',
            'phone' => '01000000000',
            'country' => 'EG',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'email_verification_sent' => true,
                'message' => 'تم إنشاء الحساب وإرسال رسالة تأكيد البريد الإلكتروني.',
            ]);

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://api.brevo.com/v3/smtp/email'
            && data_get($request->data(), 'to.0.email') === 'verification-test@example.com'
        );
    }
}
