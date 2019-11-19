# librenms-plugin-WhereIsVLAN
A LibreNMS plugin to show what switchports that have a specific VLAN active

# INSTALL
Copy the WhereIsVLAN directory to your librenms/html/plugins/ directory.
In Librenms go to Overview->Plugins->Plugin Admin
Click enable on "WhereIsVLAN"

# USAGE
Go to Overview->Plugins->WhereIsVLAN
Enter vlan id to search for, you may enter multiple vlans separated by ',' or a range with '-' ex.242,1984-1999
Also you may exclude trunk (tagged) or untagged (access) ports from the result.
