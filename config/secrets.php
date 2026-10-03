<?php
// Server-only encryption key for at-rest secrets (currently: the shared LPG/SDMS
// portal credential in lpg_credentials). Never sent to the browser. Generated once
// with random_bytes(32); rotating it invalidates any previously-encrypted values.
$LPG_ENC_KEY = base64_decode('qmAO+ZcwiwCES3YS8yvA0QgJ6ItDG2xLv94kKkUM0mQ=');
