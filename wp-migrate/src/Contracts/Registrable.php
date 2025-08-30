<?php
namespace WpMigrate\Contracts;

if ( ! defined( 'ABSPATH' ) ) { exit; }

interface Registrable {
    public function register(): void;
}
