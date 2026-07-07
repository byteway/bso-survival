<?php

namespace BSO\Survival\Core;

use BSO\Survival\Database\Migrator;

class Activator {
    public static function activate(): void {
        Migrator::migrate();
    }
}
