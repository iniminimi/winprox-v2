<?php

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\ConfirmReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Data\Reservations\ReservationBookingData;
use App\Models\Category;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

function reservationFixture(): array
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'is_reservable' => true,
    ]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'allow_reservations' => true,
        'is_active' => true,
    ]);

    return [$tenant, $admin, $unit];
}

function reservationWindow(int $hoursFromNow = 2, int $durationHours = 1): array
{
    $start = now()->addHours($hoursFromNow)->seconds(0);
    $end = $start->copy()->addHours($durationHours);

    return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
}

it('maakt een pending reservering via unit-QR flow', function () {
    Mail::fake();
    [, , $unit] = reservationFixture();
    [$start, $end] = reservationWindow();

    $reservation = app(CreateReservationAction::class)->handle(
        $unit,
        ReservationBookingData::fromValidated([
            'guest_first_name' => 'Ada',
            'guest_last_name' => 'Lovelace',
            'guest_email' => 'ada@example.com',
            'start_at' => $start,
            'end_at' => $end,
        ]),
    );

    expect($reservation->confirmed_at)->toBeNull()
        ->and($reservation->expires_at)->not->toBeNull()
        ->and($reservation->isPendingActive())->toBeTrue();
});

it('blokkeert overlappende reserveringen tijdens de hold', function () {
    Mail::fake();
    [, , $unit] = reservationFixture();
    [$start, $end] = reservationWindow();

    app(CreateReservationAction::class)->handle(
        $unit,
        ReservationBookingData::fromValidated([
            'guest_first_name' => 'Ada',
            'guest_last_name' => 'Lovelace',
            'guest_email' => 'ada@example.com',
            'start_at' => $start,
            'end_at' => $end,
        ]),
    );

    expect(fn () => app(CreateReservationAction::class)->handle(
        $unit,
        ReservationBookingData::fromValidated([
            'guest_first_name' => 'Grace',
            'guest_last_name' => 'Hopper',
            'guest_email' => 'grace@example.com',
            'start_at' => $start,
            'end_at' => $end,
        ]),
    ))->toThrow(ValidationException::class);
});

it('bevestigt via confirm-token en weigert verlopen holds', function () {
    Mail::fake();
    [, , $unit] = reservationFixture();
    [$start, $end] = reservationWindow();

    $reservation = app(CreateReservationAction::class)->handle(
        $unit,
        ReservationBookingData::fromValidated([
            'guest_first_name' => 'Ada',
            'guest_last_name' => 'Lovelace',
            'guest_email' => 'ada@example.com',
            'start_at' => $start,
            'end_at' => $end,
        ]),
    );

    $this->get(route('reservations.confirm', ['token' => $reservation->confirm_token]))
        ->assertOk()
        ->assertSee(__('reservations.public.confirm_ok'));

    expect($reservation->fresh()->isConfirmed())->toBeTrue();

    $expired = app(CreateReservationAction::class)->handle(
        $unit,
        ReservationBookingData::fromValidated([
            'guest_first_name' => 'Alan',
            'guest_last_name' => 'Turing',
            'guest_email' => 'alan@example.com',
            'start_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(1)->addHour()->format('Y-m-d H:i:s'),
        ]),
    );
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();

    expect(fn () => app(ConfirmReservationAction::class)->handle($expired->fresh()))
        ->toThrow(ValidationException::class);
});

it('laat staff een bevestigde reservering aanmaken en annuleren', function () {
    Mail::fake();
    [, $admin, $unit] = reservationFixture();
    [$start, $end] = reservationWindow();

    $reservation = app(CreateReservationAction::class)->handle(
        $unit,
        ReservationBookingData::fromValidated(
            [
                'guest_first_name' => 'Staff',
                'guest_last_name' => 'User',
                'guest_email' => 'staff@example.com',
                'start_at' => $start,
                'end_at' => $end,
            ],
            autoConfirm: true,
            createdByUserId: (int) $admin->id,
        ),
    );

    expect($reservation->isConfirmed())->toBeTrue();

    app(CancelReservationAction::class)->handle($reservation, (int) $admin->id);
    expect($reservation->fresh()->lifecycle()->value)->toBe('cancelled');

    $this->actingAs($admin)
        ->get(route('reservations.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('calendar.index', ['type' => 'reservations']))
        ->assertOk();
});

it('stuurt bevestigingsmail synchroon bij pending reservering', function () {
    Mail::fake();
    [, , $unit] = reservationFixture();
    [$start, $end] = reservationWindow();

    app(CreateReservationAction::class)->handle(
        $unit,
        ReservationBookingData::fromValidated([
            'guest_first_name' => 'Ada',
            'guest_last_name' => 'Lovelace',
            'guest_email' => 'ada@example.com',
            'start_at' => $start,
            'end_at' => $end,
        ]),
    );

    Mail::assertSent(\App\Mail\ReservationConfirmMail::class, function ($mail) {
        return $mail->hasTo('ada@example.com') && $mail->alreadyConfirmed === false;
    });
});

    Mail::fake();
    [, , $unit] = reservationFixture();
    [$start, $end] = reservationWindow();

    $reservation = app(CreateReservationAction::class)->handle(
        $unit,
        ReservationBookingData::fromValidated(
            [
                'guest_first_name' => 'Ada',
                'guest_last_name' => 'Lovelace',
                'guest_email' => 'ada@example.com',
                'start_at' => $start,
                'end_at' => $end,
            ],
            autoConfirm: true,
        ),
    );

    $this->get(route('reservations.manage', ['token' => $reservation->manage_token]))
        ->assertOk()
        ->assertSee(__('reservations.public.manage_title'));
});
