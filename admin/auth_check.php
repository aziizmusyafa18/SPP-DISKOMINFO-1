<?php
/**
 * Guard: include file ini di baris pertama halaman admin yang terproteksi.
 */
require_once __DIR__ . '/../includes/session.php';
require_login();
