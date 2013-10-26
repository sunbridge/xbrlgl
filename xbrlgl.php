<?php
//
// Check if sessioc directory's owner is web server.
// This installation's location is /var/lib/php/session
include 'postgresql.php';

$timezone = "Asia/Tokyo";
if (function_exists('date_default_timezone_set'))
    date_default_timezone_set($timezone);

$glcor = 'http://www.xbrl.org/taxonomy/int/gl/cor/2003-08-29/';
$glbus = 'http://www.xbrl.org/taxonomy/int/gl/bus/2003-08-29/';
$glmuc = 'http://www.xbrl.org/taxonomy/int/gl/muc/2003-08-29/';

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
    error_log($msg . "\n", 3, "/ebs/www/sambuichi.jp/public_html/logs/tao.log");
}

function save_xbrlgl($pdo, $xbrl) {
    $dom = new DOMDocument;
    $dom -> loadXML($xbrl);
    if (!$dom) {
        echo 'Error while parsing the document';
        exit ;
    }
    $entries = $dom -> getElementsByTagNameNS($glcor, 'accountingEntries');
    if (count($entries)) :
        foreach ($entries as $entry) :
            echo 'ENTRY' . '<br/>';
            /*
             * documentInfo
             */
            echo '- DOCUMENT' . '<br/>';
            $documentInfo = $entry -> getElementsByTagNameNS($glcor, 'documentInfo') -> item(0);
            $entriesType = $documentInfo -> getElementsByTagNameNS($glcor, 'entriesType') -> item(0) -> nodeValue;
            $entriesType = trim($entriesType);
            echo 'Entries Type：' . $entriesType . '<br/>';
            $uniqueID = $documentInfo -> getElementsByTagNameNS($glcor, 'uniqueID') -> item(0) -> nodeValue;
            $uniqueID = trim($uniqueID);
            echo 'Unique ID：' . $uniqueID . '<br/>';
            /*
             * entityInformation
             */
            echo '--  ENTITY' . '<br/>';
            $entityInformation = $entry -> getElementsByTagNameNS($glcor, 'entityInformation') -> item(0);
            $organizationIdentifiers = $entityInformation -> getElementsByTagNameNS($glbus, 'organizationIdentifiers') -> item(0);
            $organizationIdentifier = $organizationIdentifiers -> getElementsByTagNameNS($glbus, 'organizationIdentifier') -> item(0) -> nodeValue;
            $organizationIdentifier = trim($organizationIdentifier);
            echo 'Identifier：' . $organizationIdentifier . '<br/>';
            $organizationDescription = $organizationIdentifiers -> getElementsByTagNameNS($glbus, 'organizationDescription') -> item(0) -> nodeValue;
            $organizationDescription = trim($organizationDescription);
            echo 'Description：' . $organizationDescription . '<br/>';
            $organizationAddress = $entityInformation -> getElementsByTagNameNS($glbus, 'organizationAddress');
            if (count($organizationAddress)) :
                echo '--  ADDRESS' . '<br/>';
                foreach ($organizationAddress as $address) :
                    $organizationAddressStreet = $address -> getElementsByTagNameNS($glbus, 'organizationAddressStreet') -> item(0) -> nodeValue;
                    $organizationAddressStreet = trim($organizationAddressStreet);
                    echo 'Street:' . $organizationAddressStreet . '<br/>';
                    $organizationAddressZipOrPostalCode = $address -> getElementsByTagNameNS($glbus, 'organizationAddressZipOrPostalCode') -> item(0) -> nodeValue;
                    $organizationAddressZipOrPostalCode = trim($organizationAddressZipOrPostalCode);
                    echo 'Zip or Postal Code:' . $organizationAddressZipOrPostalCode . '<br/>';
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
            $sql .= ":entriesType, :uniqueID, ";
            $sql .= ":organizationIdentifier, :organizationDescription, :organizationAddressStreet, :organizationAddressZipOrPostalCode";
            $sql .= ") ";
            debug_log(__LINE__ . ': '.$sql);
            $stmt = $pdo -> prepare($sql);
            $stmt -> bindParam(':entriesType', $entriesType);
            $stmt -> bindParam(':uniqueID', $uniqueID);
            $stmt -> bindParam(':organizationIdentifier', $organizationIdentifier);
            $stmt -> bindParam(':organizationDescription', $organizationDescription);
            $stmt -> bindParam(':organizationAddressStreet', $organizationAddressStreet);
            $stmt -> bindParam(':organizationAddressZipOrPostalCode', $organizationAddressZipOrPostalCode);
            debug_log(__LINE__ . ': '.
                ' :entriesType='. $entriesType.
                ' :uniqueID='. $uniqueID.
                ' :organizationIdentifier='. $organizationIdentifier.
                ' :organizationDescription='. $organizationDescription.
                ' :organizationAddressStreet='. $organizationAddressStreet.
                ' :organizationAddressZipOrPostalCode='. $organizationAddressZipOrPostalCode
            );
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
                    echo '- HEADER' . '<br/>';
                    $enteredDate = $header -> getElementsByTagNameNS($glcor, 'enteredDate') -> item(0) -> nodeValue;
                    $enteredDate = trim($enteredDate);
                    echo 'Entered date:' . $enteredDate . '<br/>';
                    $sourceJournalDescription = $header -> getElementsByTagNameNS($glbus, 'sourceJournalDescription') -> item(0) -> nodeValue;
                    $sourceJournalDescription = trim($sourceJournalDescription);
                    echo 'Source Journal:' . $sourceJournalDescription . '<br/>';
                    $entryNumber = $header -> getElementsByTagNameNS($glcor, 'entryNumber') -> item(0) -> nodeValue;
                    $entryNumber = trim($entryNumber);
                    echo 'Entry Number:' . $entryNumber . '<br/>';
                    /*
                     * INSERT TABLE
                     */
                    $sql = "INSERT INTO entryheaders(";
                    $sql .= "accountingEnrtiesID, enteredDate, sourceJournalDescription, entryNumber";
                    $sql .= ") ";
                    $sql .= "VALUES(";
                    $sql .= ":accountingEnrtiesID, :enteredDate, :sourceJournalDescription, :entryNumber";
                    $sql .= ") ";
                    debug_log(__LINE__ . ': '.$sql);
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
                    $entryDetail = $entry -> getElementsByTagNameNS($glcor, 'entryDetail');
                    if (count($entryDetail)) :
                        foreach ($entryDetail as $detail) :
                            echo '--  DETAIL' . '<br/>';
                            $postingDate = $detail -> getElementsByTagNameNS($glcor, 'postingDate') -> item(0) -> nodeValue;
                            $postingDate = trim($postingDate);
                            echo 'Posting Date:' . $postingDate . '<br/>';
                            echo '---   ACCOUNT' . '<br/>';
                            $account = $header -> getElementsByTagNameNS($glcor, 'account') -> item(0);
                            $accountMainID = $account -> getElementsByTagNameNS($glcor, 'accountMainID') -> item(0) -> nodeValue;
                            $accountMainID = trim($accountMainID);
                            echo 'Account Main ID:' . $accountMainID . '<br/>';
                            $accountMainDescription = $account -> getElementsByTagNameNS($glcor, 'accountMainDescription') -> item(0) -> nodeValue;
                            $accountMainDescription = trim($accountMainDescription);
                            echo 'Account Main Description:' . $accountMainDescription . '<br/>';
                            $accountType = $account -> getElementsByTagNameNS($glcor, 'accountType') -> item(0) -> nodeValue;
                            $accountType = trim($accountType);
                            echo 'Account Type:' . $accountType . '<br/>';
                            $accountSub = $account -> getElementsByTagNameNS($glcor, 'accountSub') -> item(0);
                            $accountSubID = $accountSub -> getElementsByTagNameNS($glcor, 'accountSubID') -> item(0) -> nodeValue;
                            $accountSubID = trim($accountSubID);
                            echo 'Aaccount Sub ID:' . $accountSubID . '<br/>';
                            $accountSubDescription = $accountSub -> getElementsByTagNameNS($glcor, 'accountSubDescription') -> item(0) -> nodeValue;
                            $accountSubDescription = trim($accountSubDescription);
                            echo 'Aaccount Sub Description:' . $accountSubDescription . '<br/>';
                            $accountSubType = $accountSub -> getElementsByTagNameNS($glcor, 'accountSubType') -> item(0) -> nodeValue;
                            $accountSubType = trim($accountSubType);
                            echo 'Aaccount Sub Type:' . $accountSubType . '<br/>';
                            echo '---' . '<br/>';
                            $debitCreditCode = $detail -> getElementsByTagNameNS($glcor, 'debitCreditCode') -> item(0) -> nodeValue;
                            $debitCreditCode = trim($debitCreditCode);
                            echo 'Debit Credit Code:' . $debitCreditCode . '<br/>';
                            $amount = $detail -> getElementsByTagNameNS($glcor, 'amount') -> item(0) -> nodeValue;
                            $amount = trim($amount);
                            echo 'Amount:' . $amount . '<br/>';
                            $detailComment = $detail -> getElementsByTagNameNS($glcor, 'detailComment') -> item(0) -> nodeValue;
                            $detailComment = trim($detailComment);
                            echo 'Detail Comment:' . $detailComment . '<br/>';
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
                            debug_log(__LINE__ . ': '.$sql);
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
                            debug_log(__LINE__ . ': '.
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
    $pdo = null;
}

function journal($pdo, $period, $account) {
    $current_number = 0;
    $count = 0;
    $sql = "SELECT postingdate,entrynumber,detailcomment,account_d,account_c,debit,credit ";
    $sql .= "FROM journal_entry ";
    if($period || $account) {
        $sql .= "WHERE ";
        if ($period && !$account)
            $sql .= "postingdate~'".$period."' ";
        else if(!$period && $account) {
            $sql .= "postingdate||'_'||entrynumber IN (";
            $sql .= "SELECT postingdate||'_'||entrynumber FROM journal_entry WHERE accountid_d='".$account."' OR accountid_c='".$account."')";
        }
        else if($period && $account) {
            $sql .= "postingdate~'".$period."' ";
            $sql .= "AND postingdate||'_'||entrynumber IN (";
            $sql .= "SELECT postingdate||'_'||entrynumber FROM journal_entry WHERE accountid_d='".$account."' OR accountid_c='".$account."')";
        }
    }
    $sql .= "ORDER BY postingdate,entrynumber;";
    debug_log(__LINE__ . ': '.$sql);
    $stmt = $pdo -> query($sql);
    print '<table width="97%" border="1" cellpadding="4">';
    print '<tr style="color:#fff; background:#898;" align="center"><td width="10%">日付</td><td width="5%">＃</td><td>摘要</td>';
    print '<td width="14%">借方科目</td><td width="14%">貸方科目</td><td width="11%">借方</td><td width="11%">貸方</td></tr>';
    while ($row = $stmt -> fetch(PDO::FETCH_ASSOC)) {
        $entrynumber = $row['entrynumber'];
        if ($current_number != $entrynumber) {
            $count += 1;
        }
        if ($count%2 == 0) {
            print '<tr style="background:#eff;">';
        }
        else {
            print "<tr>";
        }
        $current_number = $entrynumber;
        $postingdate = $row['postingdate'];
        print "<td>".$postingdate."</td>";
        print "<td align='right'>".$entrynumber."</td>";
        $detailcomment = $row['detailcomment'];
        print "<td>".$detailcomment."</td>";
        $account_d = $row['account_d'];
        print "<td>".$account_d."</td>";
        $account_c = $row['account_c'];
        print "<td>".$account_c."</td>";
        $debit = $row['debit'];
        if (is_numeric($debit))
            print "<td align='right'>".number_format($debit)."</td>";
        else
            print "<td></td>";
        $credit = $row['credit'];
        if (is_numeric($credit))
            print "<td align='right'>".number_format($credit)."</td>";
        else
            print "<td></td>";
        print "</tr>";
    }
    print "</table>";
    $stmt=null;
    $sql=null;
    $pdo=null;
}

function calc($pdo, $period, $account) {
    $current_number = 0;
    $count = 0;
    $sql = "SELECT postingdate,entrynumber,detailcomment,accountid_d,account_d,debit,accountid_c,account_c,credit ";
    $sql .= "FROM v_entry ";
	$sql_condition = "";
    if($period || $account) {
        $sql_condition .= "WHERE ";
        if ($period && !$account)
            $sql_condition .= "postingdate~'".$period."' ";
        else if(!$period && $account) {
            $sql_condition .= "postingdate||'_'||entrynumber IN (";
            $sql_condition .= "SELECT postingdate||'_'||entrynumber FROM v_entry ";
            $sql_condition .= "WHERE (accountid_d='".$account."' OR accountid_c='".$account."') ";
            $sql_condition .= "AND postingdate~'".$period."') ";
        }
        else if($period && $account) {
            $sql_condition .= "postingdate~'".$period."' ";
            $sql_condition .= "AND postingdate||'_'||entrynumber IN (";
            $sql_condition .= "SELECT postingdate||'_'||entrynumber FROM v_entry ";
            $sql_condition .= "WHERE (accountid_d='".$account."' OR accountid_c='".$account."') ";
            $sql_condition .= "AND postingdate~'".$period."') ";
        }
    }
	$sql .= $sql_condition; 
    $sql .= "ORDER BY postingdate,entrynumber;";
    debug_log(__LINE__ . ': '.$sql);
    $STH_SELECT = $pdo->query("SELECT count(*) FROM v_entry ".$sql_condition);
	$total_recordcount = $STH_SELECT->fetchColumn();
	debug_log(__LINE__ . ': Total Record Count='.$total_recordcount);
    $stmt = $pdo -> query($sql);
	$recordcount=0;
    $prev_postingdate='';
    $prev_entrynumber='';
    $first=true;
    print '<table width="97%" border="0" cellpadding="4">';
    print '<tr style="color:#fff; background:#898;" align="center"><td width="10%">日付</td><td width="5%">＃</td><td>摘要</td>';
    print '<td width="14%">借方科目</td><td width="14%">貸方科目</td><td width="11%">借方</td><td width="11%">貸方</td></tr>';
    while ($row = $stmt -> fetch(PDO::FETCH_ASSOC)) {
    	$recordcount += 1;
 		debug_log(__LINE__ . ': Record Count='.$recordcount);
        $postingdate = $row['postingdate'];
        $entrynumber = $row['entrynumber'];
        $detailcomment = $row['detailcomment'];
        $accountid_d = $row['accountid_d'];
        $account_d = $row['account_d'];
        $debit = $row['debit'];
        $accountid_c = $row['accountid_c'];
        $account_c = $row['account_c'];
        $credit = $row['credit'];
        debug_log(__LINE__ . ': date '.$prev_postingdate.'/'.$postingdate.' entry# '.$prev_entrynumber.'/'.$entrynumber);
        if ($prev_postingdate != $postingdate || $prev_entrynumber!=$entrynumber) {
            $index=0;
        }
        else {
            $index += 1;
        }
        debug_log(__LINE__ . ': index='.$index);
        if($recordcount==$total_recordcount) {
	        $source_entry[$index]['postingdate'] = $postingdate;
	        $source_entry[$index]['entrynumber'] = $entrynumber;
	        $source_entry[$index]['detailcomment'] = $detailcomment;
	        $source_entry[$index]['accountid_d'] = $accountid_d;
	        $source_entry[$index]['account_d'] = $account_d;
	        $source_entry[$index]['debit'] = $debit;
	        $source_entry[$index]['accountid_c'] = $accountid_c;
	        $source_entry[$index]['account_c'] = $account_c;
	        $source_entry[$index]['credit'] = $credit;
        }
        if ((!$first && $index==0)||$recordcount==$total_recordcount) {
            foreach ( $source_entry as $key_s=>$val_s) {
            	debug_log(__LINE__ . ': '
            		.'S '.$key_s.' : '.$val_s['postingdate'].'　'.$val_s['entrynumber'].'　'.$val_s['detailcomment'].' '
                    .$val_s['accountid_d'].' '.$val_s['account_d'].' '
                    .$val_s['accountid_c'].' '.$val_s['account_c'].' '
                    .number_format(intval($val_s['debit'])).' '
                    .number_format(intval($val_s['credit']))
				);
                $overwrote=false;
                if ($key_s==0) {
                    $key_t=-1;
                }
                else {
                    foreach ( $target_entry as $key_t=>$val_t) {
                        if ($val_s['detailcomment']==$val_t['detailcomment']) {
            				debug_log(__LINE__ . ': T:'.$key_t.' S:'.$key_s);
                            debug_log(__LINE__ . ': *T'.$key_t.' : '.$val_t['postingdate'].' '.$val_t['entrynumber'].' '.$val_t['detailcomment'].' '
                                .$val_t['accountid_d'].' '.$val_t['account_d'].' '.$val_t['debit'].' '
                                .$val_t['accountid_c'].' '.$val_t['account_c'].' '.$val_t['credit']);
                            debug_log(__LINE__ . ': *S'.$key_s.' '.$val_s['postingdate'].' '.$val_s['entrynumber'].' '.$val_s['detailcomment'].' '
                                .$val_s['accountid_d'].' '.$val_s['account_d'].' '.$val_s['debit'].' '
                                .$val_s['accountid_c'].' '.$val_s['account_c'].' '.$val_s['credit']);
                            if ($val_s['debit']!='' && $val_t['debit']==''){
                                $debit_s = intval($val_s['debit']);
                                $credit_t = intval($val_t['credit']);
                                debug_log(__LINE__ . ': -T '.$key_t.' credit '.$credit_t.' -S '.$key_s.' debit '.$debit_s);
                                if ($debit_s == $credit_t
									|| ( $credit_t*1.04 < $debit_s
                                        && $debit_s < $credit_t*1.06)
                                    || ($debit_s*1.04 < $credit_t
                                        && $credit_t < $debit_s*1.06)) {
                                    $target_entry[$key_t]['accountid_d'] = $val_s['accountid_d'];
                                    $target_entry[$key_t]['account_d'] = $val_s['account_d'];
                                    $target_entry[$key_t]['debit'] = $val_s['debit'];
                                    $overwrote=true;
                                    debug_log(__LINE__ . ': Debit: *T overwrote '.$key_t);
                                }
                            }
                            else if ($val_s['credit']!='' && $val_t['credit']=='') {
                                $credit_s = intval($val_s['credit']);
                                $debit_t = intval($val_t['debit']);
                                debug_log(__LINE__ . ': -T '.$key_t.' debit '.$debit_t.' -S '.$key_s.' credit '.$credit_s);
                                if ($credit_s == $debit_t
									|| ($debit_t*1.04 < $credit_s
                                        && $credit_s < $debit_t*1.06)
                                    || ($credit_s*1.04 < $debit_t
                                        && $debit_t < $credit_s*1.06) ) {
                                    $target_entry[$key_t]['accountid_c'] = $val_s['accountid_c'];
                                    $target_entry[$key_t]['account_c'] = $val_s['account_c'];
                                    $target_entry[$key_t]['credit'] = $val_s['credit'];
                                    $overwrote=true;
                                    debug_log(__LINE__ . ': Credit: *T overwrote '.$key_t);
                                }
                            }
                        }
                    }
                }
                if(!$overwrote) {
                    $i = $key_t + 1;
                    $target_entry[$i]['postingdate'] = $val_s['postingdate'];
                    $target_entry[$i]['entrynumber'] = $val_s['entrynumber'];
                    $target_entry[$i]['detailcomment'] = $val_s['detailcomment'];
                    $target_entry[$i]['accountid_d'] = $val_s['accountid_d'];
                    $target_entry[$i]['account_d'] = $val_s['account_d'];
                    $target_entry[$i]['debit'] = $val_s['debit'];
                    $target_entry[$i]['accountid_c'] = $val_s['accountid_c'];
                    $target_entry[$i]['account_c'] = $val_s['account_c'];
                    $target_entry[$i]['credit'] = $val_s['credit'];
                    debug_log(__LINE__ . ': +T '.$i.' '.$target_entry[$i]['postingdate'].' '.$target_entry[$i]['entrynumber'].' '
                    	.$target_entry[$i]['detailcomment'].' '
                        .$target_entry[$i]['accountid_d'].' '.$target_entry[$i]['account_d'].' '.$target_entry[$i]['debit'].' '
                    	.$target_entry[$i]['accountid_c'].' '.$target_entry[$i]['account_c'].' '.$target_entry[$i]['credit']);
                }
            }
            foreach ( $target_entry as $t=>$val_t) {
                debug_log(__LINE__ . ': T'.$t.' '.$val_t['postingdate'].' '.$val_t['entrynumber'].' '
                	.$val_t['detailcomment'].' '
                    .$val_t['accountid_d'].' '.$val_t['account_d'].' '.number_format($val_t['debit']).' '
                    .$val_t['accountid_c'].' '.$val_t['account_c'].' '.number_format($val_t['credit']));
 				
            }
			$sum_d=0;
			$sum_c=0;
			$count_d=0;
			$count_c=0;
			$w_comment_d = '';
			$w_comment_c = '';
			$w_accountid_d = '';
			$w_accountid_c = '';
			$w_account_d = '';
			$w_account_c = '';
			$w_debit = '';
			$w_credit = '';
            foreach ( $target_entry as $t=>$val_t) {
                $w_postingdate = $val_t['postingdate'];
                $w_entrynumber = $val_t['entrynumber'];
            	$debit_t = intval($val_t['debit']);
				$credit_t = intval($val_t['credit']);
				$sum_d += $debit_t;
				$sum_c += $credit_t;
				$w_comment = $val_t['detailcomment'];
				if ($w_comment != '' && $debit_t > 0 && $credit_t == 0) {
					$count_d += 1;
					if ($count_d == 1) {
						$w_comment_d = $w_comment;
						$w_accountid_d = $val_t['accountid_d'];
						$w_account_d = $val_t['account_d'];
						$w_debit = $val_t['debit'];
            			debug_log(__LINE__ . ': '.$t.' debit '.$w_comment.' '.$w_accountid_d.' '.$w_account_d.' '.number_format($w_debit));
					}
					else {
						$w_comment_d = '';
						$w_accountid_d = '';
						$w_account_d = '';
						$w_debit = '';
					}
				}
				if ($w_comment != '' && $debit_t == 0 && $credit_t > 0) {
					$count_c += 1;
					if ($count_c == 1) {
						$w_comment_c = $w_comment;
						$w_accountid_c = $val_t['accountid_c'];
						$w_account_c = $val_t['account_c'];
						$w_credit = $val_t['credit'];
            			debug_log(__LINE__ . ': '.$t.' credit '.$w_comment.' '.$w_accountid_c.' : '.$w_account_c.' '.number_format($w_credit));
					}
					else {
						$w_comment_c = '';
						$w_accountid_c = '';
						$w_account_c = '';
						$w_credit = '';
					}
				}
			}
            debug_log(__LINE__ . ': count_d='.$count_d.' count_c='.$count_c);
			$out_ontry = null;
			$idx = 0;
            if ($w_comment_d != '' && $count_d == 1) {
	            debug_log(__LINE__ . ': xxxxxx '.$w_comment_d.' '.$w_accountid_d.' '.$w_account_d.' '.number_format(intval($w_debit)));				
				$mapped=false;
	            foreach ($target_entry as $t=>$val_t) {
	            	if (!$mapped 
	            		&& $w_comment_d == $val_t['detailcomment']
						&& intval($val_t['debit']) == 0) {
	                    $out_ontry[$idx]['postingdate'] = $val_t['postingdate'];
	                    $out_ontry[$idx]['entrynumber'] = $val_t['entrynumber'];
	                    $out_ontry[$idx]['detailcomment'] = $val_t['detailcomment'];
	                    $out_ontry[$idx]['accountid_d'] = $w_accountid_d;
	                    $out_ontry[$idx]['account_d'] = $w_account_d;
	                    $out_ontry[$idx]['debit'] = $w_debit;
	                    $out_ontry[$idx]['accountid_c'] = $val_t['accountid_c'];
	                    $out_ontry[$idx]['account_c'] = $val_t['account_c'];
	                    $out_ontry[$idx]['credit'] = $val_t['credit'];
	                    debug_log(__LINE__ . ': +T'.$i.' : '.$out_ontry[$idx]['postingdate'].' : '.$out_ontry[$idx]['entrynumber'].' '
	                    	.$out_ontry[$idx]['detailcomment'].' '
	                    	.$out_ontry[$idx]['accountid_d'].' : '.$out_ontry[$idx]['account_d'].' '.number_format($out_ontry[$idx]['debit']).' '
	                        .$out_ontry[$idx]['accountid_c'].' : '.$out_ontry[$idx]['account_c'].' '.number_format($out_ontry[$idx]['credit']));
						$idx += 1;
						$mapped = true;
	            	}
					else if (!($w_comment_d == $val_t['detailcomment'] 
						&& $w_accountid_d == $val_t['accountid_d'])) {
	                    $out_ontry[$idx]['postingdate'] = $val_t['postingdate'];
	                    $out_ontry[$idx]['entrynumber'] = $val_t['entrynumber'];
	                    $out_ontry[$idx]['detailcomment'] = $val_t['detailcomment'];
	                    $out_ontry[$idx]['accountid_d'] = $val_t['accountid_d'];
	                    $out_ontry[$idx]['account_d'] = $val_t['account_d'];
	                    $out_ontry[$idx]['debit'] = $val_t['debit'];
	                    $out_ontry[$idx]['accountid_c'] = $val_t['accountid_c'];
	                    $out_ontry[$idx]['account_c'] = $val_t['account_c'];
	                    $out_ontry[$idx]['credit'] = $val_t['credit'];
						$idx += 1;
					}
				}
				if (!$mapped) {
                    $out_ontry[$idx]['postingdate'] = $w_postingdate;
                    $out_ontry[$idx]['entrynumber'] = $w_entrynumber;
                    $out_ontry[$idx]['detailcomment'] = $w_comment_d;
                    $out_ontry[$idx]['accountid_d'] = $w_accountid_d;
                    $out_ontry[$idx]['account_d'] = $w_account_d;
                    $out_ontry[$idx]['debit'] = $w_debit;
                    $out_ontry[$idx]['accountid_c'] = '';
                    $out_ontry[$idx]['account_c'] = '';
                    $out_ontry[$idx]['credit'] = '';					
				}
			}
			else if ($w_comment_c != '' && $count_c == 1) {
	            debug_log(__LINE__ . ': xxxxxx '.$w_comment_c.' '.$w_accountid_c.' '.$w_account_c.' '.number_format(intval($w_credit)));				
				$mapped=false;
	            foreach ( $target_entry as $t=>$val_t) {
	            	if (!$mapped 
	            		&& $w_comment_c == $val_t['detailcomment'] 
	            		&& intval($val_t['credit']) == 0) {
	                    $out_ontry[$idx]['postingdate'] = $val_t['postingdate'];
	                    $out_ontry[$idx]['entrynumber'] = $val_t['entrynumber'];
	                    $out_ontry[$idx]['detailcomment'] = $val_t['detailcomment'];
	                    $out_ontry[$idx]['accountid_d'] = $val_t['accountid_d'];
	                    $out_ontry[$idx]['account_d'] = $val_t['account_d'];
	                    $out_ontry[$idx]['debit'] = $val_t['debit'];
	                    $out_ontry[$idx]['accountid_c'] = $w_accountid_c;
	                    $out_ontry[$idx]['account_c'] = $w_account_c;
	                    $out_ontry[$idx]['credit'] = $w_credit;
	                    debug_log(__LINE__ . ': +T'.$i.' : '.$out_ontry[$idx]['postingdate'].' '.$out_ontry[$idx]['entrynumber'].' '
	                    	.$out_ontry[$idx]['detailcomment'].' '
	                        .$out_ontry[$idx]['accountid_d'].' : '.$out_ontry[$idx]['account_d'].' '.number_format($out_ontry[$idx]['debit']).' '
	                        .$out_ontry[$idx]['accountid_c'].' : '.$out_ontry[$idx]['account_c'].' '.number_format($out_ontry[$idx]['credit']));
						$idx += 1;
						$mapped = true;
	            	}
					else if (!($w_comment_c == $val_t['detailcomment'] 
						&& $w_accountid_c == $val_t['accountid_c'])) {
	                    $out_ontry[$idx]['postingdate'] = $val_t['postingdate'];
	                    $out_ontry[$idx]['entrynumber'] = $val_t['entrynumber'];
	                    $out_ontry[$idx]['detailcomment'] = $val_t['detailcomment'];
	                    $out_ontry[$idx]['accountid_d'] = $val_t['accountid_d'];
	                    $out_ontry[$idx]['account_d'] = $val_t['account_d'];
	                    $out_ontry[$idx]['debit'] = $val_t['debit'];
	                    $out_ontry[$idx]['accountid_c'] = $val_t['accountid_c'];
	                    $out_ontry[$idx]['account_c'] = $val_t['account_c'];
	                    $out_ontry[$idx]['credit'] = $val_t['credit'];
						$idx += 1;
					}
				}
				if (!$mapped) {
                    $out_ontry[$idx]['postingdate'] = $w_postingdate;
                    $out_ontry[$idx]['entrynumber'] = $w_entrynumber;
                    $out_ontry[$idx]['detailcomment'] = $w_comment_c;
                    $out_ontry[$idx]['accountid_d'] = '';
                    $out_ontry[$idx]['account_d'] = '';
                    $out_ontry[$idx]['debit'] = '';
                    $out_ontry[$idx]['accountid_c'] = $w_accountid_c;
                    $out_ontry[$idx]['account_c'] = $w_account_c;
                    $out_ontry[$idx]['credit'] = $w_credit;
				}
			}
			else {
	            foreach ( $target_entry as $t=>$val_t) {
                    $out_ontry[$t]['postingdate'] = $val_t['postingdate'];
                    $out_ontry[$t]['entrynumber'] = $val_t['entrynumber'];
                    $out_ontry[$t]['detailcomment'] = $val_t['detailcomment'];
                    $out_ontry[$t]['accountid_d'] = $val_t['accountid_d'];
                    $out_ontry[$t]['account_d'] = $val_t['account_d'];
                    $out_ontry[$t]['debit'] = $val_t['debit'];
                    $out_ontry[$t]['accountid_c'] = $val_t['accountid_c'];
                    $out_ontry[$t]['account_c'] = $val_t['account_c'];
                    $out_ontry[$t]['credit'] = $val_t['credit'];
				}
			}
            foreach ( $out_ontry as $i=>$val_out) {
		        $o_postingdate = $val_out['postingdate'];
		        $o_entrynumber = $val_out['entrynumber'];
		        if ($current_date != $o_postingdate || $current_number != $o_entrynumber) {
		            $count += 1;
		        }
		        if ($count%2 == 0) {
		            print '<tr style="background:#eff;">';
		        }
		        else {
		            print '<tr>';
		        }
                print '<td>'.$o_postingdate.'</td><td style="text-align:right">'.$o_entrynumber.'</td><td>'.$val_out['detailcomment'].'</td>'
                    .'<td>'/*.$val_out['accountid_d'].' '*/.$val_out['account_d'].'</td>'
                    .'<td>'/*.$val_out['accountid_c'].' '*/.$val_out['account_c'].'</td>'
                    .'<td style="text-align:right">'.number_format($val_out['debit']).'</td>'
                    .'<td style="text-align:right">'.number_format($val_out['credit']).'</td></tr>';
				$current_date = $o_postingdate;
				$current_number = $o_entrynumber;
            }
            $source_entry = null;
            $target_entry = null;
            $key_t = -1;
        }
        $first=false;
        $source_entry[$index]['postingdate'] = $postingdate;
        $source_entry[$index]['entrynumber'] = $entrynumber;
        $source_entry[$index]['detailcomment'] = $detailcomment;
        $source_entry[$index]['accountid_d'] = $accountid_d;
        $source_entry[$index]['account_d'] = $account_d;
        $source_entry[$index]['debit'] = $debit;
        $source_entry[$index]['accountid_c'] = $accountid_c;
        $source_entry[$index]['account_c'] = $account_c;
        $source_entry[$index]['credit'] = $credit;
        $prev_postingdate=$postingdate;
        $prev_entrynumber=$entrynumber;
        // print '<tr><td>$prev_postingdate</td><td>'.$prev_postingdate.'</td><td>$prev_entrynumber</td><td>'.$prev_entrynumber.'</td></tr>';
    }
    print '</table>';
}
$sql = NULL;
$action = NULL;
$period = NULL;
$account = NULL;
$xbrl = NULL;

if (isset($_GET['action']))       $action = $_GET['action'];
else if (isset($_POST['action'])) $action = $_POST['action'];
if (isset($_GET['period']))       $period = $_GET['period'];
else if (isset($_POST['period'])) $period = $_POST['period'];
if (isset($_GET['account']))      $account = $_GET['account'];
else if (isset($_POST['account']))$account = $_POST['account'];
debug_log(__LINE__ . ': action='.$action.' period='.$period.' account='.$account);

if (isset($_GET['xbrl']))         $xbrl = $_GET['xbrl'];
if (isset($_POST['xbrl']))        $xbrl = $_POST['xbrl'];

debug_log('action=' . $action);
debug_log($xbrl);

// process request based on action
switch ($action) {
    case 'save' :
        debug_log(__LINE__ . ': save_xbrlgl');
        save_xbrlgl($pdo, $xbrl);
        break;
    case 'journal' :
        debug_log(__LINE__ . ': journal');
        journal($pdo, $period, $account);
        break;
    case 'calc' :
        debug_log(__LINE__ . ': calc');
        calc($pdo, $period, $account);
        break;
    default :
}
?>
