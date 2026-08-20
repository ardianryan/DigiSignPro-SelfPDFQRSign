<?php

use App\Helpers\QrCodeHelper;

test('qrcode helper generates valid data uri without script injection', function () {
    $payload = 'https://sign.ppti.me/verify/DS-123456<script>alert(1)</script>';

    $dataUri = QrCodeHelper::toDataUri($payload);

    expect($dataUri)->toBeString();
    expect($dataUri)->toStartWith('data:image/png;base64,');
});
