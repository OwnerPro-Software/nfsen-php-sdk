<?php

use NFePHP\Common\Certificate;

function makeTestCertificate(): Certificate
{
    $pfxContent = file_get_contents(__DIR__ . '/fixtures/certs/fake.pfx');
    return Certificate::readPfx($pfxContent, 'secret');
}
