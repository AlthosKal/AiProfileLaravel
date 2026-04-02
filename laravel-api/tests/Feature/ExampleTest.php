<?php

test('application is up', function () {
    $response = $this->get('/up');

    $response->assertSuccessful();
});
