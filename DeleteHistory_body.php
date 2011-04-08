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
                $wgOut->addHTML(wfMsg('db_size_old') . '<b>' . $db_size_old . " MB</b><br />");
                $wgOut->addHTML(wfMsg('db_size_new') . '<b>' . $db_size_new . " MB</b><br />");
                $wgOut->addHTML(wfMsg('db_space_won') . '<b>' . ($db_size_old - $db_size_new) . " MB</b><br />");
            }
            $wgOut->addHTML("<pre>".$out."</pre>");
        }
        return true;
    }
}

