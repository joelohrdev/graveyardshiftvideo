<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('collection.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit their collection', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('collection.index'));
    $response->assertOk();
});
