<?php
class DeleteHistory extends SpecialPage
{
	function __construct()
	{
		// Need to belong to Administor group
		parent::__construct( 'DeleteHistory', 'editinterface' );
		wfLoadExtensionMessages('DeleteHistory');
	}

	function execute( $par )
	{
        // Get database size function
        function get_db_size( )
        {
    		global $wgRequest, $wgDBname;
    		$dbw =& wfGetDB( DB_MASTER );

			$dbsize = $dbw->query( "SELECT table_schema '" . $wgDBname . "', sum( data_length + index_length ) / 1024 / 1024 'DB sze in MB' FROM information_schema.TABLES where TABLE_SCHEMA like '" . $wgDBname . "' GROUP BY table_schema ;");
			while ($row = $dbw->fetchRow($dbsize))
            {
                $size = $row[1];
            }
            return $size;
        }

        // Load required globals
		global $wgRequest, $wgOut, $wgUser, $wgDBname, $wgVersion;

		$this->setHeaders();

		// Check if user is Admin
		if ( !$this->userCanExecute($wgUser) )
		{
			$this->displayRestrictionError();
			return;
		}

		$dbw =& wfGetDB( DB_MASTER );
 
		// Get request data from, e.g.
		$param = $wgRequest->getText('param');

		// Localisation messages
		$check_only = wfMsg('check_only');
		$del_hist = wfMsg('del_hist');
		$del_hist_opt = wfMsg('del_hist_opt');

		// User choice
        $wgOut->addWikiText(wfMsg('what_to_do') . ' :');
		$wgOut->addHTML("<form action=\"$_SERVER[REQUEST_URI]\" method=\"post\">
			<input type=\"radio\" name=\"choice\" value=\"0\" checked>$check_only<br />
			<input type=\"radio\" name=\"choice\" value=\"1\">$del_hist<br />
			<input type=\"radio\" name=\"choice\" value=\"2\">$del_hist_opt (MySQL)<br /><br />
			<input type=\"submit\" value=\"Validate\">
			</form>
		");

	    // Choosen action choice
        $show_db_size=0;
        $pwd = getcwd();

	    if ((isset($_POST['choice'])) and ($_POST['choice'] == "0"))
	    {
			$out_logs=shell_exec("php $pwd/maintenance/deleteOldRevisions.php");
	    }
		elseif ((isset($_POST['choice'])) and ($_POST['choice'] == "1"))
		{
			$out_logs=shell_exec("php $pwd/maintenance/deleteOldRevisions.php --delete");
		}
		elseif ((isset($_POST['choice'])) and ($_POST['choice'] == "2"))
		{
            $show_db_size=1;
            // Change deprecated function
            if ($wgVersion >= 1.18)
            {
    		    $dbw->begin();
            }
            else
            {
        		$dbw->immediateBegin();
            }

            // Get actual database size
            $db_size_old = get_db_size( );

            // Delete old history
			$out_logs=shell_exec("php $pwd/maintenance/deleteOldRevisions.php --delete");

			// Get all tables
			$alltables = $dbw->query( "SHOW TABLES" );

            // Show optimize status
            $out_opt="<table border=1 style='border-style: double;'>
                <tr>
                    <th>Tables</th>
                    <th>Status</th>
                </tr>
            ";

			while ($row = $dbw->fetchRow($alltables))
			{
                // Optimise tables
                $out_opt .= "<tr>\n<td>";
				$out_opt .= $row[0] . "</td>\n<td>";
      			$opt_res = $dbw->query("OPTIMIZE TABLE {$row[0]};");

				while ($res = $dbw->fetchRow( $opt_res ))
				{
                    // Set green color on OK and if table is already up to date
                    if (preg_match ("/OK/i", $res[3]))
                    {
					    $out_opt .= '<span style="font-weight: bold; color: green"> ' . $res[3] . '</span>';
                    }
                    elseif (preg_match ("/Table is already up to date/i", $res[3]))
                    {
                        $out_opt .= '<span style="color: green">' . $res[3] . '</span>';
                    }
                    else
                    {
                        $out_opt .= $res[3];
                    }
				}
                $out_opt .= '</td></tr>';
			}
            $out_opt.="</table>";

            // Get size after
            $db_size_new = get_db_size( );
		}

		// Fix display pre problem
        if ((isset($_POST['choice'])) and ($_POST['choice'] != ""))
        {
			$wgOut->addHTML("<br />");
            $wgOut->addWikiText('=' . wfMsg('result') . '=');

            // Show DB size if ask to optimize
            if ($show_db_size == 1)
            {
                function kb_or_mb($db_size_old,$db_size_new)
                {
                    $spacewon=($db_size_old - $db_size_new);
                    if ($spacewon < 1)
                    {
                        $spacewon = ($spacewon * 1024) . " KB";
                    }
                    else
                    {
                        $spacewon = $spacewon . " MB";
                    }
                    return $spacewon;
                }
                // Show result
                $wgOut->addHTML("<table border=1'>
                    <tr>
                        <td></td>
                        <th>" . wfMsg('size') . "</th>
                    </tr><tr>
                        <th>" . wfMsg('db_size_old') . "</th>
                        <td>$db_size_old MB</td>
                    </tr><tr>
                        <th>" . wfMsg('db_size_new') . "</th>
                        <td>$db_size_new MB</td>
                    </tr><tr>
                        <th>" . wfMsg('db_size_old') . "</th>
                        <td align='center'><b>" . kb_or_mb($db_size_old,$db_size_new) . "</b></td>
                    </tr></table><br />
                ");
            }

            if ($out_opt)
            {
                $wgOut->addWikiText('=' . wfMsg('opt_stat') . '=');
    		    $wgOut->addHTML($out_opt . '<br />');
            }
            if ($out_logs)
            {
                if ($_POST['choice'] == "2")
                {
                    $wgOut->addWikiText('=' . wfMsg('logs') . '=');
                }
    		    $wgOut->addHTML("<pre>".$out_logs."</pre>");
            }
		}
		return true;
	}
}
