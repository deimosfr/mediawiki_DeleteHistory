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
        // Set readable database size
        function formatfilesize($data)
        {
            // bytes
            if ( $data < 1024 )
            {
                return $data . ' B';
            }
            // kilobytes
            elseif ( $data < 1024000 )
            {
                return round( ( $data / 1024 ), 1 ) . ' KB';
            }
            // megabytes
            else
            {
                return round( ( $data / 1024000 ), 1 ) . ' MB';
            }
        }
        // Get database size function
        function get_db_size( )
        {
    		global $wgRequest, $wgDBname;
    		$dbw =& wfGetDB( DB_MASTER );

			$dbsize = $dbw->query( "SELECT CONCAT(sum(ROUND((DATA_LENGTH + INDEX_LENGTH - DATA_FREE),2))) AS Size FROM INFORMATION_SCHEMA.TABLES where TABLE_SCHEMA like '". $wgDBname . "' ;" );
			while ($row = $dbw->fetchRow($dbsize))
            {
                $size = $row[0];
            }
            return $size;
        }

		global $wgRequest, $wgOut, $wgUser, $wgDBname;

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
			$out=shell_exec("php $pwd/maintenance/deleteOldRevisions.php");
	    }
		elseif ((isset($_POST['choice'])) and ($_POST['choice'] == "1"))
		{
			$out=shell_exec("php $pwd/maintenance/deleteOldRevisions.php --delete");
		}
		elseif ((isset($_POST['choice'])) and ($_POST['choice'] == "2"))
		{
            $show_db_size=1;
    		$dbw->immediateBegin();

            // Get actual database size
            $db_size_old = get_db_size( );

            // Delete old history
			$out=shell_exec("php $pwd/maintenance/deleteOldRevisions.php --delete");
            $out.="</pre>\n<pre>";

			// Get all tables
			$alltables = $dbw->query( "SHOW TABLES" );
			while ($row = $dbw->fetchRow($alltables))
			{
                // Optimise tables
				$out .= 'Optimizing table ' . $row[0] . ' : ';
				$opt_res = $dbw->query("OPTIMIZE TABLE {$row[0]};");

				while ($res = $dbw->fetchRow( $opt_res ))
				{
                    // Set green color on OK and if table is already up to date
                    if (preg_match ("/OK/i", $res[3]))
                    {
					    $out .= '<span style="font-weight: bold; color: green">' . $res[3] . '</span>';
                    }
                    elseif (preg_match ("/Table is already up to date/i", $res[3]))
                    {
                        $out .= '<span style="color: green">' . $res[3] . '</span>';
                    }
                    else
                    {
                        $out .= $res[3];
                    }
				}
				$out .= '<br />';
			}

            // Get size after
            $db_size_new = get_db_size( );
		}

		// Fix display pre problem
        if ((isset($_POST['choice'])) and ($_POST['choice'] != ""))
        {
			$wgOut->addHTML("<br /><hr />");
            $wgOut->addWikiText(wfMsg('result') . ' :');

            // Show DB size if ask to optimize
            if ($show_db_size == 1)
            {
                $wgOut->addHTML(wfMsg('db_size_old') . '<b>' . formatfilesize($db_size_old) . "</b><br />");
                $wgOut->addHTML(wfMsg('db_size_new') . '<b>' . formatfilesize($db_size_new) . "</b><br />");
                $wgOut->addHTML(wfMsg('db_space_won') . '<b>' . formatfilesize($db_size_old - $db_size_new) . "</b><br />");
            }

		    $wgOut->addHTML("<pre>".$out."</pre>");
		}
		return true;
	}
}
