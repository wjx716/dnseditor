<?php

// Global configuration

// Do we log errors?
$cfg_log_errors = true;

// your primary nameserver
$cfg_primary_ns = "ns1.mydomain.com.";

// Database connection information - we need to abstract out to a DB class for things other then mysql
$cfg_db_name = "dns";
$cfg_db_host = "127.0.0.1";
$cfg_db_user = "root";
$cfg_db_pass = "";

// show the "Update Servers" button. You need to configure the dns_forbidden.update script for this to work
$cfg_updateservers = false;

// default zone options
// possible key's
// ALL: type (type of record, ie. A, NS, MX, TXT or CNAME)
// NS: nameserver
// MX: name, priority
// A: name, ip
// CNAME: name, target
// TXT: name, text
$cfg_newzone_defaults = array(
  array(
      "type" => "A"
    , "name" => "@"
    , "ip"   => "10.10.99.23"
  ),

  array(
      "type" => "A"
    , "name" => "www"
    , "ip"   => "10.10.99.23"
  ),

  array(
      "type" => "A"
    , "name" => "mail"
    , "ip"   => "10.10.99.101"
  ),

  array(
      "type" => "NS"
    , "nameserver" => "ns1.mydomain.com."
  ),

  array(
      "type" => "NS"
    , "nameserver" => "ns2.mydomain.com."
  ),

  array(
      "type" => "MX"
    , "name" => "mail"
  ),

  array(
      "type" => "TXT" 
    , "name" => "whois"
    , "text" => "We are mydomain.com"
  ),

  array(
      "type" => "CNAME"
    , "name" => "smtp"
    , "target" => "mail"
  ),

  array(
      "type" => "CNAME"
    , "name" => "pop"
    , "target" => "mail"
  )
);

?>
