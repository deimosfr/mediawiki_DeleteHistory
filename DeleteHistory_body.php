<?php
class DeleteHistory extends SpecialPage
{
    function __construct()
    {
        global $wgVersion;
        // Need to belong to Administor group
        parent::__construct( 'DeleteHistory', 'editinterface' );
        if ($wgVersion <= 1.16) {
            wfLoadExtensionMessages('DeleteHistory');
        }
    }

    function execute( $par )
    {
        // Get database size function
        function get_db_size()
        {
            global $wgRequest, $wgDBname;
            $dbw =& wfGetDB( DB_MASTER );

            $mw_dbname = $dbw->buildLike($wgDBname);
            $dbsize = $dbw->query( "select table_schema " . $mw_dbname . ", sum( data_length + index_length ) / 1024 / 1024 'DB sze in MB' from information_schema.TABLES where TABLE_SCHEMA " . $mw_dbname . " GROUP BY table_schema ;");
            while ($row = $dbw->fetchRow($dbsize))
            {
                $size = $row[1];
            }
            return $size;
        }

        // Get tables engine and collation
        function get_engine_col($table_name)
        {
            global $wgRequest, $wgDBname;
            $dbw =& wfGetDB( DB_MASTER );

            $engine_collation = $dbw->query( "select ENGINE,TABLE_COLLATION from information_schema.TABLES where TABLE_SCHEMA = '" . $wgDBname . "' and TABLE_NAME = '" . $table_name . "';");
            while ($row = $dbw->fetchRow($engine_collation))
            {
                $engine = $row[0];
                $collation = $row[1];
            }
            return array('engine' => $engine, 'collation' => $collation);
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

        // Escaping URI
        $current_uri = htmlentities($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8');

        // User choice
        $wgOut->addWikiText(wfMsg('what_to_do') . ' :');
        $wgOut->addHTML("<form action=\"$current_uri\" method=\"post\">
            <input type=\"radio\" name=\"choice\" value=\"0\" checked>$check_only<br />
            <input type=\"radio\" name=\"choice\" value=\"1\">$del_hist<br />
            <input type=\"radio\" name=\"choice\" value=\"2\">$del_hist_opt (MySQL)<br /><br />
            <input type=\"submit\" value=\"" . wfMsg('validate') . "\">
            </form>
            ");

        // Choosen action choice
        $show_db_size=0;
        $pwd = getcwd();
        $command = 'php ' . $pwd . '/maintenance/deleteOldRevisions.php --delete';

        if ((isset($_POST['choice'])) and ($_POST['choice'] == "0"))
        {
            $command = 'php ' . $pwd . '/maintenance/deleteOldRevisions.php';
            $out_logs = shell_exec(escapeshellcmd($command));
        }
        elseif ((isset($_POST['choice'])) and ($_POST['choice'] == "1"))
        {
            $out_logs = shell_exec(escapeshellcmd($command));
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
            $out_logs = shell_exec(escapeshellcmd($command));

            // Get all tables
            $alltables = $dbw->query( "SHOW TABLES" );

            // Show optimize status
            $out_opt="<table border=1 style='border-style: double;'>
                <tr>
                <th>Tables Names</th>
                <th>Engine</th>
                <th>Collation</th>
                <th>Status</th>
                </tr>
                ";

            while ($row = $dbw->fetchRow($alltables))
            {
                // Run and show output
                $out_opt .= "<tr>\n<td>";
                // Database name
                $out_opt .= $row[0] . "&nbsp;</td>\n<td>";
                // Get engine and collation
                extract(get_engine_col($row[0]));
                $out_opt .= $engine . "&nbsp;</td>\n<td>";
                $out_opt .= $collation . "&nbsp;</td>\n<td>";
                // Optimize
                $opt_res = $dbw->query("OPTIMIZE TABLE {$row[0]};");

                while ($res = $dbw->fetchRow( $opt_res ))
                {
                    // Set green color on OK and if table is already up to date
                    if (preg_match ("/OK/i", $res[3]))
                    {
                        $out_opt .= '<span style="font-weight: bold; color: green"> ' . $res[3] . '&nbsp;</span>';
                    }
                    elseif (preg_match ("/Table is already up to date/i", $res[3]))
                    {
                        $out_opt .= '<span style="color: green">' . $res[3] . '&nbsp;</span>';
                    }
                    else
                    {
                        $out_opt .= $res[3] . '&nbsp;';
                    }
                }
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
                    <th>" . wfMsg('db_space_won') . "</th>
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
