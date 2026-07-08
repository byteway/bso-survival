<?php

namespace BSO\Survival\Core;

use BSO\Survival\Database\Migrator;
use BSO\Survival\Support\Capabilities;

class Activator {
    public static function activate(): void {
        Migrator::migrate();
        Capabilities::ensureRoleMappings();
    }
}
