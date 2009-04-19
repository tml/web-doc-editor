<?php
/**
 * Ext JS controller class definition file
 * 
 * @todo Add inline documentation for each controller task
 */

/**
 * Required file
 */
require_once './class.php';
require_once './LockFile.php';
require_once './BugReader.php';
require_once './NewsReader.php';

/**
 * Ext JS controller class
 */
class ExtJsController
{

    /**
     * A phpDoc instance
     *
     * @var phpDoc
     */
    private $phpDoc;
    /**
     * Array of request variables
     *
     * @var array
     */
    private $requestVariables = array();

    /**
     * Initializes the controller
     * 
     * @param array $request An associative array of request variables
     */
    public function __construct($request)
    {
        $this->phpDoc = new phpDoc();
        $this->requestVariables = $request;
    }

    /**
     * Returns the JSON representation of a value
     *
     * @param mixed $value The value being encoded. Can be any type except a resource.
     * @return string The JSON encoded value on success
     */
    public function getResponse($value)
    {
        return json_encode($value);
    }

    /**
     * Gets the failure response
     * @package string $message An optional error message
     *
     * @return string The failure string.
     */
    public function getFailure($message = false)
    {
        $return = array('success' => false);
        if ($message) {
            $return['msg'] = $message;
        }
        return $this->getResponse($return);
    }

    /**
     * Gets the success response
     *
     * @return string The success string.
     */
    public function getSuccess()
    {
        return $this->getResponse(array('success' => true));
    }

    /**
     * Gets the specified request variable
     *
     * @param string $name The variable name
     * @return mixed The variable value on success, FALSE is the variable was not set
     */
    public function getRequestVariable($name)
    {
        return $this->hasRequestVariable($name) ? $this->requestVariables[$name] : false;
    }

    /**
     * Tells if the specified request variable exist
     *
     * @param string $name The variable name
     * @return mixed Returns TRUE if the variable exists, FALSE otherwise
     */
    public function hasRequestVariable($name)
    {
        return isset($this->requestVariables[$name]);
    }

    /**
     * Login to the tool
     *
     * @return The Success response on success, or a Failure
     */
    public function login()
    {
        $cvsLogin  = $this->getRequestVariable('cvsLogin');
        $cvsPasswd = $this->getRequestVariable('cvsPassword');
        $lang      = $this->getRequestVariable('lang');

        $response  = $this->phpDoc->login($cvsLogin,$cvsPasswd,$lang);

        if ($response['state'] === true) {
            // This user is already know in a valid user
            return $this->getSuccess();
        } elseif ($response['state'] === false) {
            // This user is unknow from this server
            return $this->getFailure($response['msg']);
        } else {
            return $this->getFailure();
        }
    }


    public function updateRepository()
    {
        $this->phpDoc->isLogged();

        if ($this->phpDoc->cvsLogin == 'cvsread') {
            return $this->getFailure();
        }

        $this->phpDoc->updateRepository();
        return $this->getSuccess();
    }

    public function checkLockFile()
    {

        $lockFile = $this->getRequestVariable('lockFile');
        $lock = new LockFile($lockFile);

        $this->phpDoc->isLogged();
        return $lock->isLocked() ? $this->getSuccess() : $this->getFailure();
    }

    public function applyTools()
    {
        $this->phpDoc->isLogged();

        $this->phpDoc->cleanUp();

        // Set the lock File
        $lock = new LockFile('lock_apply_tools');

        if ($lock->lock()) {
            // Start Revcheck
            $this->phpDoc->revStart();

            // Parse translators
            $this->phpDoc->revParseTranslation();

            // Set lastUpdate date/time
            $this->phpDoc->setLastUpdate();

            // Check errors in files
            //        $tool = new ToolsError($_SESSION['lang']);
            //       $tool->run('/');

        }
        $lock->release();

        return $this->getSuccess();
    }


    /**
     * Tests the CVS username against its password
     *
     * @return Success
     */
    public function testCvsLogin()
    {

        $cvsLogin  = $this->getRequestVariable('cvsLogin');
        $cvsPasswd = $this->getRequestVariable('cvsPasswd');

        $this->phpDoc->login($cvsLogin,$cvsPasswd);
        $r = $this->phpDoc->checkCvsAuth();

        if ($r === true) {
            return $this->getSuccess();
        } else {
            return $this->getFailure(str_replace("\n", "", nl2br($r)));
        }
    }

    /**
     * Pings the server and user session
     *
     * @return string "pong" on success, "false" on failure
     */
    public function ping()
    {
        $this->phpDoc->isLogged();
        $r = $this->phpDoc->getLastUpdate();

        $response = !isset($_SESSION['userID']) ? 'false' : 'pong';

        return $this->getResponse(array('ping' => $response, 'lastupdate' => $r['lastupdate'], 'by' => $r['by']));

    }

    //NEW
    public function getFilesNeedUpdate() {
        $this->phpDoc->isLogged();
        $r = $this->phpDoc->getFilesNeedUpdate();
        return $this->getResponse(array('nbItems' => $r['nb'], 'Items' => $r['node']));
    }

    // NEW
    public function getFilesNeedReviewed() {
        $this->phpDoc->isLogged();
        $r = $this->phpDoc->getFilesNeedReviewed();
        return $this->getResponse(array('nbItems' => $r['nb'], 'Items' => $r['node']));
    }

    // NEW
    public function getFilesError() {
        $this->phpDoc->isLogged();
        
        $errorTools = new ToolsError($this->phpDoc->db);
        $errorTools->setParams('', '', $this->phpDoc->cvsLang, '', '', '');
        $r = $errorTools->getFilesError($this->phpDoc->getModifiedFiles());

        return $this->getResponse(array('nbItems' => $r['nb'], 'Items' => $r['node']));
    }

    // NEW
    public function getFilesPendingCommit() {
        $this->phpDoc->isLogged();

        $r = $this->phpDoc->getFilesPendingCommit();

        return $this->getResponse(array('nbItems' => $r['nb'], 'Items' => $r['node']));
    }

    // NEW
    public function getFilesPendingPatch() {
        $this->phpDoc->isLogged();

        $r = $this->phpDoc->getFilesPendingPatch();

        return $this->getResponse(array('nbItems' => $r['nb'], 'Items' => $r['node']));
    }

    //NEW
    public function getTranslatorInfo() {

        $this->phpDoc->isLogged();

        $translators = $this->phpDoc->getTranslatorsInfo();

        return $this->getResponse(array('nbItems' => count($translators), 'Items' => $translators));
    }

    //NEW
    public function getSummaryInfo() {

        $this->phpDoc->isLogged();

        $summary = $this->phpDoc->getSummaryInfo();

        return $this->getResponse(array('nbItems' => count($summary), 'Items' => $summary));
    }

    public function getLastNews() {

        $this->phpDoc->isLogged();

        $news = new NewsReader($this->phpDoc->cvsLang);
        $r = $news->getLastNews();

        return $this->getResponse(array('nbItems' => count($r), 'Items' => $r));
    }

    public function getOpenBugs() {

        $this->phpDoc->isLogged();

        $bugs = new BugReader($this->phpDoc->cvsLang);
        $r = $bugs->getOpenBugs();

        return $this->getResponse(array('nbItems' => count($r), 'Items' => $r));
    }

    public function getFile() {
        $this->phpDoc->isLogged();

        $FilePath = $this->getRequestVariable('FilePath');
        $FileName = $this->getRequestVariable('FileName');

        // We must detect the encoding of the file with the first line "xml version="1.0" encoding="utf-8"
        // If this utf-8, we don't need to use utf8_encode to pass to this app, else, we apply it

        $file = $this->phpDoc->getFileContent($FilePath, $FileName);

        $return = array('success' => true);

        if (strtoupper($file['charset']) == 'UTF-8') {
            $return['content'] = $file['content'];
        } else {
            $return['content'] = iconv($file['charset'], "UTF-8", $file['content']);
        }
        return $this->getResponse($return);
    }


    // NEW
    public function checkFileError() {

        $this->phpDoc->isLogged();
        $FilePath = $this->getRequestVariable('FilePath');
        $FileName = $this->getRequestVariable('FileName');
        $FileLang = $this->getRequestVariable('FileLang');

        // Remove \
        $FileContent = stripslashes($this->getRequestVariable('FileContent'));

        // Replace &nbsp; by space
        $FileContent = str_replace("&nbsp;", "", $FileContent);

        // Detect encoding
        $charset = $this->phpDoc->getFileEncoding($FileContent, 'content');

        // If the new charset is set to utf-8, we don't need to decode it
        if ($charset != 'utf-8') {
            // Utf8_decode
            $FileContent = utf8_decode($FileContent);
        }

        // Get EN content to check error with
        $dirEN = DOC_EDITOR_CVS_PATH.'/en'.$FilePath;
        $en_content = file_get_contents($dirEN.$FileName);

        // Do tools_error
        //$error = $this->phpDoc->tools_error_check_all($fileContent, $en_content);

        // Update DB with this new Error (if any)
        $info = $this->phpDoc->getInfoFromContent($FileContent);
        $anode[0] = array( 0 => $FileLang.$FilePath, 1 => $FileName, 2 => $en_content, 3 => $FileContent, 4 => $info['maintainer']);

        $errorTools = new ToolsError($this->phpDoc->db);
        $r = $errorTools->updateFilesError($anode, 'nocommit');

        //$r = $this->phpDoc->updateFilesError($anode, 'nocommit');

        return $this->getResponse(array('success' => true, 'error' => $r['state'], 'error_first' => $r['first']));
    }

    // NEW
    public function saveFile() {

        $this->phpDoc->isLogged();

        $filePath   = $this->getRequestVariable('filePath');
        $fileName   = $this->getRequestVariable('fileName');
        $fileLang   = $this->getRequestVariable('fileLang');
        $type       = $this->getRequestVariable('type') ? $this->getRequestVariable('type') : 'file';
        $emailAlert = $this->getRequestVariable('emailAlert') ? $this->getRequestVariable('emailAlert') : '';


        if ($this->phpDoc->cvsLogin == 'cvsread' && $type == 'file') {
            return $this->getFailure();
        }

        // Clean up path
        $filePath = str_replace('//', '/', $filePath);

        // Extract lang from path
        if ($fileLang == 'all') {
            $t = explode('/', $filePath);

            $fileLang = $t[0];

            array_shift($t);
            $filePath = '/'.implode('/', $t);
        }

        // Remove \
        $fileContent = stripslashes($this->getRequestVariable('fileContent'));

        // Replace &nbsp; by space
        $fileContent = str_replace("&nbsp;", "", $fileContent);

        // Detect encoding
        $charset = $this->phpDoc->getFileEncoding($fileContent, 'content');

        // If the new charset is set to utf-8, we don't need to decode it
        if ($charset != 'utf-8') {
            // Utf8_decode
            //$fileContent = utf8_decode($fileContent);
            $fileContent = iconv("UTF-8", $charset, $fileContent);
        }

        // Get revision
        $info = $this->phpDoc->getInfoFromContent($fileContent);

        if ($type == 'file') {

            $this->phpDoc->saveFile($filePath.$fileName, $fileContent, $fileLang, 'file');
            $this->phpDoc->registerAsPendingCommit($fileLang, $filePath, $fileName, $info['rev'], $info['en-rev'], $info['reviewed'], $info['maintainer']);
            return $this->getResponse(array(
            'success' => true,
            'en_revision' => $info['rev'],
            'new_revision' => $info['en-rev'],
            'maintainer' => $info['maintainer'],
            'reviewed' => $info['reviewed']
            ));

        } else {
            $uniqID = $this->phpDoc->registerAsPendingPatch($fileLang, $filePath, $fileName, $emailAlert);
            $this->phpDoc->saveFile($filePath.$fileName, $fileContent, $fileLang, 'patch', $uniqID);
            return $this->getResponse(array(
            'success' => true,
            'uniqId' => $uniqID,
            ));
        }

    }

    // NEW
    public function getLog() {

        $this->phpDoc->isLogged();
        $Path = $this->getRequestVariable('Path');
        $File = $this->getRequestVariable('File');

        $r = $this->phpDoc->cvsGetLog($Path, $File);
        return $this->getResponse(array('nbItems' => count($r), 'Items' => $r));
    }

    public function getDiff() {

        $this->phpDoc->isLogged();
        $FilePath = $this->getRequestVariable('FilePath');
        $FileName = $this->getRequestVariable('FileName');
        $type     = $this->getRequestVariable('type') ? $this->getRequestVariable('type') : '';
        $uniqID   = $this->getRequestVariable('uniqID') ? $this->getRequestVariable('uniqID') : '';

        $info = $this->phpDoc->getDiffFromFiles($FilePath, $FileName, $type, $uniqID);
        return $this->getResponse(array(
        'success' => true,
        'content' => $info['content'],
        'encoding' => $info['charset'],
        ));
    }

    //NEW
    public function getDiff2() {

        $this->phpDoc->isLogged();
        $FilePath = $this->getRequestVariable('FilePath');
        $FileName = $this->getRequestVariable('FileName');
        $Rev1 = $this->getRequestVariable('Rev1');
        $Rev2 = $this->getRequestVariable('Rev2');

        $r = $this->phpDoc->getDiffFromExec($FilePath, $FileName, $Rev1, $Rev2);

        return $this->getResponse(array(
        'success' => true,
        'content' => $r,
        ));

    }

    public function erasePersonalData() {

        $this->phpDoc->isLogged();

        if ($this->phpDoc->cvsLogin == 'cvsread') {
            return $this->getFailure();
        }

        $this->phpDoc->erasePersonalData();
        return $this->getSuccess();

    }

    public function getCommitLogMessage() {

        $this->phpDoc->isLogged();
        $r = $this->phpDoc->getCommitLogMessage();
        return $this->getResponse(array('nbItems' => count($r), 'Items' => $r));
    }

    //NEW
    public function clearLocalChange() {
        $this->phpDoc->isLogged();

        if ($this->phpDoc->cvsLogin == 'cvsread') {
            return $this->getFailure();
        }

        $FilePath = $this->getRequestVariable('FilePath');
        $FileName = $this->getRequestVariable('FileName');

        $info = $this->phpDoc->clearLocalChange($FilePath, $FileName);

        $return = array('success' => true);
        $return['revision']   = $info['rev'];
        $return['maintainer'] = $info['maintainer'];
        $return['error']      = $info['errorFirst'];
        $return['reviewed']   = $info['reviewed'];
        return $this->getResponse($return);
    }

    public function getLogFile()
    {

        $this->phpDoc->isLogged();

        $file = $this->getRequestVariable('file');

        $content = $this->phpDoc->getOutputLogFile($file);

        return $this->getResponse(array('success' => true, 'mess' => $content));

    }

    public function checkBuild()
    {

        $this->phpDoc->isLogged();

        if ($this->phpDoc->cvsLogin == 'cvsread') {
            return $this->getFailure();
        }

        $xmlDetails = $this->getRequestVariable('xmlDetails');

        $lock = new LockFile('lock_check_build');
        if ($lock->lock()) {
            // Start the checkBuild system
            $output = $this->phpDoc->checkBuild($xmlDetails);
        }
        // Remove the lock File
        $lock->release();

        // Send output into a log file
        $this->phpDoc->saveOutputLogFile('log_check_build', $output);
        return $this->getSuccess();
    }

    public function cvsCommit() {
        $this->phpDoc->isLogged();

        if ($this->phpDoc->cvsLogin == 'cvsread') {
            return $this->getFailure();
        }

        $nodes = $this->getRequestVariable('nodes');
        $logMessage = stripslashes($this->getRequestVariable('logMessage'));

        $anode = json_decode(stripslashes($nodes));

        $r = $this->phpDoc->cvsCommit($anode, $logMessage);

        return $this->getResponse(array('success' => true, 'mess' => $r));
    }

    public function onSuccesCommit() {

        $this->phpDoc->isLogged();

        if ($this->phpDoc->cvsLogin == 'cvsread') {
            return $this->getFailure();
        }

        $nodes = $this->getRequestVariable('nodes');
        $logMessage = stripslashes($this->getRequestVariable('logMessage'));

        $anode = json_decode(stripslashes($nodes));

        // Update revision & reviewed for all this files
        $this->phpDoc->updateRev($anode);

        // Update FilesError for all this files
        for ($i = 0; $i < count($anode); $i++) {

            $t = explode("/", $anode[$i][0]);

            $FileLang = $t[0];
            array_shift($t);

            $FilePath = '/'.implode("/", $t);
            $FileName = $anode[$i][1];

            $en_content     = file_get_contents(DOC_EDITOR_CVS_PATH.'en'.$FilePath.$FileName);
            $lang_content   = file_get_contents(DOC_EDITOR_CVS_PATH.$FileLang.$FilePath.$FileName);

            $info = $this->phpDoc->getInfoFromContent($lang_content);

            $anode[$i][2] = $en_content;
            $anode[$i][3] = $lang_content;
            $anode[$i][4] = $info['maintainer'];
        }

        $errorTools = new ToolsError($this->phpDoc->db);
        $errorTools->updateFilesError($anode);

        // Remove all this files in needcommit
        $this->phpDoc->removeNeedCommit($anode);

        // Manage this logMessage
        $this->phpDoc->manageLogMessage($logMessage);
        return $this->getSuccess();

    }

    public function getConf() {

        $this->phpDoc->isLogged();
        $r['userLang']  = $this->phpDoc->cvsLang;
        $r['userLogin'] = $this->phpDoc->cvsLogin;
        $r['userConf']  = $this->phpDoc->userConf;

        return $this->getResponse(array('success' => true, 'mess' => $r));
    }

    public function sendEmail() {

        $this->phpDoc->isLogged();

        $to      = $this->getRequestVariable('to');
        $subject = $this->getRequestVariable('subject');
        $msg     = $this->getRequestVariable('msg');

        $this->phpDoc->sendEmail($to, $subject, $msg);
        return $this->getSuccess();
    }

    public function confUpdate() {

        $this->phpDoc->isLogged();

        $item      = $this->getRequestVariable('item');
        $value     = $this->getRequestVariable('value');

        $r = $this->phpDoc->updateConf($item, $value);
        return $this->getResponse(array('success' => true, 'msg' => $r));
    }

    public function getAllFiles() {

        $this->phpDoc->isLogged();

        $node  = $this->getRequestVariable('node');
        $search  = $this->getRequestVariable('search');

        $files = $this->phpDoc->getAllFiles($node, $search);

        return $this->getResponse($files);
    }

    public function saveLogMessage() {

        $this->phpDoc->isLogged();

        if ($this->phpDoc->cvsLogin == 'cvsread') {
            return $this->getFailure();
        }

        $messID = $this->getRequestVariable('messID');
        $mess   = stripslashes($this->getRequestVariable('mess'));

        $this->phpDoc->saveLogMessage($messID, $mess);
        return $this->getSuccess();
    }

    public function deleteLogMessage() {

        $this->phpDoc->isLogged();

        if ($this->phpDoc->cvsLogin == 'cvsread') {
            return $this->getFailure();
        }

        $messID = $this->getRequestVariable('messID');

        $this->phpDoc->deleteLogMessage($messID);
        return $this->getSuccess();
    }

    public function getAllFilesAboutExtension() {

        $this->phpDoc->isLogged();

        $ExtName = $this->getRequestVariable('ExtName');

        $r = $this->phpDoc->getAllFilesAboutExtension($ExtName);

        return $this->getResponse(array('success' => true, 'files' => $r));
    }

    public function afterPatchAccept() {

        $this->phpDoc->isLogged();

        $PatchUniqID = $this->getRequestVariable('PatchUniqID');

        $this->phpDoc->afterPatchAccept($PatchUniqID);
        return $this->getSuccess();
    }

    public function afterPatchReject() {

        $this->phpDoc->isLogged();

        if ($this->phpDoc->cvsLogin == 'cvsread') {
            return $this->getFailure();
        }

        $PatchUniqID = $this->getRequestVariable('PatchUniqID');

        $this->phpDoc->afterPatchReject($PatchUniqID);

        return $this->getSuccess();
    }

    public function getCheckDocData() {

        $this->phpDoc->isLogged();

        $r = $this->phpDoc->getCheckDocData();

        return $this->getResponse(array('nbItems' => $r['nb'], 'Items' => $r['node']));
    }

    public function getCheckDocFiles() {

        $this->phpDoc->isLogged();

        $path      = $this->getRequestVariable('path');
        $errorType = $this->getRequestVariable('errorType');

        $r = $this->phpDoc->getCheckDocFiles($path, $errorType);

        return $this->getResponse(array('success' => true, 'files' => $r));
    }

    public function downloadPatch()
    {

        $FilePath = $this->getRequestVariable('FilePath');
        $FileName = $this->getRequestVariable('FileName');

        $patch = $this->phpDoc->getRawDiff($FilePath, $FileName);

        $file = 'patch-' . time() . '.patch';

        $size = strlen($patch);

        header("Content-Type: application/force-download; name=\"$file\"");
        header("Content-Transfer-Encoding: binary");
        header("Content-Disposition: attachment; filename=\"$file\"");
        header("Expires: 0");
        header("Cache-Control: no-cache, must-revalidate");
        header("Pragma: no-cache");
        return $patch;
    }

    public function logout()
    {

        $_SESSION = array();
        setcookie(session_name(), '', time()-42000, '/');
        session_destroy();
        header("Location: ../");
        exit;

    }

    public function translationGraph()
    {
        require_once './jpgraph/src/jpgraph.php';
        require_once './jpgraph/src/jpgraph_pie.php';
        require_once './jpgraph/src/jpgraph_pie3d.php';

        $this->phpDoc->isLogged();

        $Total_files_lang = $this->phpDoc->getNbFiles();
        $Total_files_lang = $Total_files_lang[0];
        //
        $up_to_date = $this->phpDoc->getNbFilesTranslated();
        $up_to_date = $up_to_date[0];
        //
        $critical = $this->phpDoc->getStatsCritical();
        $critical = $critical[0];
        //
        $old = $this->phpDoc->getStatsOld();
        $old = $old[0];
        //
        $missing = sizeof($this->phpDoc->getMissFiles());
        //
        $no_tag = $this->phpDoc->getStatsNoTag();
        $no_tag = $no_tag[0];
        //
        $data = array($up_to_date,$critical,$old,$missing,$no_tag);
        $pourcent = array();
        $total = 0;
        $total = array_sum($data);

        foreach ( $data as $valeur ) {
            $pourcent[] = round($valeur * 100 / $total);
        }

        $noExplode = ($Total_files_lang == $up_to_date) ? 1 : 0;

        $legend = array(
        $pourcent[0] . '%% up to date ('.$up_to_date.')',
        $pourcent[1] . '%% critical ('.$critical.')',
        $pourcent[2] . '%% old ('.$old.')',
        $pourcent[3] . '%% missing ('.$missing.')',
        $pourcent[4] . '%% without revtag ('.$no_tag.')'
        );

        $title = 'PHP : Details for '.ucfirst($this->phpDoc->cvsLang).' Documentation';

        $graph = new PieGraph(530,300);
        $graph->SetShadow();

        $graph->title->Set($title);
        $graph->title->Align('left');
        $graph->title->SetFont(FF_FONT1,FS_BOLD);

        $graph->legend->Pos(0.02,0.18,"right","center");

        $graph->subtitle->Set('(Total: '.$Total_files_lang.' files)');
        $graph->subtitle->Align('left');
        $graph->subtitle->SetColor('darkred');

        $t1 = new Text(date('m/d/Y'));
        $t1->SetPos(522,294);
        $t1->SetFont(FF_FONT1,FS_NORMAL);
        $t1->Align("right", 'bottom');
        $t1->SetColor("black");
        $graph->AddText($t1);

        $p1 = new PiePlot3D($data);
        $p1->SetSliceColors(array("#68d888", "#ff6347", "#eee8aa", "#dcdcdc", "#f4a460"));
        if ($noExplode != 1) {
            $p1->ExplodeAll();
        }
        $p1->SetCenter(0.35,0.55);
        $p1->value->Show(false);

        $p1->SetLegends($legend);

        $graph->Add($p1);
        $graph->Stroke();

        return '';
    }

    public function getLastUpdate() {

        $this->phpDoc->isLogged();
        $r = $this->phpDoc->getLastUpdate();

        return $this->getResponse(array('success' => true, 'lastupdate' => $r['lastupdate']));
    }

}