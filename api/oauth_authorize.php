<?php
/** OAuth 2.0 Authorization Endpoint with PKCE (P4.1-M3). */
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
require_once __DIR__ . '/../core/mcp_oauth.php';
birkly_mcp_oauth_handle_authorize();
