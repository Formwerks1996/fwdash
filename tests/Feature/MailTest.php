<?php

use App\Models\User;
use App\Notifications\NewUserSignedInNotification;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunHttpTransport;

it('sends mail through Mailgun when configured', function () {
    config([
        'mail.default' => 'mailgun',
        'services.mailgun.domain' => 'mg.formwerks.ca',
        'services.mailgun.secret' => 'test-key',
    ]);

    expect(Mail::mailer('mailgun')->getSymfonyTransport())->toBeInstanceOf(MailgunHttpTransport::class);
});

it('links the new-user email to the users page', function () {
    $mail = (new NewUserSignedInNotification(User::factory()->make(['name' => 'Pat'])))->toMail(new stdClass);

    expect($mail->subject)->toContain('Pat')
        ->and($mail->actionUrl)->toBe(route('users'));
});
