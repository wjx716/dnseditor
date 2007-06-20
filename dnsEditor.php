<?php
include 'config.php';

ini_set('log_errors', $cfg_log_errors);
$primary_ns = $cfg_primary_ns;

// Begin session
session_start();
// DB connection
mysql_select_db($cfg_db_name, mysql_connect($cfg_db_host, $cfg_db_user, $cfg_db_pass)) or die (mysql_error());

// This calls a shell script.  Eventually we may want to have it perform some other forms of updating
function updateServers() {
	$err = 0;
	$r = exec("./dns_update.forbidden",$r_array,$r_code);
	// create string to return to the page
	$errText = "";
	if ($r_code != 0) {
		$errText = "Error: " . mysql_error();
	}
	$r_text = implode("\r\n",$r_array);
	$newText = "Servers Updated:\r\n" . $r_text . '~~|~~' . $errText;
	return $newText;
}

function ToDBString($string, $isNumber=false) {
	if($isNumber) {
		if(preg_match("/^\d*[\.\,\']\d+|\d+[\.\,\']|\d+$/A", $string))
			return preg_replace( array("/^(\d+)[\.\,\']$/","/^(\d*)[\.\,\'](\d+)$/"),array("\1.","\1.\2"), $string);
		else
			die("~~|~~~~|~~String validation error: Not a number: \"".$string ."\"");
	} else {
		return "'".mysql_real_escape_string(trim(strtolower($string)))."'";
	}
}
// Main function to update table information
function updateSerial($rid) {
	$m_sql = "select zone from dns_records where rid = " . ToDBString($rid,true) . ";";
	$sql = @mysql_query($m_sql);
	$err = mysql_errno();
	if ($err != 0) {
		$errText .= "MySQL Error: " . mysql_error() . "\r\n" . $m_sql;
		return $errText;
	}
	$row = mysql_fetch_row($sql);
	$zone = $row[0];
	if ($zone) {
		$m_sql = "UPDATE dns_records SET serial = " . date("YmdU") . " WHERE zone='$zone' AND type='SOA';";
		$sql = @mysql_query($m_sql);
		$err = mysql_errno();
		if ($err != 0) {
			$errText .= "MySQL Error: " . mysql_error() . "\r\n" . $m_sql;
			return $errText;
		}
	} else {
			$errText .= "MySQL Error: " . mysql_error() . "\r\n" . $m_sql;
			return $errText;
	}
}

function changeText($sValue) {
	// decode submitted data
	$errText = "";
	$sValue_array = explode("~~|~~", $sValue);
	$sCell = explode("__", $sValue_array[1]);
	// strip bad stuff
	$parsedInput = htmlspecialchars($sValue_array[0], ENT_QUOTES);
	//update DB
	if ($sCell[0]) {     
		$sql = @mysql_query("UPDATE dns_records SET $sCell[1]= '$parsedInput' WHERE rid = ".ToDBString($sCell[0],true));
		$err = mysql_errno();
		$errText .= updateSerial($sCell[0]);
	}
	// create string to return to the page
	if ($err != 0) {
		$errText .= "MySQL Error: " . mysql_error();
	}
	$newText = '<div onclick="editCell(\''.$sValue_array[1].'\', this);">'.$parsedInput.'</div>~~|~~'.$sValue_array[1] . '~~|~~' . $errText;
	return $newText;
}
function changeZone($sValue) {
	// decode submitted data
	$sValue_array = explode("~~|~~", $sValue);
	$sZone = $sValue_array[0];
	// strip bad stuff
	// create string to return to the page
	$errText = "";
	if ($err != 0) {
		$errText = "MySQL Error: " . mysql_error();
	}
	$newText = getZone($sZone) . "~~|~~" . $zone . '~~|~~' . $errText;
	return $newText;
}
function addRecord($sValue) {
	$errText = "";
	$sValue_array = explode("~~|~~", $sValue);
	$zone = $sValue_array[0];
	$rtype = $sValue_array[1];
	$rtype = strtoupper($rtype);
	if (isValidType($rtype)) {
		switch ($rtype) {
			case "A":
				$sql_string = "INSERT INTO dns_records (zone,type) VALUES (".ToDBString($zone) . ",UCASE(" . ToDBString($rtype) . "));";
				if (zoneExists($zone)) {
					$sql = @mysql_query($sql_string);
					$err = mysql_errno();
				} else {
					$errText .= "Zone does not exist.  (zone: " . $zone . ")\r\n";
				}
				break;
			case "SOA":
				$sql_string = "INSERT INTO dns_records (zone,host,type,data,ttl,refresh,retry,expire,minimum,serial,resp_person) VALUES (".ToDBString($zone) . ",'@',UCASE(" . ToDBString($rtype) . "),'".$primary_ns."',86400,7200,3600,604800,3600,".date("YmdU").",'hostmaster');";
				if (!zoneExists($zone)) {
					$sql = @mysql_query($sql_string);
					$err = mysql_errno();
				} else {
					$errText .= "Zone already exists.  (zone: " . $zone . ")\r\n";
				}
				break;
			default:
				$sql_string = "INSERT INTO dns_records (zone,host,type) VALUES (".ToDBString($zone) . ",'@',UCASE(" . ToDBString($rtype) . "));";
				if (zoneExists($zone)) {
					$sql = @mysql_query($sql_string);
					$err = mysql_errno();
				} else {
					$errText .= "Zone does not exist.  (zone: " . $zone . ")\r\n";
				}
				break;
		}
	} else {
		$errText .= "Invalid record type specified.  (type: " . $rtype . ")\r\n";
	}
	// create string to return to the page
	if ($err != 0) {
		$errText .= "MySQL Error: " . mysql_error() . "\r\n";
	}
	$typehtml = "";
	switch ($rtype) {
		case "NS":
			$typehtml = getNSRecords($zone);
			break;
		case "MX":
			$typehtml = getMXRecords($zone);
			break;
		case "A":
			$typehtml = getARecords($zone);
			break;
		case "SOA":
			$typehtml = getSOARecord($zone);
			break;
		case 'CNAME':
			$typehtml = getCNAMERecords($zone);
			break;
		case 'TXT':
			$typehtml = getTXTRecords($zone);
			break;
	}
	$newtext = $rtype . '~~|~~' . $typehtml . '~~|~~' . $errText;
	return $newtext;
}
function delZone($sValue) {
	$errText = "";
	$sValue_array = explode("~~|~~", $sValue);
	$zone = $sValue_array[0];
	if (zoneExists($zone)) {
		$sql = @mysql_query("delete from dns_records where zone=".ToDBString($zone).";");
		$err = mysql_errno();
	} else {
		$errText .= "Cannot delete zone: Zone does not exist.  (zone: ".$zone.")\r\n";
	}
	// create string to return to the page
	if ($err != 0) {
		$errText .= "MySQL Error: " . mysql_error() . "\r\n";
	}
	$typehtml = "";
	$newtext = '~~|~~~~|~~' . $errText;
	return $newtext;
	
}
function delRecord($sValue) {
	$errText = "";
	$sValue_array = explode("~~|~~", $sValue);
	$zone = $sValue_array[0];
	$rtype = $sValue_array[1];
	$rtype = strtoupper($rtype);
	$rid = $sValue_array[2];
	if (ridExists($rid)) {
		$sql = @mysql_query("delete from dns_records where rid=".ToDBString($rid,true).";");
		$err = mysql_errno();
	} else {
		$errText .= "Record id does not exist.  (rid: ".$rid.")\r\n";
	}
	// create string to return to the page
	if ($err != 0) {
		$errText .= "MySQL Error: " . mysql_error() . "\r\n";
	}
	$typehtml = "";
	switch ($rtype) {
		case "NS":
			$typehtml = getNSRecords($zone);
			break;
		case "MX":
			$typehtml = getMXRecords($zone);
			break;
		case "A":
			$typehtml = getARecords($zone);
			break;
		case "SOA":
			$typehtml = getSOARecord($zone);
			break;
		case 'CNAME':
			$typehtml = getCNAMERecords($zone);
			break;
		case 'TXT':
			$typehtml = getTXTRecords($zone);
			break;
	}
	$newtext = $rtype . '~~|~~' . $typehtml . '~~|~~' . $errText;
	return $newtext;
}

function zoneExists($zone) {
	$sql = @mysql_query("select rid from dns_records where type='SOA' and zone=".ToDBString($zone).";");
	$err = mysql_errno();
	$rec = mysql_num_rows($sql);
	return $rec > 0;
}

function isValidType($rtype) { 
	switch ($rtype) {
		case "NS":
		case "MX":
		case "SOA":
		case "A":
		case "TXT":
		case 'CNAME':
			return 1;
			break;
		default:
			return 0;
			break;
	}
}

function ridExists($rid) { 
	$sql = @mysql_query("select rid from dns_records where rid=".ToDBString($rid,true).";");
	$err = mysql_errno();
	$rec = mysql_num_rows($sql);
	return $rec > 0;
}

function getZone($zone) {
		if (zoneExists($zone)) {
			$html .= "<table class=\"editable_table\" border=\"0\">\n";
			$html .= "<tr class=\"yellow\"><th>Edit Zone [". $zone . "]</th></tr>\n";
			$html .= "</table>\n";
			$html .= "<div id=\"div_soa_records\">" . getSOARecord($zone) . "</div>";
			$html .= "<div id=\"div_ns_records\">" . getNSRecords($zone) . "</div>";
			$html .= "<div id=\"div_mx_records\">" . getMXRecords($zone) . "</div>";
			$html .= "<div id=\"div_a_records\">" . getARecords($zone) . "</div>";
			$html .= '<div id="div_cname_records">' . getCNAMERecords($zone) . '</div>';
			$html .= '<div id="div_txt_records">' . getTXTRecords($zone) . '</div>';
		} else {
			$html .= "<h2>Zone [$zone] does not exist.</h2>";	
		}
		if (mysql_error()) {
			$html .= "<div id=\"mysql_error\">MySql Error:<br>" . mysql_error() . "</div>";
		}
		return $html;
}
// functions for each record type
function getSOARecord($zone) {
	// the TR string
	$table = "";
	// query DB
	$sql = @mysql_query("SELECT rid,host,data,resp_person,refresh,retry,expire,minimum FROM dns_records WHERE type='SOA' AND zone=".ToDBString($zone)."");
	// build table
	$table .= "<table id=\"tbl_soa_records\" class=\"editable_table\">";
	$table .= "<tr class=\"yellow\"><td class=\"row_head\"><strong>SOA</strong></td><td>MNAME</td><td>RNAME</td><td>Refresh</td><td>Retry</td><td>Expire</td><td>Minimum</td></tr>\n";
	while($row = mysql_fetch_array($sql)){
		stripslashes(extract($row));
		$table .= "<tr><td class=\"row_head\" align=\"right\"><!-- <span class=\"del\" onclick=\"delRecord('$zone', 'SOA', $rid);\">-</span>//--></td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__data\" onmouseover=\"bgSwitch('on', this, 'Modify Zone Primary Master Server' );\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .= "	<div onclick=\"editCell('".$rid."__data', this);\">".$data."</div>\n";
		$table .= "</td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__resp_person\" onmouseover=\"bgSwitch('on', this, 'Modify Zone Responsible Person');\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .= "	<div onclick=\"editCell('".$rid."__resp_person', this);\">".$resp_person."</div>\n";
		$table .= "</td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__refresh\" onmouseover=\"bgSwitch('on', this, 'Refresh determines the number of seconds between a successful check on the serial number on the zone of the primary, and the next attempt. Usually around 2-24 hours. Not used by a primary server.');\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .= "	<div onclick=\"editCell('".$rid."__refresh', this);\">".$refresh."</div>\n";
		$table .= "</td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__retry\" onmouseover=\"bgSwitch('on', this, 'If a refresh attempt fails, a server will retry after this many seconds. Not used by a primary server.');\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .= "	<div onclick=\"editCell('".$rid."__retry', this);\">".$retry."</div>\n";
		$table .= "</td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__expire\" onmouseover=\"bgSwitch('on', this, 'Measured in seconds. If the refresh and retry attempts fail after that many seconds the server will stop serving the zone. Typical value is 1 week. Not used by a primary server.');\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .= "	<div onclick=\"editCell('".$rid."__expire', this);\">".$expire."</div>\n";
		$table .= "</td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__minimum\" onmouseover=\"bgSwitch('on', this, 'The default TTL for every record in the zone. Can be overridden for any particular record. Typical values range from eight hours to four days. When changes are being made to a zone, often set at ten minutes or less.');\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .= "	<div onclick=\"editCell('".$rid."__minimum', this);\">".$minimum."</div>\n";
		$table .= "</td>\n";
		$table .= "</tr>\n";
	}
	$table .= "</table>\n";
	return $table;
}
function getARecords($zone) {
	// the TR string
	$table = "";
	// query DB
	$sql = @mysql_query("SELECT rid,host,data FROM dns_records WHERE type='A' AND zone=".ToDBString($zone)." ORDER BY host");
	// build table
	$table .= "<table id=\"tbl_a_records\" class=\"editable_table\">";
	$table .= "<tr class=\"yellow\">\n";
	$table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'A');\" onmouseover=\"bgSwitch('on', this, 'Add A Record');\" onmouseout=\"bgSwitch('off', this, '');\"\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> A</strong></td>\n";
	$table .= "<td>Name</td>\n";
	$table .= "<td>IP Address</td>\n";
	$table .= "</tr>\n";
	while($row = mysql_fetch_array($sql)){
		stripslashes(extract($row));
		$table .=	"<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'A', $rid);\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__host\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record Host');\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .= "<div onclick=\"editCell('".$rid."__host', this);\">".$host."</div>\n";
		$table .= "</td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__data\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record IP');\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .=	"<div onclick=\"editCell('".$rid."__data', this);\">".$data."</div>\n";
		$table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}
function getCNAMERecords($zone) {
	// the TR string
	$table = "";
	// query DB
	$sql = @mysql_query("SELECT rid,host,data FROM dns_records WHERE type='CNAME' AND zone=".ToDBString($zone)." ORDER BY host");
	// build table
	$table .= "<table id=\"tbl_cname_records\" class=\"editable_table\">";
	$table .= "<tr class=\"yellow\">\n";
	$table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'CNAME');\" onmouseover=\"bgSwitch('on', this, 'Add CNAME Record');\" onmouseout=\"bgSwitch('off', this, '');\"\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> CNAME</strong></td>\n";
	$table .= "<td>Name</td>\n";
	$table .= "<td>Canonical Name</td>\n";
	$table .= "</tr>\n";
	while($row = mysql_fetch_array($sql)){
		stripslashes(extract($row));
		$table .=	"<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'A', $rid);\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__host\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record Host');\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .= "<div onclick=\"editCell('".$rid."__host', this);\">".$host."</div>\n";
		$table .= "</td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__data\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record IP');\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .=	"<div onclick=\"editCell('".$rid."__data', this);\">".$data."</div>\n";
		$table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}
function getTXTRecords($zone) {
	// the TR string
	$table = "";
	// query DB
	$sql = @mysql_query("SELECT rid,host,data FROM dns_records WHERE type='TXT' AND zone=".ToDBString($zone)." ORDER BY host");
	// build table
	$table .= "<table id=\"tbl_txt_records\" class=\"editable_table\">";
	$table .= "<tr class=\"yellow\">\n";
	$table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'TXT');\" onmouseover=\"bgSwitch('on', this, 'Add TXT Record');\" onmouseout=\"bgSwitch('off', this, '');\"\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> TXT</strong></td>\n";
	$table .= "<td>Name</td>\n";
	$table .= "<td>Text</td>\n";
	$table .= "</tr>\n";
	while($row = mysql_fetch_array($sql)){
		stripslashes(extract($row));
		$table .=	"<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'A', $rid);\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__host\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record Host');\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .= "<div onclick=\"editCell('".$rid."__host', this);\">".$host."</div>\n";
		$table .= "</td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__data\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record IP');\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .=	"<div onclick=\"editCell('".$rid."__data', this);\">".$data."</div>\n";
		$table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}

function getMXRecords($zone) {
	// the TR string
	$table = "";
	// query DB
	$sql = @mysql_query("SELECT rid,mx_priority,data FROM dns_records WHERE type='MX' AND zone=".ToDBString($zone)." ORDER BY mx_priority");
	// build table
	$table .= "<table id=\"tbl_mx_records\" class=\"editable_table\">";
	$table .= "<tr class=\"yellow\">\n";
	$table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'MX');\" onmouseover=\"bgSwitch('on', this, 'Add MX Record');\" onmouseout=\"bgSwitch('off', this, '');\"\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> MX</strong></td>\n";
	$table .= "<td>Name</td>\n";
	$table .= "<td>Priority</td>\n";
	$table .= "</tr>\n";
	while($row = mysql_fetch_array($sql)){
		stripslashes(extract($row));
		$table .=	"<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'MX', $rid);\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__data\" onmouseover=\"bgSwitch('on', this, 'Modify Mail Exchange Name');\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .=	"<div onclick=\"editCell('".$rid."__data', this);\">".$data."</div>\n";
		$table .= "</td>\n";
		$table .= "<td class=\"point_small\" id=\"".$rid."__mx_priority\" onmouseover=\"bgSwitch('on', this, 'Modify Mail Exchange Priority');\" onmouseout=\"bgSwitch('off', this);\">\n";
		$table .= "<div onclick=\"editCell('".$rid."__mx_priority', this);\">".$mx_priority."</div>\n";
		$table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}
function getNSRecords($zone) {
	// the TR string
	$table = "";
	// query DB
	$sql = @mysql_query("SELECT rid,data FROM dns_records WHERE type='NS' AND zone=".ToDBString($zone)." order by DATA");
	// build table
	$table .= "<table id=\"tbl_nsrecords\" class=\"editable_table\">";
	$table .= "<tr class=\"yellow\">\n";
	$table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'NS');\"  onmouseover=\"bgSwitch('on', this, 'Add Nameserver Record');\" onmouseout=\"bgSwitch('off', this, '');\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> NS</strong></td>\n";
	$table .= "<td>Nameserver</td>\n";
	$table .= "</tr>\n";
	while($row = mysql_fetch_array($sql)){
		stripslashes(extract($row));
		$table .=	"<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'NS', $rid);\"  onmouseover=\"bgSwitch('on', this, 'Delete Nameserver Record');\" onmouseout=\"bgSwitch('off', this, '');\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__data\" onmouseover=\"bgSwitch('on', this, 'Modify Nameserver Record');\" onmouseout=\"bgSwitch('off', this, '');\">\n";
		$table .=	"<div onclick=\"editCell('".$rid."__data', this);\">".$data."</div>\n";
		$table .= "</td>\n</tr>\n";
	}
	$table .= "</table>\n";
	return $table;
}
function getZoneList() {
	// the TR string
	$table = "";
	// query DB
	$sql = mysql_query("SELECT DISTINCT zone,rid FROM dns_records WHERE type='SOA' order by zone asc");
	if(!$sql)
		echo mysql_error();
	// build table
	while($row = mysql_fetch_array($sql)){
		stripslashes(extract($row));
		$table .=	"<tr><td class=\"point_del\"><span class=\"del\" onclick=\"javascript:delZone('".$zone."');\" onmouseover=\"bgSwitch('on', this, 'Delete Zone: ".$zone."');\" onmouseout=\"bgSwitch('off', this);\"><img src=\"delete.png\" border=\"0\" alt=\"Delete Zone\" /></span></td>\n";
		$table .= "<td class=\"point\" id=\"".$rid."__zone\" onmouseover=\"bgSwitch('on', this, 'Edit Zone: ".$zone."');\" onmouseout=\"bgSwitch('off', this, '');\">\n";
		$table .=	"<div onclick=\"editZone('".$zone."');\">".$zone."</div>\n";
		$table .= "</td>\n</tr>\n";
    }
    return $table;
	
}
// sajax
require("sajax.php");
$sajax_request_type = "POST";
sajax_init();
//$sajax_debug_mode = 1;
sajax_export("changeText");
sajax_export("changeZone");
sajax_export("addRecord");
sajax_export("delRecord");
sajax_export("updateServers");
sajax_export("getZoneList");
sajax_export("delZone");
sajax_handle_client_request();

?>
<html>
	<head>
		<title>dnsEditor: Ajaxified</title>
		
		<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
		<meta http-equiv="Content-Language" content="en-us" />
		<meta name="ROBOTS" content="ALL" />
		<meta http-equiv="imagetoolbar" content="no" />
		<meta name="MSSmartTagsPreventParsing" content="true" />
		<meta name="Copyright" content="(c) 2005 Copyright content & design: Lokkju, Inc" />
		<meta name="Keywords" content="dns ajax edit bind" />
		<meta name="Description" content="Ajax based edit in place DNS Record configuration system" />
		<!-- (c) Copyright 2005 by Lokkju, Inc All Rights Reserved. -->
		
		<link href="dnsEditor.css" rel="stylesheet" type="text/css" media="all" />
		
 		<!-- KLUDGE:: Win IE 5 -->
 		<!-- corrects the unsightly Flash of Unstyled Content. See http://www.bluerobot.com/web/css/fouc.asp for more info -->
 		<script type="text/javascript"></script>
 		<!-- END KLUDGE:: -->
		<script src="filtertable.js" type="text/javascript" language="javascript" charset="utf-8"></script>
		<script type="text/javascript">
		<?
			sajax_show_javascript();
		?>
		function trim(str) {
			str = " " + str + " ";
			return str.replace(/^\s+/g, '').replace(/\s+$/g, '');
		}
  		function textChanger_cb(result) {
			var result_array=result.split("~~|~~");
			if (result_array[2]) {
				alert(result_array[2]);
			}
			document.getElementById(result_array[1]).innerHTML = result_array[0];
			Fat.fade_element(result_array[1], 30, 1500, "#EEFCC5", "#FFFFFF")
		}
		function zoneChanger_cb(result) {
			var result_array=result.split("~~|~~");
			if (result_array[2]) {
				alert(result_array[2]);
			}
			document.getElementById('zoneinfo').innerHTML = result_array[0];
		}
		function getZoneList_cb(result) {
			var result_array=result.split("~~|~~");
			if (result_array[2]) {
				alert(result_array[2]);
			}
			document.getElementById('zonemenu_filterable').innerHTML = result_array[0];
		}
		
		function addRecord_cb(result) {
			var result_array=result.split("~~|~~");
			if (result_array[2]) {
				alert(result_array[2]);
			}
			switch(result_array[0]) {
				case "NS":
					document.getElementById('div_ns_records').innerHTML = result_array[1];
					break;
				case "MX":
					document.getElementById('div_mx_records').innerHTML = result_array[1];
					break;
				case "A":
					document.getElementById('div_a_records').innerHTML = result_array[1];
					break;
				case 'CNAME':
					document.getElementById('div_cname_records').innerHTML = result_array[1];
					break;
				case 'TXT':
					document.getElementById('div_txt_records').innerHTML = result_array[1];
					break;
				case 'SOA':
					refreshZones();
					break;
			}
		}
		function delZone_cb(result) {
			var result_array=result.split("~~|~~");
			if (result_array[2]) {
				alert(result_array[2]);
			}
			refreshZones();
		}
		function delRecord_cb(result) {
			var result_array=result.split("~~|~~");
			if (result_array[2]) {
				alert(result_array[2]);
			}
			switch(result_array[0]) {
				case "NS":
					document.getElementById('div_ns_records').innerHTML = result_array[1];
					break;
				case "MX":
					document.getElementById('div_mx_records').innerHTML = result_array[1];
					break;
				case "A":
					document.getElementById('div_a_records').innerHTML = result_array[1];
					break;
				case 'CNAME':
					document.getElementById('div_cname_records').innerHTML = result_array[1];
					break;
				case 'TXT':
					document.getElementById('div_txt_records').innerHTML = result_array[1];
					break;
				case "SOA":
					refreshZones();
					break;
			}
		}
		function updateServers_cb(result) {
			var result_array=result.split("~~|~~");
			if (result_array[1]) {
				alert(result_array[1]);
			}
			alert(result_array[0]);
			document.getElementById('btn_updateServers').value='Update Servers';
		}
		
		function parseForm(cellID, inputID) {
			var temp = trim(document.getElementById(inputID).value);
			var obj = /^(\s*)([\W\w]*)(\b\s*$)/;
			if (obj.test(temp)) { temp = temp.replace(obj, '$2'); }
			var obj = /  /g;
			while (temp.match(obj)) { temp = temp.replace(obj, " "); }
			if (temp == " ") { temp = ""; }
			if (! temp) {alert("This field must contain at least one non-whitespace character.");return;}
			var st = trim(document.getElementById(inputID).value) + '~~|~~' + cellID;
			document.getElementById(cellID).innerHTML = "<div class=\"update\">Updating...</div>";
			x_changeText(st, textChanger_cb);
			document.getElementById(cellID).style.border = 'none';
		}
		function editCell(id, cellSpan) {
			var inputWidth = (document.getElementById(id).offsetWidth / 7);
			var oldCellSpan = trim(cellSpan.innerHTML);
			document.getElementById(id).innerHTML = "<div id=\"" + id + "span\"><form name=\"activeForm\" onsubmit=\"parseForm('"+id+"', '"+id+"input');return false;\" style=\"margin:0;\" action=\"\"><input type=\"text\" class=\"dynaInput\" id=\""+id+"input\" size=\""+ inputWidth + "\" onblur=\"parseForm('"+id+"', '"+id+"input');return false;\"><br /><noscript><input value=\"OK\" type=\"submit\"></noscript></form></div>";
			document.getElementById(id+"input").value = oldCellSpan;
			document.getElementById(id).style.background = '#ffc';
			document.getElementById(id+"span").style.border = '1px solid #fc0';
			document.getElementById(id+"input").focus(); // for some reason, two focus calls are needed - no idea why?  perhaps one to render, and the other to focus?
			document.getElementById(id+"input").focus();			
		}
		function editZone(zone) {
			x_changeZone(trim(zone), zoneChanger_cb);
			document.getElementById('zoneinfo').innerHTML = "<div id='zone_edit_msg'><strong>Retrieving Zone [" + zone + "]...</strong></div>";
		}
		function addRecord(zone,rtype) {
			var st = trim(zone) + "~~|~~" + trim(rtype);
			x_addRecord(st,addRecord_cb);
		}
		function addZone() {
			var zone = prompt("Please enter the domain name to add:","");
			if ((zone==null)||(zone.length==0)) {
				alert("Invalid Zone");
				return false;
			}
			addRecord(zone,"SOA");
		}
		function delZone(zone) {
			var answer = confirm ("You are about to delete all records for " + zone + "\r\nAre your sure you want to delete ALL entries for this zone?")
			if (answer) {
				var st = trim(zone);
				x_delZone(st,delZone_cb);
			}
		}
		function refreshZones() {
			document.getElementById('zonemenu_filterable').innerHTML = "<tr><td>Updating...</td></tr>";
			document.getElementById('zoneinfo').innerHTML = "<div id='zone_edit_msg'><strong>Select a zone.</strong></div>";
			x_getZoneList("",getZoneList_cb);
		}
		function delRecord(zone,rtype,rid) {
			var answer = confirm ("Are your sure you want to delete this record?")
			if (answer) {
				var st = trim(zone) + "~~|~~" + trim(rtype) + '~~|~~' + trim(rid);
				x_delRecord(st,delRecord_cb);
			}
		}
		function bgSwitch(ac, td, st) {
			if (ac == 'on'){
				if (td.tagName == "TD") td.style.background = '#ffc';
				if (st) mys(st);
			} else if (ac == 'off'){
				if (td.tagName == "TD") td.style.background = '#ffffff';
				mys('');
			}
		}
		function mys(s) {
			if (s==null || s==''){
				document.getElementById("status_div_over").innerHTML = "";
			} else {
				document.getElementById("status_div_over").innerHTML = s;
			}	
		}
		</script>
		<script type="text/javascript" src="fat.js"></script>
		<style type="text/css">
		
		</style>
         <!-- <base href="" /> Breaks in IE -->
		
	</head>
	
	<body id="thebody" onload="">
		<div id="zonelist">
			<div id="zonelist_top">
				<table class="" border="0" width="100%">
					<tr>
						<th colspan="1" align="left">Zone List&nbsp;&nbsp;<a href="javascript:refreshZones();"><img src="refresh.png" border=0 alt="refresh" onmouseover="bgSwitch('on', this, 'Refresh Zone List');" onmouseout="bgSwitch('off', this, '');"/></a></th>
						<td align="right"><input type="button" class="cmdBtn" value="Add Zone" onClick="javascript:addZone();" onmouseover="bgSwitch('on', this, 'Add a new Zone');" onmouseout="bgSwitch('off', this, '');"/><br><input class="cmdBtn" type="button" value="Update Servers" name="btn_updateServers" id="btn_updateServers" onclick="javascript:this.value='Updating...';x_updateServers('',updateServers_cb);" onmouseover="bgSwitch('on', this, 'Syncronize all DNS Servers');" onmouseout="bgSwitch('off', this, '');" /></td>
					</tr>
				</table>
			</div>
			<div id="zonelist_filter_div"  onmouseover="bgSwitch('on', this, 'Filter zones based on text string');" onmouseout="bgSwitch('off', this, '');">
				Filter: <input name="zonemenu_filterable_filter" id="zonemenu_filterable_filter" type="text" value="" size="10" maxlength="10"/>
			</div>
			<div id="zonelist_menu">
				<table class="zonemenu_filterable" id="zonemenu_filterable" border="0">
					<?php echo getZoneList(); ?>
				</table>
			</div>
			<div id="status_div">
				<div id="status_div_over">
				&nbsp;
				</div>
				<div id="status_div_ajax">
				</div>
			</div>
		</div>
		<div id="zoneinfo">
			<div id="zone_edit_msg"><strong>Select a zone.</strong></div>
		</div>
	</body>
</html>
