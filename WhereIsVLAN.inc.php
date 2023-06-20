<style type="text/css">
form {margin: auto;width: 50%;}
.btn {margin-bottom: 5px;}
</style>
<?php
global $plugin_name;
$plugin_name="WhereIsVLAN";

# echo "Well done, the $GLOBALS[plugin_name] plugin is up and running<br>\n";

$exclude_untagged_checked="";
$exclude_tagged_checked="";
$vlan_to_search="";


if ($_POST){
	$do_query=0;
	if ($_POST["vlan_to_search"] ){
		$exclude_tagged="";
		$exclude_untagged="";
		if ($_POST["exclude_tagged"] && $_POST["exclude_tagged"]==1){
			$exclude_tagged_checked="checked";
			$exclude_tagged="ports_vlans.untagged!='0' AND ";
		}
		if ($_POST["exclude_untagged"] && $_POST["exclude_untagged"]==1){
			$exclude_untagged_checked="checked";
			$exclude_untagged="ports_vlans.untagged!='1' AND ";
		}
		if (is_numeric ($_POST["vlan_to_search"])){
			$do_query=1;
			$vlan_to_search=$_POST["vlan_to_search"];
			$vlan_to_search_statement="ports_vlans.vlan='".$_POST["vlan_to_search"]."'";
		}else{
			$vlan_to_search_statement="(";
			foreach(explode(",", $_POST["vlan_to_search"]) as $vlan){
				if (preg_match ( "/(\d+)-(\d+)/" , $vlan, $matches)){
					$first_vlan=$matches[1];
					$last_vlan=$matches[2];
					if ($last_vlan > $first_vlan){
						for ($counter=$first_vlan; $counter <= $last_vlan; $counter++){
							if (is_numeric ($counter)){
								$do_query=1;
								$vlan_to_search_statement.="ports_vlans.vlan='$counter' OR ";
							}else{
								echo "ERROR:'$counter' is not numeric!<br>\n";
								$vlan_to_search="";
								$vlan_to_search_statement="";
								$do_query=0;
							}
						}
					}else{
						for ($counter=$first_vlan; $counter >= $last_vlan; $counter--){
							if (is_numeric ($counter)){
								$do_query=1;
								$vlan_to_search_statement.="ports_vlans.vlan='$counter' OR ";
							}else{
								echo "ERROR:'$counter' is not numeric!<br>\n";
								$vlan_to_search="";
								$vlan_to_search_statement="";
								$do_query=0;
							}
						}
					}
				}else{
					if (is_numeric ($vlan)){
						$do_query=1;
						$vlan_to_search_statement.="ports_vlans.vlan='$vlan' OR ";
					}else{
						echo "ERROR:'$vlan' is not numeric!<br>\n";
						$vlan_to_search="";
						$vlan_to_search_statement="";
						$do_query=0;
					}
				}
			}
			$vlan_to_search_statement=substr($vlan_to_search_statement, 0, -4); # Remove last ' OR '
			$vlan_to_search_statement.=")";
			#print "DEBUG: \$vlan_to_search_statement=\"$vlan_to_search_statement\"<br>\n";
			$vlan_to_search=$_POST["vlan_to_search"];
			#echo "ERROR:'".$_POST["vlan_to_search"]."' is not numeric!<br>\n";
		}
	}
	if ($do_query){
		$query = "
select
	ports_vlans.device_id,
	ports_vlans.port_id,
	ports_vlans.vlan,
	ports_vlans.untagged,
	vlans.vlan_name,
	devices.sysName,
	devices.hostname,
	ports.ifName,
	ports.ifAlias,
	ports.ifSpeed,
	ports.ifDuplex,
	ports.ifOperStatus,
	ports.ifAdminStatus
from
	ports_vlans,
	vlans,
	devices,
	ports
where
	ports_vlans.device_id=devices.device_id AND
	ports_vlans.port_id=ports.port_id AND
	vlans.device_id=devices.device_id AND
	vlans.vlan_vlan=ports_vlans.vlan AND
	$exclude_tagged
	$exclude_untagged
	$vlan_to_search_statement
order by ports_vlans.device_id, ports.ifName
;
";
		$result=array();
		foreach( dbFetchRows($query) as $line){
			$device_id=$line['device_id'];
			$port_id=$line['port_id'];
			$vlan=$line['vlan'];
			$untagged=$line['untagged'];
			$vlan_name=$line['vlan_name'];
			$sysName=$line['sysName'];
			$hostname=$line['hostname'];
			$ifName=$line['ifName'];
			$ifAlias=$line['ifAlias'];
			$ifSpeed=$line['ifSpeed'];
			$ifDuplex=$line['ifDuplex'];
			$ifOperStatus=$line['ifOperStatus'];
			$ifAdminStatus=$line['ifAdminStatus'];

			$result[$device_id]['sysName']=$sysName;
			$result[$device_id]['hostname']=$hostname;
			$result[$device_id]['vlan'][$vlan]['vlan_name']=$vlan_name;
			$result[$device_id]['vlan'][$vlan]['ports'][$port_id]['untagged']=$untagged;
			$result[$device_id]['vlan'][$vlan]['ports'][$port_id]['ifName']=$ifName;
			$result[$device_id]['vlan'][$vlan]['ports'][$port_id]['ifAlias']=$ifAlias;
			$result[$device_id]['vlan'][$vlan]['ports'][$port_id]['ifSpeed']=$ifSpeed;
			$result[$device_id]['vlan'][$vlan]['ports'][$port_id]['ifDuplex']=$ifDuplex;
			$result[$device_id]['vlan'][$vlan]['ports'][$port_id]['ifOperStatus']=$ifOperStatus;
			$result[$device_id]['vlan'][$vlan]['ports'][$port_id]['ifAdminStatus']=$ifAdminStatus;
		}
	}
}

$form="<form action='/plugin/v1/$GLOBALS[plugin_name]' method='post'>";
$form.="Enter vlan id to search for:<input class=\"form-control\" type=\"textbox\" size=50 name=\"vlan_to_search\" value=\"$vlan_to_search\" ><br>\n";
$form.="Exclude access ports:<input type=\"checkbox\" name=\"exclude_untagged\" value=\"1\" $exclude_untagged_checked><br>\n";
$form.="Exclude trunk ports:<input type=\"checkbox\" name=\"exclude_tagged\" value=\"1\" $exclude_tagged_checked><br>\n";
$form.=csrf_field();
$form.='<input name="search" value="search" type="submit" class="btn btn-default"><br>';
print $form;

if ($result){
	print '<style type="text/css">
.tg  {border-collapse:collapse;border-spacing:0;}
.tg td{font-family:Arial, sans-serif;font-size:14px;padding:10px 5px;border-style:solid;border-width:1px;overflow:hidden;word-break:normal;border-color:black;}
.tg th{font-family:Arial, sans-serif;font-size:14px;font-weight:normal;padding:10px 5px;border-style:solid;border-width:1px;overflow:hidden;word-break:normal;border-color:black;}
.tg .tg-9hbo{background-color:#c0c0c0;font-weight:bold;vertical-align:top}
.tg .tg-q8xn{background-color:#dae8fc;vertical-align:top}
.tg .tg-head{background-color:#999999;vertical-align:top}
.tg .tg-yw4l{vertical-align:top}
</style>
';
	foreach($result as $device_id => $device_data){
		$sysName=$device_data['sysName'];
		$hostname=$device_data['hostname'];
		$n=0;
		$format="tg-head" ; # Set row format
		$table="<table class='tg'><tr><th colspan=7 class='tg-9hbo'>$sysName ($hostname) </th></tr>";
		$table.="<tr class=\"$format\"><td>vlan (name)<td>ifName, ifAlias<td>type<td>ifOperStatus<td>ifAdminStatus<td>ifSpeed<td>ifDuplex</tr>";
		foreach($device_data['vlan'] as $vlan => $vlan_data){
			$format= ( $n++ % 2 ) ? "tg-q8xn" : "tg-yw4l" ; # Set row format
			$vlan_name=$vlan_data['vlan_name'];
			$numinterfaces=count($vlan_data['ports']);
			$numinterfaces++;
			$table.="<tr class=\"$format\" ><td rowspan='$numinterfaces' >$vlan ($vlan_name)</td></tr>\n";
			foreach($vlan_data['ports'] as $port_id => $port_data){
				$ifName=$port_data['ifName'];
				$ifAlias=$port_data['ifAlias'];
				$untagged=$port_data['untagged'];
				$ifSpeed=$port_data['ifSpeed'];
				$ifSpeed=$ifSpeed/1000000;
				$ifSpeed.=" Mbit";
				$ifDuplex=$port_data['ifDuplex'];
				$ifOperStatus=$port_data['ifOperStatus'];
				$ifAdminStatus=$port_data['ifAdminStatus'];
				if ($untagged){
					$type="access port";
				}else{
					$type="trunk port";
				}
				$table.="<tr class=\"$format\"><td>$ifName, $ifAlias<td>$type<td>$ifOperStatus<td>$ifAdminStatus<td>$ifSpeed<td>$ifDuplex</tr>\n";
			}
		}
		$table.="</table><br>\n";
		#$table="$sysName $hostname<br>";
		print $table;

	}
	#print "DEBUG:<pre>";
	#var_dump($result);
	#print "</pre><br>\n";
}
