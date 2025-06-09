<style type="text/css">
    form { margin: auto; width: 50%; }
    .btn { margin-bottom: 5px; }
</style>

<?php
global $plugin_name;
$plugin_name = "WhereIsVLAN";

$exclude_untagged_checked    = "";
$exclude_tagged_checked      = "";
$exclude_oper_down_checked   = "";
$exclude_admin_down_checked  = "";
$vlan_to_search              = "";
$exclude_untagged            = "";
$exclude_tagged              = "";
$exclude_oper_down           = "";
$exclude_admin_down          = "";

if ($_POST) {
    $do_query = 0;

    // Exclude trunk ports (tagged)
    if (isset($_POST["exclude_tagged"]) && $_POST["exclude_tagged"] == 1) {
        $exclude_tagged_checked = "checked";
        $exclude_tagged = "ports_vlans.untagged != '0' AND ";
    }

    // Exclude access ports (untagged)
    if (isset($_POST["exclude_untagged"]) && $_POST["exclude_untagged"] == 1) {
        $exclude_untagged_checked = "checked";
        $exclude_untagged = "ports_vlans.untagged != '1' AND ";
    }

    // Exclude interfaces that are operationally down
    if (isset($_POST["exclude_oper_down"]) && $_POST["exclude_oper_down"] == 1) {
        $exclude_oper_down_checked = "checked";
        $exclude_oper_down = "ports.ifOperStatus = 'up' AND ";
    }

    // Exclude interfaces that are administratively down
    if (isset($_POST["exclude_admin_down"]) && $_POST["exclude_admin_down"] == 1) {
        $exclude_admin_down_checked = "checked";
        $exclude_admin_down = "ports.ifAdminStatus = 'up' AND ";
    }

    // VLAN input handling (single or comma/-dash ranges)
    if (is_numeric($_POST["vlan_to_search"])) {
        $do_query = 1;
        $vlan_to_search = $_POST["vlan_to_search"];
        $vlan_to_search_statement = "ports_vlans.vlan = '$_POST[vlan_to_search]'";
    } else {
        $vlan_to_search_statement = "(";
        foreach (explode(",", $_POST["vlan_to_search"]) as $vlan) {
            if (preg_match("/(\d+)-(\d+)/", $vlan, $matches)) {
                $first_vlan = $matches[1];
                $last_vlan  = $matches[2];

                if ($last_vlan > $first_vlan) {
                    for ($counter = $first_vlan; $counter <= $last_vlan; $counter++) {
                        if (is_numeric($counter)) {
                            $do_query = 1;
                            $vlan_to_search_statement .= "ports_vlans.vlan = '$counter' OR ";
                        } else {
                            echo "ERROR: '$counter' is not numeric!<br>\n";
                            $vlan_to_search = "";
                            $vlan_to_search_statement = "";
                            $do_query = 0;
                        }
                    }
                } else {
                    for ($counter = $first_vlan; $counter >= $last_vlan; $counter--) {
                        if (is_numeric($counter)) {
                            $do_query = 1;
                            $vlan_to_search_statement .= "ports_vlans.vlan = '$counter' OR ";
                        } else {
                            echo "ERROR: '$counter' is not numeric!<br>\n";
                            $vlan_to_search = "";
                            $vlan_to_search_statement = "";
                            $do_query = 0;
                        }
                    }
                }
            } else {
                if (is_numeric($vlan)) {
                    $do_query = 1;
                    $vlan_to_search_statement .= "ports_vlans.vlan = '$vlan' OR ";
                } else {
                    echo "ERROR: '$vlan' is not numeric!<br>\n";
                    $vlan_to_search = "";
                    $vlan_to_search_statement = "";
                    $do_query = 0;
                }
            }
        }
        // Trim trailing “ OR ” and close parenthesis
        $vlan_to_search_statement = substr($vlan_to_search_statement, 0, -4) . ")";
    }

    if ($do_query) {
        $query = "
            SELECT
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
            FROM
                ports_vlans,
                vlans,
                devices,
                ports
            WHERE
                ports_vlans.device_id   = devices.device_id AND
                ports_vlans.port_id     = ports.port_id AND
                vlans.device_id         = devices.device_id AND
                vlans.vlan_vlan         = ports_vlans.vlan AND
                $exclude_tagged
                $exclude_untagged
                $exclude_oper_down
                $exclude_admin_down
                $vlan_to_search_statement
            ORDER BY
                ports_vlans.device_id, ports.ifName
        ";

        $result = [];
        foreach (dbFetchRows($query) as $line) {
            $device_id       = $line['device_id'];
            $port_id         = $line['port_id'];
            $vlan            = $line['vlan'];
            $untagged        = $line['untagged'];
            $vlan_name       = $line['vlan_name'];
            $sysName         = $line['sysName'];
            $hostname        = $line['hostname'];
            $ifName          = $line['ifName'];
            $ifAlias         = $line['ifAlias'];
            $ifSpeed         = $line['ifSpeed'] / 1000000 . " Mbit";
            $ifDuplex        = $line['ifDuplex'];
            $ifOperStatus    = $line['ifOperStatus'];
            $ifAdminStatus   = $line['ifAdminStatus'];

            $result[$device_id]['sysName']                     = $sysName;
            $result[$device_id]['hostname']                    = $hostname;
            $result[$device_id]['vlan'][$vlan]['vlan_name']    = $vlan_name;
            $result[$device_id]['vlan'][$vlan]['ports'][$port_id] = [
                'untagged'      => $untagged,
                'ifName'        => $ifName,
                'ifAlias'       => $ifAlias,
                'ifSpeed'       => $ifSpeed,
                'ifDuplex'      => $ifDuplex,
                'ifOperStatus'  => $ifOperStatus,
                'ifAdminStatus' => $ifAdminStatus,
            ];
        }
    }
}
?>

<?php
// Build form
$form  = "<form action='/plugin/v1/$GLOBALS[plugin_name]' method='post'>";
$form .= "Enter VLAN ID to search for: <input class='form-control' size='50' name='vlan_to_search' value='$vlan_to_search'><br>\n";
$form .= "Exclude access ports: <input type='checkbox' name='exclude_untagged'  value='1' $exclude_untagged_checked><br>\n";
$form .= "Exclude trunk ports:  <input type='checkbox' name='exclude_tagged'    value='1' $exclude_tagged_checked><br>\n";
$form .= "Exclude interfaces that are operationally down:   <input type='checkbox' name='exclude_oper_down'  value='1' $exclude_oper_down_checked><br>\n";
$form .= "Exclude interfaces that are administratively down: <input type='checkbox' name='exclude_admin_down' value='1' $exclude_admin_down_checked><br>\n";
$form .= csrf_field();
$form .= '<input name="search" value="Search" type="submit" class="btn btn-default"><br>';
$form .= "</form>";
print $form;

// If query returned results, render table(s)
if (isset($result) && $result) {
    print "
    <div class=\"panel panel-default\">
      <div class=\"panel-heading\"><strong>WhereIsVLAN Results</strong></div>
      <div class=\"panel-body\">
    ";

    foreach ($result as $device_id => $device_data) {
        $sysName  = $device_data['sysName'];
        $hostname = $device_data['hostname'];
        $n        = 0;
        $format   = "tg-head";
        $device_url = "/device/device=$device_id/";

        // Table header
        $table  = "<table class='tg'>
                     <tr>
                       <th colspan='7' class='tg-0pky'>
                         <a href='$device_url'>$sysName</a> ($hostname)
                       </th>
                     </tr>";
        $table .= "<tr class='$format'>
                     <td>VLAN (Name)</td>
                     <td>ifName</td>
                     <td>ifAlias</td>
                     <td>ifOperStatus</td>
                     <td>ifAdminStatus</td>
                     <td>ifSpeed</td>
                     <td>ifDuplex</td>
                   </tr>";

        // Table rows per VLAN/port
        foreach ($device_data['vlan'] as $vlan => $vlan_data) {
            foreach ($vlan_data['ports'] as $port_id => $port_data) {
                $format = ($n++ % 2) ? "tg-q8xn" : "tg-yw4l";

                $vlan_name      = $vlan_data['vlan_name'];
                $untagged       = $port_data['untagged'];
                $ifName         = $port_data['ifName'];
                $ifAlias        = $port_data['ifAlias'];
                $ifSpeed        = $port_data['ifSpeed'];
                $ifDuplex       = $port_data['ifDuplex'];
                $ifOperStatus   = $port_data['ifOperStatus'];
                $ifAdminStatus  = $port_data['ifAdminStatus'];
                $type           = $untagged ? "access port" : "trunk port";

                $table .= "<tr class='$format'>
                             <td>$vlan ($vlan_name)</td>
                             <td>$ifName</td>
                             <td>$ifAlias</td>
                             <td>$ifOperStatus</td>
                             <td>$ifAdminStatus</td>
                             <td>$ifSpeed</td>
                             <td>$ifDuplex</td>
                           </tr>\n";
            }
        }

        $table .= "</table><br>\n";
        print $table;
    }

    print "</div></div>";
}
?>
