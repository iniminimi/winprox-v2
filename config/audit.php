<?php

return [

    'enabled' => filter_var(env('AUDIT_LOG_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

];
