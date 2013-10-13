<?php
include 'postgresql.php';

$timezone = "Asia/Tokyo";
if (function_exists('date_default_timezone_set'))
    date_default_timezone_set($timezone);

session_start();

function debug_log($str) {
    date_default_timezone_set('Asia/Tokyo');
    $datetime = date("Y/m/d (D) H:i:s", time());
    //日時
    $client_ip = $_SERVER["REMOTE_ADDR"];
    //クライアントのIP
    $request_url = $_SERVER["REQUEST_URI"];
    //アクセスしたURL
    $msg = "[{$datetime}][client {$client_ip}][url {$request_url}]{$str}";
    error_log($msg . "\n", 3, "/home2/ns/_logs/tao.log");
}

//ディレクトリ
$dirName = "./instances/";

//ディレクトの存在チェック
if (is_dir($dirName)) {
    //ディレクトリハンドル取得
    if ($dir = opendir($dirName)) {
        //ファイル情報読み込み、表示
        print "<table border='1' style='width:500px'>";
        while (($file = readdir($dir)) !== false) {
            if(($file != ".") && ($file != "..")) {
                //ファイルオープン
                $fileH = fopen($dirName . $file,"r");
                //表示
                //if ($file=='0001-20090630-137-697-1-1855.xml') {
                print "<tr><td bgcolor='#CCCCCC'>" .$file . "</td></tr>";
                import_xbrl($pdo, $dirName . $file);
                //}
                //ファイルクローズ
                fclose($fileH);
            }
        }
        print "</table>";
        closedir($dir);
    }
}

function import_xbrl($pdo, $filename) {
    $dom = new DOMDocument;
    $dom->load($filename);
    //$dom -> loadXML($xml);
    if (!$dom) {
        debug_log(__LINE__ . ":" . 'Error while parsing the document');
        exit ;
    }
    $glcor = 'http://www.xbrl.org/taxonomy/int/gl/cor/2003-08-29/';
    $glbus = 'http://www.xbrl.org/taxonomy/int/gl/bus/2003-08-29/';
    $glmuc = 'http://www.xbrl.org/taxonomy/int/gl/muc/2003-08-29/';

    $entries = $dom -> getElementsByTagNameNS($glcor, 'accountingEntries');
    if (count($entries)) :
        foreach ($entries as $entry) :
            debug_log(__LINE__ . ":" . 'ENTRY');
            /*
             * documentInfo
             */
            debug_log(__LINE__ . ":" . '- DOCUMENT');
            $documentInfo = $entry -> getElementsByTagNameNS($glcor, 'documentInfo') -> item(0);
            $entriesType = $documentInfo -> getElementsByTagNameNS($glcor, 'entriesType') -> item(0) -> nodeValue;
            $entriesType = trim($entriesType);
            debug_log(__LINE__ . ":" . 'Entries Type：' . $entriesType);
            $uniqueID = $documentInfo -> getElementsByTagNameNS($glcor, 'uniqueID') -> item(0) -> nodeValue;
            $uniqueID = trim($uniqueID);
            debug_log(__LINE__ . ":" . 'Unique ID：' . $uniqueID);
            /*
             * entityInformation
             */
            debug_log(__LINE__ . ":" . '--  ENTITY');
            $entityInformation = $entry -> getElementsByTagNameNS($glcor, 'entityInformation') -> item(0);
            $organizationIdentifiers = $entityInformation -> getElementsByTagNameNS($glbus, 'organizationIdentifiers') -> item(0);
            $organizationIdentifier = $organizationIdentifiers -> getElementsByTagNameNS($glbus, 'organizationIdentifier') -> item(0) -> nodeValue;
            $organizationIdentifier = trim($organizationIdentifier);
            debug_log(__LINE__ . ":" . 'Identifier：' . $organizationIdentifier);
            $organizationDescription = $organizationIdentifiers -> getElementsByTagNameNS($glbus, 'organizationDescription') -> item(0) -> nodeValue;
            $organizationDescription = trim($organizationDescription);
            debug_log(__LINE__ . ":" . 'Description：' . $organizationDescription);
            $organizationAddress = $entityInformation -> getElementsByTagNameNS($glbus, 'organizationAddress');
            if (count($organizationAddress)) :
                debug_log(__LINE__ . ":" . '--  ADDRESS');
                foreach ($organizationAddress as $address) :
                    $organizationAddressStreet = $address -> getElementsByTagNameNS($glbus, 'organizationAddressStreet') -> item(0) -> nodeValue;
                    $organizationAddressStreet = trim($organizationAddressStreet);
                    debug_log(__LINE__ . ":" . 'Street:' . $organizationAddressStreet);
                    $organizationAddressZipOrPostalCode = $address -> getElementsByTagNameNS($glbus, 'organizationAddressZipOrPostalCode') -> item(0) -> nodeValue;
                    $organizationAddressZipOrPostalCode = trim($organizationAddressZipOrPostalCode);
                    debug_log(__LINE__ . ":" . 'Zip or Postal Code:' . $organizationAddressZipOrPostalCode);
                endforeach;
            endif;
            /*
             * INSERT TABLE
             */
            $sql = "INSERT INTO accountingenrties(";
            $sql .= "entriesType, uniqueID, ";
            $sql .= "organizationIdentifier, organizationDescription, organizationAddressStreet, organizationAddressZipOrPostalCode";
            $sql .= ") ";
            $sql .= "VALUES(";
            $sql .= "'".$entriesType."','".$uniqueID."',";
            $sql .= "'".$organizationIdentifier."','".$organizationDescription."','".$organizationAddressStreet."','".$organizationAddressZipOrPostalCode."'";
            $sql .= ") ";
            debug_log(__LINE__ . ':'.$sql);
            $stmt = $pdo -> prepare($sql);
/*            $stmt -> bindParam(':entriesType', $entriesType);
            $stmt -> bindParam(':uniqueID', $uniqueID);
            $stmt -> bindParam(':organizationIdentifier', $organizationIdentifier);
            $stmt -> bindParam(':organizationDescription', $organizationDescription);
            $stmt -> bindParam(':organizationAddressStreet', $organizationAddressStreet);
            $stmt -> bindParam(':organizationAddressZipOrPostalCode', $organizationAddressZipOrPostalCode);
            debug_log(__LINE__ . ':'.
                ' :entriesType='. $entriesType.
                ' :uniqueID='. $uniqueID.
                ' :organizationIdentifier='. $organizationIdentifier.
                ' :organizationDescription='. $organizationDescription.
                ' :organizationAddressStreet='. $organizationAddressStreet.
                ' :organizationAddressZipOrPostalCode='. $organizationAddressZipOrPostalCode
            );
*/
            try {
                $stmt -> execute();
            } catch( PDOExecption $e ) {
                print "Error!: " . $e -> getMessage() . "<br>";
            }
            // Get current sequence id value
            $sql = "SELECT currval('accountingenrties_id_seq') AS seq";
            $stmt = $pdo -> query($sql);
            while ($row = $stmt -> fetch(PDO::FETCH_ASSOC)) {
                $accountingEnrtiesID = $row['seq'];
            }
            /*
             * entryHeader
             */
            $entryHeader = $entry -> getElementsByTagNameNS($glcor, 'entryHeader');
            if (count($entryHeader)) :
                foreach ($entryHeader as $header) :
                    debug_log(__LINE__ . ":" . '- HEADER');
                    $enteredDate = $header -> getElementsByTagNameNS($glcor, 'enteredDate') -> item(0) -> nodeValue;
                    $enteredDate = trim($enteredDate);
                    debug_log(__LINE__ . ":" . 'Entered date:' . $enteredDate);
                    $sourceJournalDescription = $header -> getElementsByTagNameNS($glbus, 'sourceJournalDescription') -> item(0) -> nodeValue;
                    $sourceJournalDescription = trim($sourceJournalDescription);
                    debug_log(__LINE__ . ":" . 'Source Journal:' . $sourceJournalDescription);
                    $entryNumber = $header -> getElementsByTagNameNS($glcor, 'entryNumber') -> item(0) -> nodeValue;
                    $entryNumber = trim($entryNumber);
                    debug_log(__LINE__ . ":" . 'Entry Number:' . $entryNumber);
                    /*
                     * INSERT TABLE
                     */
                    $sql = "INSERT INTO entryheaders(";
                    $sql .= "accountingEnrtiesID, enteredDate, sourceJournalDescription, entryNumber";
                    $sql .= ") ";
                    $sql .= "VALUES(";
                    $sql .= ":accountingEnrtiesID, :enteredDate, :sourceJournalDescription, :entryNumber";
                    $sql .= ") ";
                    debug_log(__LINE__ . ':'.$sql);
                    $stmt = $pdo -> prepare($sql);
                    $stmt -> bindParam(':accountingEnrtiesID', $accountingEnrtiesID);
                    $stmt -> bindParam(':enteredDate', $enteredDate);
                    $stmt -> bindParam(':sourceJournalDescription', $sourceJournalDescription);
                    $stmt -> bindParam(':entryNumber', $entryNumber);
                    try {
                        $stmt -> execute();
                    } catch( PDOExecption $e ) {
                        print "Error!: " . $e -> getMessage() . "<br>";
                    }
                    // Get current sequence id value
                    $sql = "SELECT currval('entryheaders_id_seq') AS seq";
                    $stmt = $pdo -> query($sql);
                    while ($row = $stmt -> fetch(PDO::FETCH_ASSOC)) {
                        $entryHeadersID = $row['seq'];
                    }
                    /*
                     * entryDetail
                     */
                    $entryDetail = $header -> getElementsByTagNameNS($glcor, 'entryDetail');
                    if (count($entryDetail)) :
                        foreach ($entryDetail as $detail) :
                            debug_log(__LINE__ . ":" . '--  DETAIL');
                            $postingDate = $detail -> getElementsByTagNameNS($glcor, 'postingDate') -> item(0) -> nodeValue;
                            $postingDate = trim($postingDate);
                            debug_log(__LINE__ . ":" . 'Posting Date:' . $postingDate);
                            debug_log(__LINE__ . ":" . '---   ACCOUNT');
                            $account = $detail -> getElementsByTagNameNS($glcor, 'account') -> item(0);
                            $accountMainID = $account -> getElementsByTagNameNS($glcor, 'accountMainID') -> item(0) -> nodeValue;
                            $accountMainID = trim($accountMainID);
                            debug_log(__LINE__ . ":" . 'Account Main ID:' . $accountMainID);
                            $accountMainDescription = $account -> getElementsByTagNameNS($glcor, 'accountMainDescription') -> item(0) -> nodeValue;
                            $accountMainDescription = trim($accountMainDescription);
                            debug_log(__LINE__ . ":" . 'Account Main Description:' . $accountMainDescription);
                            $accountType = $account -> getElementsByTagNameNS($glcor, 'accountType') -> item(0) -> nodeValue;
                            $accountType = trim($accountType);
                            debug_log(__LINE__ . ":" . 'Account Type:' . $accountType);
                            $accountSub = $account -> getElementsByTagNameNS($glcor, 'accountSub') -> item(0);
                            $accountSubID = $accountSub -> getElementsByTagNameNS($glcor, 'accountSubID') -> item(0) -> nodeValue;
                            $accountSubID = trim($accountSubID);
                            debug_log(__LINE__ . ":" . 'Aaccount Sub ID:' . $accountSubID);
                            $accountSubDescription = $accountSub -> getElementsByTagNameNS($glcor, 'accountSubDescription') -> item(0) -> nodeValue;
                             $accountSubDescription = trim($accountSubDescription);
                            debug_log(__LINE__ . ":" . 'Aaccount Sub Description:' . $accountSubDescription);
                            $accountSubType = $accountSub -> getElementsByTagNameNS($glcor, 'accountSubType') -> item(0) -> nodeValue;
                             $accountSubType = trim($accountSubType);
                            debug_log(__LINE__ . ":" . 'Aaccount Sub Type:' . $accountSubType);
                            debug_log(__LINE__ . ":" . '---');
                            $debitCreditCode = $detail -> getElementsByTagNameNS($glcor, 'debitCreditCode') -> item(0) -> nodeValue;
                            $debitCreditCode = trim($debitCreditCode);
                            debug_log(__LINE__ . ":" . 'Debit Credit Code:' . $debitCreditCode);
                            $amount = $detail -> getElementsByTagNameNS($glcor, 'amount') -> item(0) -> nodeValue;
                            $amount = trim($amount);
                            debug_log(__LINE__ . ":" . 'Amount:' . $amount);
                            $detailComment = $detail -> getElementsByTagNameNS($glcor, 'detailComment') -> item(0) -> nodeValue;
                            $detailComment = trim($detailComment);
                            debug_log(__LINE__ . ":" . 'Detail Comment:' . $detailComment);
                            /*
                             * INSERT TABLE
                             */
                            $sql = "INSERT INTO entrydetails(";
                            $sql .= "entryHeadersID, postingDate, ";
                            $sql .= "accountMainID, accountMainDescription, accountType, accountSubID, accountSubDescription, accountSubType, ";
                            $sql .= "debitCreditCode, amount, detailComment";
                            $sql .= ") ";
                            $sql .= "VALUES(";
                            $sql .= "'".$entryHeadersID."','".$postingDate."',";
                            $sql .= "'".$accountMainID."','".$accountMainDescription."','".$accountType."','".$accountSubID."','".$accountSubDescription."','".$accountSubType."',";
                            $sql .= "'".$debitCreditCode."','".$amount."','".$detailComment."'";
    /*                        $sql .= ":entryHeadersID, :postingDate, ";
                            $sql .= ":accountMainID, :accountMainDescription, :accountType, :accountSubID, :accountSubDescription, :accountSubType";
                            $sql .= ":debitCreditCode, :amount, :detailComment";
    */
                            $sql .= ") ";
                            debug_log(__LINE__ . ':'.$sql);
                            $stmt = $pdo -> prepare($sql);
    /*                        $stmt -> bindParam(':entryHeadersID', $entryHeadersID);
                            $stmt -> bindParam(':postingDate', $postingDate);
                            $stmt -> bindParam(':accountMainID', $accountMainID);
                            $stmt -> bindParam(':accountMainDescription', $accountMainDescription);
                            $stmt -> bindParam(':accountType', $accountType);
                            $stmt -> bindParam(':accountSubID', $accountSubID);
                            $stmt -> bindParam(':accountSubDescription', $accountSubDescription);
                            $stmt -> bindParam(':accountSubType', $accountSubType);
                            $stmt -> bindParam(':debitCreditCode', $debitCreditCode);
                            $stmt -> bindParam(':amount', $amount);
                            $stmt -> bindParam(':detailComment', $detailComment);
                            debug_log(__LINE__ . ':'.
                                ' :entryHeadersID='. $entryHeadersID.
                                ' :postingDate='. $postingDate.
                                ' :accountMainID='. $accountMainID.
                                ' :accountMainDescription='. $accountMainDescription.
                                ' :accountType='. $accountType.
                                ' :accountSubID='. $accountSubID.
                                ' :accountSubDescription='. $accountSubDescription.
                                ' :accountSubType='. $accountSubType.
                                ' :debitCreditCode='. $debitCreditCode.
                                ' :amount='. $amount.
                                ' :detailComment='. $detailComment
                            );
    */
                            try {
                                $stmt -> execute();
                            } catch( PDOExecption $e ) {
                                print "Error!: " . $e -> getMessage() . "<br>";
                            }
                        endforeach;
                    endif;
                endforeach;
            endif;
        endforeach;
    endif;
}
?>
