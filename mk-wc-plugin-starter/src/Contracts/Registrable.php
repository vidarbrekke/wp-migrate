<?php
namespace MK\WcPluginStarter\Contracts;

if ( ! defined( 'ABSPATH' ) ) { exit; }

interface Registrable {
    public function register(): void;
}
