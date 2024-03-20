<?php

namespace Tests\Feature\Checkouts;

use App\Events\CheckoutableCheckedOut;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Support\InteractsWithSettings;
use Tests\TestCase;

class AssetCheckoutTest extends TestCase
{
    use InteractsWithSettings;

    public function testCheckingOutAssetRequiresCorrectPermission()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('hardware.checkout.store', Asset::factory()->create()), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->create()->id,
            ])
            ->assertForbidden();
    }

    public function testNonExistentAssetCannotBeCheckedOut()
    {
        Event::fake([CheckoutableCheckedOut::class]);

        $this->actingAs(User::factory()->checkoutAssets()->create())
            ->post(route('hardware.checkout.store', 1000), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->create()->id,
                'name' => 'Changed Name',
                'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id,
                'checkout_at' => '2024-03-18',
                'expected_checkin' => '2024-03-28',
                'note' => 'An awesome note',
            ])
            ->assertSessionHas('error');

        Event::assertNotDispatched(CheckoutableCheckedOut::class);
    }

    public function testAssetNotAvailableForCheckoutCannotBeCheckedOut()
    {
        Event::fake([CheckoutableCheckedOut::class]);

        $asset = Asset::factory()->assignedToUser()->create();

        $this->actingAs(User::factory()->checkoutAssets()->create())
            ->post(route('hardware.checkout.store', $asset), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->create()->id,
                'name' => 'Changed Name',
                'status_id' => Statuslabel::factory()->readyToDeploy()->create()->id,
                'checkout_at' => '2024-03-18',
                'expected_checkin' => '2024-03-28',
                'note' => 'An awesome note',
            ])
            ->assertSessionHas('error');

        Event::assertNotDispatched(CheckoutableCheckedOut::class);
    }

    public function testValidationWhenCheckingOutAsset()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('hardware.checkout.store', Asset::factory()->create()), [
                //
            ])
            ->assertSessionHasErrors();
    }

    public function testAnAssetCanBeCheckedOutToAUser()
    {
        $this->markTestIncomplete();

        Event::fake([CheckoutableCheckedOut::class]);

        $originalStatus = Statuslabel::factory()->readyToDeploy()->create();
        $updatedStatus = Statuslabel::factory()->readyToDeploy()->create();
        $asset = Asset::factory()->create(['status_id' => $originalStatus->id]);
        $admin = User::factory()->checkoutAssets()->create();
        $userLocation = Location::factory()->create();

        $user = User::factory()->for($userLocation)->create();

        $this->actingAs($admin)
            ->post(route('hardware.checkout.store', $asset), [
                'checkout_to_type' => 'user',
                'assigned_user' => $user->id,
                'name' => 'Changed Name',
                'status_id' => $updatedStatus->id,
                'checkout_at' => '2024-03-18',
                'expected_checkin' => '2024-03-28',
                'note' => 'An awesome note',
            ]);

        // @todo: ensure asset updated
        $asset->refresh();
        $this->assertTrue($asset->location->is($userLocation));
        $this->assertEquals('2024-03-28 00:00:00', (string) $asset->expected_checkin);
        $this->assertTrue($asset->assetstatus->is($updatedStatus));
        
        Event::assertDispatched(function (CheckoutableCheckedOut $event) use ($admin, $asset, $user) {
            return $event->checkoutable->is($asset)
                && $event->checkedOutTo->is($user)
                && $event->checkedOutBy->is($admin)
                && $event->note === 'An awesome note';
        });

        // @todo: assert action log entry created
    }

    public function testLicenseSeatsAreAssignedToUserUponCheckout()
    {
        $this->markTestIncomplete();
    }

    public function testAnAssetCanBeCheckedOutToAnAsset()
    {
        $this->markTestIncomplete();
    }

    public function testAnAssetCanBeCheckedOutToALocation()
    {
        $this->markTestIncomplete();
    }

    public function testLastCheckoutUsesCurrentDateIfNotProvided()
    {
        $this->markTestIncomplete();
    }
}
