<?php
require ('../../../php.files/classes/pdoDB.php');
require ('../../../php.files/classes/kas-framework.php');
$kas_framework->safesession();
$kas_framework->checkAuthStudent();
require (constant('tripple_return').'php.files/classes/generalVariables.php');
require (constant('tripple_return').'php.files/student_details.php');

extract($_POST);
//making sure tat the file was not accessed by the url
if (!isset($_POST['byepass'])) {
	exit('Error 404: File Cannot be Accessed');
}
	if ($kas_framework->strIsEmpty($pin) or $kas_framework->strIsEmpty($serial)) {
		$kas_framework->showDangerCallout('Please Provide a Pin and a Serial');
		$kas_framework->buttonController('#creditWalletButton', 'enable');
	} else {
		$pinCheck1 = "SELECT * FROM student_wallet_pins  WHERE codec = :pin AND sn = :serial";
		$db_handle = $dbh->prepare($pinCheck1);
		$db_handle->bindParam(':pin', $pin); $db_handle->bindParam(':serial', $serial); 
		$db_handle->execute();
		$paramGetFields = $db_handle->fetch(PDO::FETCH_OBJ);
		$db_handle = null;
		
		$result_of_status = @$paramGetFields->status;
		
		$pinCheck = "SELECT * FROM student_wallet_pins WHERE codec = :pin AND sn = :serial AND status = '0' LIMIT 1";
		$db_handleX = $dbh->prepare($pinCheck);
		$db_handleX->bindParam(':pin', $pin); $db_handleX->bindParam(':serial', $serial); 
		$db_handleX->execute();
		$paramGetFieldX = $db_handleX->fetch(PDO::FETCH_OBJ);
		$rowsPinx = $db_handleX->rowCount();
		$db_handleX = null;			
						
			if ($result_of_status == '1') {
				$kas_framework->showDangerCallout('This Pin and Serial is Archived. Warning!!!');
				$login_attempt = @$_SESSION['login_atempt'] + 1; // warning counter
				$kas_framework->buttonController('#creditWalletButton', 'enable');
				
			} else if ($rowsPinx == 0) {
				$kas_framework->showDangerCallout('Wrong Pin and(or) Serial');
				$login_attempt = @$_SESSION['login_atempt'] + 1;  // warning counter
				$kas_framework->buttonController('#creditWalletButton', 'enable');
				
			} else {
				$rechargeAmount = $paramGetFieldX->amount;
				$supposeNewAmount = $student_balance + $rechargeAmount;
				/* begin mysql transactions */
				$dbh->beginTransaction();
				
				$updateStudentWallet = "UPDATE student_wallet SET balance = '".$supposeNewAmount."',
							date_last_used = '".date('d/m/Y')."' WHERE student_id = '".$student_id_original."'  AND balance = '".$student_balance."' LIMIT 1";
						$db_updateStudentWallet = $dbh->prepare($updateStudentWallet);
						$db_updateStudentWallet->execute();
						$get_rows = $db_updateStudentWallet->rowCount();
						$db_updateStudentWallet = null;
						
					if ($get_rows == 0) {
						exit($kas_framework->showDangerCallout('Fatal Error: Could not Credit your Balance. Please Try Again. <a href="'.$kas_framework->help_url('?topic=query-failed').'" target="blank">&raquo;Explanation?</a>'));
						$kas_framework->buttonController('#creditWalletButton', 'enable');
					}
				
				$update_wallet_pin = "UPDATE student_wallet_pins SET status = '1',
							used_by = '".$student_id_original."' WHERE codec = :pin AND sn = :serial LIMIT 1";
						$db_update_wallet_pin = $dbh->prepare($update_wallet_pin);
						$db_update_wallet_pin->bindParam(':pin', $pin); $db_update_wallet_pin->bindParam(':serial', $serial); 
						$db_update_wallet_pin->execute();
						$get_rowsWallet = $db_update_wallet_pin->rowCount();
						$db_update_wallet_pin = null;	
							
					if ($get_rowsWallet == 0) {
						exit($kas_framework->showDangerCallout('Could not Track Your ID and Pin. Please try Again. <a href="'.$kas_framework->help_url('?topic=query-failed').'" target="blank">&raquo;Explanation?</a>'));
						$kas_framework->buttonController('#creditWalletButton', 'enable');
					}
					
				$paidby = (isset($_SESSION['tapp_par_username']))? "Parent": "Student";	
				$school_years = $kas_framework->getValue('current_year', 'tbl_config', 'id', '1');
				$gradeTerm = $kas_framework->getValue('grade_terms_id', 'grade_terms', 'current', '1');
				
				$save_record = "INSERT INTO payment_recharge_receipts (tution_recharge_by_user_id, tution_recharge_by_std_par, tution_amount_recharged, tution_recharge_sch_years, tution_recharge_grade, tution_recharge_terms, recharge_means, tution_recharge_date) 
					VALUES ('".$student_id_original."', '".$paidby."', '".$rechargeAmount."', '".$school_years."', '".$user_student_grade_year_grade_id."', '".$gradeTerm."', 'Wallet Pin', '".date('d/m/Y')."')";
					$db_save_record = $dbh->prepare($save_record);
						$db_save_record->execute();
						$get_recordCount = $db_save_record->rowCount();
						$db_save_record = null;
					
					if ($get_recordCount == 0) {
						//print mysql_error();
						exit($kas_framework->showDangerCallout('Could not Create Record of Payment <a href="'.$kas_framework->help_url('?topic=query-failed').'" target="blank">&raquo;Explanation?</a>'));
						$kas_framework->buttonController('#creditWalletButton', 'enable');
					}
				
				if ($get_rows > 0 and $get_rowsWallet > 0 and $get_recordCount > 0) {
					$dbh->commit();
					$kas_framework->showInfoCallout('Wallet has been Topped Up with N'.number_format($rechargeAmount).' and your New Balance is N'.number_format($supposeNewAmount).' ');
					print '<script type="text/javascript">$(\'#user_classic_balance\').html(\''.number_format($supposeNewAmount).'\')</script>';
				} else {
					$dbh->rollBack();
					$kas_framework->showDangerCallout('Fatal Error: Could not Update your Balance. Please Try Again. <a href="'.$kas_framework->help_url('?topic=query-failed').'" target="blank">&raquo;Explanation?</a>');
					$kas_framework->buttonController('#creditWalletButton', 'enable');
				}
			}
	}
	//credit attempt for the button
		$_SESSION['login_atempt'] = @$login_attempt; //gettting the total login attempts
			
			if ($_SESSION['login_atempt'] >= 3) {
				$kas_framework->buttonController('#creditWalletButton', 'disable');
				$_SESSION['login_atempt'] = 0; //resetting the total login attempt
			}
?>